import test from 'node:test';
import assert from 'node:assert/strict';
import { PassThrough } from 'node:stream';
import { mkdtempSync, realpathSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { randomUUID } from 'node:crypto';
import { HerdrLogs, readLogConfiguration } from '../src/herdr/logs.mjs';
import { installLogInput } from '../src/herdr/logs-input.mjs';
import { NativeWorkspaceContext, serveLocal } from '../src/herdr/context.mjs';

const turn = () => new Promise(done => setImmediate(done));
async function fixture(t) {
  let value = { generation: 'native', state: 'idle', worktree: '/primary-linked', child: null }, choices = true, fail = false;
  let block, release, calls = 0, narrow = false;
  const readers = [], input = new PassThrough();
  const logs = new HerdrLogs({ primary: { cwd: '/primary-linked', sessionId: 'parent' } }, { discovery: true, companion: {} }, () => {}, {
    selection: async () => { if (fail) throw Error('native transport lost'); return { ...value, at: Date.now() }; },
    sources: { processes: new Set(), async discover(worktree, { main }) {
      calls++; if (block) { block = false; await new Promise(done => { release = done; }); }
      const root = main ? '/canonical-main' : worktree;
      const source = { main: root, context: 'local', project: root, endpoint: 'unix:///local.sock', socket: { inode: 1 }, checkout: { root },
        witness: root, label: root, services: ['database', 'web'], members: [{ service: 'database', container: 'b' }, { service: 'web', container: 'a' }] };
      return { choices: choices ? [source] : [], notice: 'Stack unavailable — no matching runtime; no main fallback' };
    }, async close() {} },
    reader: source => {
      const reader = { source, processes: new Set(), history: [], units: 0, paused: false, frozen: null,
        async subscribe() { this.state = 'following'; this.processes.add({ pid: 1000 + readers.length }); },
        async stop() { this.generation = (this.generation ?? 0) + 1; this.processes.clear(); },
        async close() { await this.stop(); },
        async action(type) { if (type === 'pause') { this.paused = !this.paused; this.frozen = this.paused ? [...this.history] : null; } },
        summary() { return 'stack\nfollowing'; }, body() { return (this.paused ? this.frozen : this.history).join('\n'); }, view() { return this.body(); },
      };
      readers.push(reader); return reader;
    },
  });
  const queue = installLogInput(input, logs, { closing: () => false, close: () => {}, canChoose: () => !narrow, scroll() {}, changed() {} });
  const settle = async () => { await queue.settled(); await logs.logs.transition; };
  const tick = async () => { await logs.tick(); await settle(); };
  const select = async (worktree, generation = randomUUID()) => {
    value = { generation: 'native', state: worktree ? 'selected' : 'unavailable', worktree, child: { id: 'child', generation, pinned: false } }; await tick();
  };
  t.after(async () => { release?.(); await logs.close(); input.destroy(); });
  return { logs, readers, input, queue, tick, select, settle, key: text => input.write(text), calls: () => calls,
    idle: async () => { value = { generation: 'native', state: 'idle', worktree: '/primary-linked', child: null }; await tick(); },
    available: next => { choices = next; }, fail: () => { fail = true; }, narrow: next => { narrow = next; },
    async block() { block = true; release = null; input.write('r'); while (!release) await turn(); return () => release(); },
  };
}

test('native configuration admits only the sole current discovery caller, never legacy service declarations', async t => {
  const root = realpathSync(mkdtempSync(join(tmpdir(), 'logs-native-')));
  const native = new NativeWorkspaceContext({ cwd: root, sessionId: randomUUID(), sessionFile: null, pid: process.pid });
  const binding = await native.start(), token = randomUUID();
  const companion = await serveLocal(() => ({}));
  t.after(() => { native.close(); companion.close(); rmSync(root, { recursive: true, force: true }); });
  native.logs = { discovery: true, companion: { port: companion.port, token } };
  assert.equal((await readLogConfiguration(binding)).discovery, true);
  native.logs = { tests: [], companion: { port: companion.port, token } }; await assert.rejects(readLogConfiguration(binding));
  native.close(); await assert.rejects(readLogConfiguration(binding));
});

test('idle uses verified main, active uses only its own stack and Main pin survives activity/idle changes', async t => {
  const f = await fixture(t); await f.tick();
  assert.equal(f.logs.logs.current.source.main, '/canonical-main');
  f.logs.logs.current.history.push('old-main'); await f.select('/worktree-a');
  assert.equal(f.logs.logs.current.source.main, '/worktree-a'); assert.doesNotMatch(f.logs.view(), /old-main/);
  f.key('m'); await f.settle(); assert.equal(f.logs.mode, 'main'); assert.equal(f.logs.logs.current.source.main, '/canonical-main');
  const main = f.logs.logs.current;
  await f.select('/worktree-b'); assert.equal(f.logs.logs.current, main, 'activity cannot steal Main');
  await f.idle(); assert.equal(f.logs.logs.current, main); assert.match(f.logs.view(), /MAIN PIN/);
  assert.match(f.logs.view(), /^MAIN PIN · All services\n/);
  assert.doesNotMatch(f.logs.view(), /(?:^|\n)LOGS\n|f Follow|finite tail/);
  await f.select('/worktree-b'); f.key('f'); await f.settle();
  assert.equal(f.logs.mode, 'follow'); assert.equal(f.logs.logs.current.source.main, '/worktree-b');
  await f.idle(); assert.equal(f.logs.logs.current.source.main, '/canonical-main');
});

test('unrelated registration cannot steal the active source; active missing stack never falls back', async t => {
  const f = await fixture(t); await f.select('/worktree-a', 'same'); const old = f.logs.logs.current;
  await f.tick(); assert.equal(f.logs.logs.current, old, 'no selection change means no switch');
  f.available(false); await f.select('/late-worktree');
  assert.equal(f.logs.logs.current, null); assert.match(f.logs.view(), /unavailable.*no matching runtime/i);
  assert.equal(f.readers.at(-1).source.main, '/worktree-a');
  f.available(true); f.logs.logs.refreshAt = 0; await f.tick();
  assert.equal(f.logs.logs.current.source.main, '/late-worktree');
});

test('Services All/subset are inspected, stack changes reset All and clear live/frozen output', async t => {
  const f = await fixture(t); await f.select('/worktree-a');
  f.key('s'); await f.settle(); assert.equal(f.logs.logs.choice().kind, 'Services');
  // All -> database -> web; deselect web and apply database only.
  f.key('\x1b[B\x1b[B \r'); await f.settle();
  assert.deepEqual(f.logs.logs.current.source.members.map(row => row.service), ['database']); assert.equal(f.logs.logs.filterLabel(), 'database');
  f.logs.logs.current.history.push('stale'); f.key('p'); await f.settle();
  await f.select('/worktree-b'); assert.equal(f.logs.logs.filterLabel(), 'All');
  assert.equal(f.logs.logs.current.source.members.length, 2); assert.doesNotMatch(f.logs.view(), /stale/);
  f.key('s \r'); await f.settle(); assert.equal(f.logs.logs.filterLabel(), 'All');
});

test('narrow panes cannot apply a choice; fragmented arrows and coalesced mark/Enter retain semantics', async t => {
  const f = await fixture(t); await f.tick(); f.key('s'); await f.settle(); f.narrow(true);
  f.key('\x1b['); f.key('B \r'); await f.settle();
  assert.ok(f.logs.logs.menu); assert.equal(f.logs.logs.current, null);
  f.narrow(false); f.key('\r'); await f.settle();
  assert.deepEqual(f.logs.logs.current.source.members.map(row => row.service), ['web']);
});

test('accepted reader controls stay with original stack during queued discovery, then Main/Follow supersedes I/O', async t => {
  for (const keys of ['c', 'pc', 'cp', 'ccp', 'crp']) await t.test(keys, async t => {
    const f = await fixture(t); await f.select('/worktree-a');
    const release = await f.block(); f.key(keys); await f.logs.action('main'); release(); await f.settle();
    const old = f.logs.logs.entries.get('/worktree-a');
    assert.equal(old.closed, keys === 'c' || keys === 'pc' || keys === 'cp');
    assert.equal(old.paused, keys.includes('p'));
    assert.equal(f.logs.logs.current.source.main, '/canonical-main'); assert.equal(f.logs.logs.current.paused, false);
  });
});

test('queued cancellation is not lost on generation revocation and cannot cancel another stack', async t => {
  const f = await fixture(t); await f.select('/worktree-a');
  const release = await f.block(); f.key('s\x1b'); await new Promise(done => setTimeout(done, 550));
  const moving = f.select('/worktree-b'); release(); await moving; await f.settle();
  assert.equal(f.logs.logs.entries.get('/worktree-a').cancelled, true);
  assert.equal(f.logs.logs.current.source.main, '/worktree-b');
  await f.select('/worktree-a'); assert.equal(f.logs.logs.current, null);
  f.key('r'); await f.settle(); assert.equal(f.logs.logs.current.source.main, '/worktree-a');
});

test('lifetime loss/expiry clears output even under Main pin; no controls can revive an invalid generation', async t => {
  const f = await fixture(t); await f.tick(); f.key('m'); await f.settle();
  const old = f.logs.logs.current; old.history.push('private'); f.fail(); await f.tick();
  assert.equal(f.logs.invalidated, true); assert.equal(f.logs.logs.current, null); assert.deepEqual(old.history, []);
  const count = f.calls(); f.key('fmrsc'); await f.settle(); assert.equal(f.calls(), count);
  assert.doesNotMatch(f.logs.view(), /private/);
  const g = await fixture(t); await g.tick(); await new Promise(done => setTimeout(done, 1550));
  assert.equal(g.logs.invalidated, true); assert.equal(g.logs.logs.current, null);
});

test('native process health exposes only bounded tracked PIDs, never runtime records', async t => {
  const f = await fixture(t); await f.tick(); f.logs.logs.current.history.push('PRIVATE_DISPLAY');
  assert.equal(f.logs.readerPids().length, 1); assert.deepEqual(f.logs.metadataPids(), []);
  assert.doesNotMatch(JSON.stringify({ readerPids: f.logs.readerPids(), metadataPids: f.logs.metadataPids() }), /PRIVATE_DISPLAY/);
  f.key('c'); await f.settle(); assert.deepEqual(f.logs.readerPids(), []);
});

test('input queue remains bounded at sixteen accepted actions', async t => {
  const f = await fixture(t); await f.select('/worktree-a'); const release = await f.block();
  f.key('c'.repeat(16)); assert.equal(f.queue.pending(), 16); release(); await f.settle();
  assert.equal(f.queue.pending(), 0); assert.equal(f.logs.logs.entries.get('/worktree-a').closed, true);
});

test('empty service subsets are refused without changing menu semantics or admitting readers', async t => {
  const f = await fixture(t); await f.tick();
  f.key('s\x1b[B \x1b[B \r'); await f.settle();
  assert.equal(f.logs.logs.menu, 'services'); assert.equal(f.logs.logs.current, null);
  assert.match(f.logs.logs.choice().notice, /at least one service/);
  f.key('pc'); await f.settle();
  assert.equal(f.logs.logs.entries.get('main').closed, false, 'rejected Enter cannot reinterpret reader controls');
  f.key('\x1b[B \r'); await f.settle();
  assert.equal(f.logs.logs.filterLabel(), 'All');
});

test('Main and Follow reset service filters on stack switches and preserve explicit close intent', async t => {
  const f = await fixture(t); await f.select('/worktree-a');
  f.key('s\x1b[B \r'); await f.settle(); assert.equal(f.logs.logs.filterLabel(), 'web');
  f.key('m'); await f.settle(); assert.equal(f.logs.logs.filterLabel(), 'All');
  f.key('c'); await f.settle(); await f.select('/worktree-b');
  assert.equal(f.logs.logs.current?.open, false, 'Main remains explicitly closed while activity changes');
  f.key('f'); await f.settle(); assert.equal(f.logs.logs.current.source.main, '/worktree-b');
  f.key('m'); await f.settle(); assert.equal(f.logs.logs.current, null, 'returning to Main is not recovery');
  f.key('r'); await f.settle(); assert.equal(f.logs.logs.current.source.main, '/canonical-main');
  f.key('f'); await f.settle(); await f.select('/worktree-a');
  assert.equal(f.logs.logs.filterLabel(), 'All');
});
