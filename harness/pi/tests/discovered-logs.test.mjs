import test from 'node:test';
import assert from 'node:assert/strict';
import { DiscoveredLogs } from '../src/herdr/discovered-logs.mjs';
import { HerdrLogs } from '../src/herdr/logs.mjs';

const source = (container = 'a', service = 'web', main = '/checkout') => ({
  main, container, service, project: 'owned', endpoint: 'unix:///local.sock', socket: { inode: 1 },
  checkout: { root: main }, witness: container, label: `${service}/${container}`,
});
async function fixture(t) {
  let choices = [], error, calls = 0, active = 0, maximum = 0;
  const readers = [];
  const logs = new DiscoveredLogs(() => {}, { sources: {
    async discover() { calls++; active++; maximum = Math.max(maximum, active); await Promise.resolve(); active--; if (error) throw Error(error); return { choices, notice: 'inventory' }; },
    async close() {},
  }, reader: value => {
    const reader = { source: value, processes: new Set(), history: [], units: 0, paused: false, frozen: null, state: 'unselected',
      async subscribe() { this.state = 'following'; this.processes.add('owned-reader'); },
      async stop() { this.generation = (this.generation ?? 0) + 1; this.processes.clear(); },
      async close() { this.open = false; await this.stop(); },
      async action(type) {
        if (type === 'pause') { this.paused = !this.paused; this.frozen = this.paused ? [...this.history] : null; }
        if (type === 'toggle') { this.open = !this.open; if (!this.open) { await this.stop(); this.state = 'closed'; } else await this.subscribe(); }
      },
      body() { return (this.paused ? this.frozen : this.history).join('\n'); }, view() { return this.body(); },
    };
    readers.push(reader); return reader;
  } });
  t.after(() => logs.close());
  return { logs, readers, set: value => { choices = value; }, fail: value => { error = value; },
    calls: () => calls, maximum: () => maximum, refresh: () => logs.refresh({ force: true }) };
}

test('late runtime and delayed Docker recover without an explicit retry', async t => {
  const f = await fixture(t); f.fail('Docker unavailable'); await f.logs.rebind('/checkout');
  assert.equal(f.logs.current, null);
  f.fail(null); await f.refresh(); assert.equal(f.logs.current, null);
  f.set([source()]); await f.refresh(); assert.equal(f.readers.length, 1); assert.equal(f.logs.current.state, 'following');
});

test('stop, restart and replacement clear stale output and reap before a fresh verified generation', async t => {
  const f = await fixture(t); f.set([source()]); await f.logs.rebind('/checkout');
  const old = f.logs.current; old.history.push('stale'); await f.logs.action('pause');
  f.set([]); await f.refresh(); assert.equal(f.logs.current, null); assert.deepEqual(old.history, []); assert.equal(old.processes.size, 0);
  f.set([source('restarted')]); await f.refresh(); assert.equal(f.logs.current.source.container, 'restarted');
  assert.equal(f.logs.current.paused, true, 'pause is not silently undone'); assert.doesNotMatch(f.logs.view(), /stale/);
  f.set([source('replacement')]); await f.refresh(); assert.equal(f.logs.current.source.container, 'replacement');
  assert.ok(f.readers.slice(0, -1).every(reader => reader.processes.size === 0));
});

test('ambiguity, cancellation and explicit close are not overridden by background refresh', async t => {
  const f = await fixture(t); f.set([source(), source('b', 'worker')]); await f.logs.rebind('/checkout');
  assert.equal(f.logs.current, null); assert.equal(f.logs.choice().count, 2);
  await f.logs.action('cancel'); f.set([source()]); await f.refresh(); assert.equal(f.logs.current, null);
  await f.logs.action('select'); await f.logs.action('choose'); assert.ok(f.logs.current);
  await f.logs.action('toggle'); const count = f.readers.length;
  f.set([source('replacement')]); await f.refresh(); assert.equal(f.readers.length, count); assert.equal(f.logs.current.open, false);
  await f.logs.action('toggle'); assert.equal(f.logs.current.source.container, 'replacement');
});

test('changed endpoint or lost selected service never silently chooses another source', async t => {
  const f = await fixture(t); f.set([source()]); await f.logs.rebind('/checkout');
  f.set([source('worker', 'worker')]); await f.refresh(); assert.equal(f.logs.current, null);
  f.set([{ ...source('new'), socket: { inode: 2 } }]); await f.refresh(); assert.equal(f.logs.current, null);
  await f.logs.action('select'); await f.logs.action('choose'); assert.equal(f.logs.current.source.container, 'new');
});

test('refresh is serialized, rate limited and cannot resurrect held or disposed context', async t => {
  const f = await fixture(t); await f.logs.rebind('/checkout'); const calls = f.calls();
  await Promise.all(Array.from({ length: 10 }, () => f.logs.refresh())); assert.equal(f.calls(), calls);
  f.set([source()]); const pending = f.refresh(); f.logs.hold(); await pending; assert.equal(f.logs.current, null);
  await f.logs.rebind('/checkout'); assert.ok(f.logs.current); assert.equal(f.maximum(), 1);
  await f.logs.close(); await f.refresh(); assert.equal(f.logs.current, null);
});

test('an in-flight initial scan does not queue an immediate second automatic scan', async t => {
  let calls = 0, release;
  const logs = new DiscoveredLogs(() => {}, { sources: {
    async discover() { if (++calls === 1) await new Promise(done => { release = done; }); return { choices: [], notice: 'waiting' }; },
    async close() {},
  } });
  t.after(() => logs.close());
  const initial = logs.rebind('/checkout');
  while (!release) await new Promise(done => setImmediate(done));
  const refresh = logs.refresh(); release(); await Promise.all([initial, refresh]);
  assert.equal(calls, 1, 'automatic refresh must recheck the interval after queued work settles');
});

test('a discovery already in flight cannot re-admit an obsolete child checkout', async t => {
  let release;
  const opened = [];
  const logs = new DiscoveredLogs(() => {}, { sources: {
    async discover(worktree) {
      if (worktree === '/old') await new Promise(done => { release = done; });
      return { choices: [source(worktree, 'web', worktree)], notice: 'verified' };
    },
    async close() {},
  }, reader: selected => ({ source: selected, processes: new Set(), history: [],
    async subscribe() { opened.push(selected.main); this.state = 'following'; }, async stop() {}, async close() {},
  }) });
  t.after(() => logs.close());
  const old = logs.rebind('/old');
  while (!release) await new Promise(done => setImmediate(done));
  logs.hold(); const current = logs.rebind('/current'); release(); await Promise.all([old, current]);
  assert.deepEqual(opened, ['/current']); assert.equal(logs.current.source.main, '/current');
});

test('selection ticks refresh discovery without delaying native/child revocation', async t => {
  const f = await fixture(t);
  let selection = { generation: 'current', state: 'idle', worktree: '/checkout' };
  const companion = new HerdrLogs({ primary: { cwd: '/checkout' } }, { discovery: true, companion: {} }, () => {}, {
    selection: async () => ({ ...selection, at: Date.now() }), sources: f.logs.sources, reader: f.logs.reader,
  });
  t.after(() => companion.close());
  await companion.tick(); await companion.logs.transition; assert.equal(companion.logs.current, null);
  f.set([source()]); companion.logs.refreshAt = 0;
  await companion.tick(); await companion.logs.transition; assert.equal(companion.logs.current.state, 'following');
  selection = { generation: 'current', state: 'unavailable', worktree: null };
  await companion.tick(); assert.equal(companion.logs.current, null);
  const calls = f.calls(); await companion.tick(); await companion.logs.transition; assert.equal(f.calls(), calls);
});

test('reader lifetime remains an explicit recovery boundary and closure retains pause across rebind', async t => {
  const f = await fixture(t); f.set([source()]); await f.logs.rebind('/checkout');
  await f.logs.action('pause'); await f.logs.action('toggle');
  await f.logs.rebind('/checkout'); await f.logs.action('toggle'); assert.equal(f.logs.current.paused, true);
  f.logs.current.expired = true; await f.logs.current.stop();
  const count = f.readers.length; await f.refresh(); assert.equal(f.readers.length, count);
  await f.logs.action('reconnect'); assert.equal(f.readers.length, count + 1);
});

test('context revocation during explicit close cannot throw or lose closed/paused intent', async t => {
  const f = await fixture(t); f.set([source()]); await f.logs.rebind('/checkout');
  await f.logs.action('pause');
  const old = f.logs.current, stop = old.stop.bind(old); let release, waited = false;
  old.stop = async () => {
    await stop();
    if (!waited) { waited = true; await new Promise(done => { release = done; }); }
  };
  const closing = f.logs.action('toggle');
  while (!release) await new Promise(done => setImmediate(done));
  f.logs.hold(); release(); await closing; await f.logs.transition;
  assert.equal(f.logs.current, null);
  await f.logs.rebind('/checkout'); assert.equal(f.logs.current, null, 'explicit close survives context rebind');
  await f.logs.action('toggle'); assert.equal(f.logs.current.paused, true, 'pause survives revocation during close');
});

test('an unreaped reader blocks every automatic replacement', async t => {
  const f = await fixture(t); f.set([source()]); await f.logs.rebind('/checkout');
  const old = f.logs.current, stop = old.stop; old.stop = async () => {};
  f.set([source('replacement')]); await f.refresh(); assert.equal(f.logs.current, null); assert.equal(f.readers.length, 1);
  assert.match(f.logs.body(), /not exited/); old.stop = stop; await old.stop();
  await f.refresh(); assert.equal(f.logs.current.source.container, 'replacement');
});
