import test from 'node:test';
import assert from 'node:assert/strict';
import { mkdtempSync, realpathSync, writeFileSync, renameSync, rmSync } from 'node:fs';
import { randomUUID } from 'node:crypto';
import { join } from 'node:path';
import { tmpdir } from 'node:os';
import { RegisteredChildren } from '../src/workspace/registered-children.mjs';
import { NativeAgentBrowser } from '../src/workspace/native-agent-browser.mjs';
import { directory, sessionFileIdentity } from '../src/observation/custody.mjs';
import { retainChild, retainedPreferences } from '../src/observation/custodian.mjs';

async function fixture(t) {
  const cwd = realpathSync(mkdtempSync(join(tmpdir(), 'main-browser-'))), file = join(cwd, 'native.jsonl');
  writeFileSync(file, 'Owned witness boundary, not native launch evidence');
  const parent = randomUUID(), generation = randomUUID(), parentFile = sessionFileIdentity(file);
  let at = Date.now(), values = [], refusal = false, discoveryLost = false, widget, listener, component;
  const child = order => ({ id: randomUUID(), parent, generation, started: 1000 + order, order,
    sequence: 1, receivedAt: at, state: 'connected', lifecycle: 'completed', upstreamStatus: 'completed',
    source: 'pi-subagents', exitCode: null, heartbeatSeconds: 2,
    identity: { task: 'Unassigned', role: 'probe', assignment: `Attempt ${order}`, provider: 'synthetic', model: 'demo', thinking: 'off', worktree: cwd, attempt: `attempt-${order}` },
    history: [`FINAL_${order}`], omitted: 0, gaps: 0 });
  const options = { now: () => at,
    discover: () => { if (discoveryLost) throw Error('Discovery unavailable'); return [{ path: '/witness', descriptor: {} }]; },
    verify: async () => { if (refusal) throw Error('Refused witness'); return { parent, generation, parentFile, at, children: values }; } };
  const theme = { fg: (_tone, text) => text };
  const ctx = { cwd, sessionManager: { getSessionId: () => parent, getSessionFile: () => file }, ui: {
    setWidget: (_key, value) => { widget = value; },
    onTerminalInput: callback => { listener = callback; return () => { listener = null; }; },
    custom: factory => new Promise(done => { component = factory({ terminal: { rows: 24 }, requestRender() {} }, theme, {}, done); }),
  } };
  const tools = { matchesKey: (data, key) => data === key, truncateToWidth: (line, width) => line.slice(0, width), wrapTextWithAnsi: line => [line] };
  const reader = (root, changed, settings) => new RegisteredChildren(root, changed, { ...settings, ...options });
  let browser;
  const open = async () => { browser = new NativeAgentBrowser(ctx, tools, { reader }); browser.start(); await browser.children.refresh(); return browser; };
  const side = new RegisteredChildren(cwd, () => {}, { ...options, preferences: true });
  t.after(() => { browser?.close(); side.close(); rmSync(cwd, { recursive: true, force: true }); assert.equal(listener, null); assert.equal(widget, undefined); });
  return { cwd, file, parent, parentFile, child, side, open, component: () => component,
    set: next => { values = next; }, advance: ms => { at += ms; }, refuse: () => { refusal = true; }, loseDiscovery: () => { discoveryLost = true; },
    retained: () => retainedPreferences(directory(cwd, parent), parentFile, at),
    picker: () => widget({ requestRender() {} }, theme).render(200).join('\n') };
}

for (const release of ['escape', 'close', 'file-loss', 'custody-loss', 'expired']) test(`distinct main/side completed choices survive ninth-attempt pressure; ${release} releases main only`, async t => {
  const f = await fixture(t), children = new Map();
  const initial = Array.from({ length: 8 }, (_, i) => f.child(i + 1));
  for (const item of initial) children.set(item.id, item);
  f.set(initial); await f.side.start(f.parent, f.file); f.side.action('pin');
  const side = f.side.selected, browser = await f.open(); browser.activate();
  while (browser.children.sources.get(browser.children.selected).order !== 1) browser.children.action('next');
  const main = browser.children.selected, view = browser.view();
  assert.match(f.component().render(200).join('\n'), /FINAL_1/);
  await f.side.refresh(); // Side persistence must not overwrite the independent main choice.
  assert.ok(f.retained().includes(main), 'existing retention owner receives the main-only viewing choice');
  retainChild(children, f.child(9), f.retained());
  assert.equal(children.size, 8); assert.ok(children.has(initial[0].id)); assert.ok(children.has(initial[7].id));
  f.set([...children.values()]); await browser.children.refresh();
  assert.equal(browser.children.selected, main); assert.equal(f.side.selected, side); assert.equal(f.side.pin, side);
  assert.match(f.component().render(200).join('\n'), /FINAL_1/);
  if (release === 'escape') f.component().handleInput('escape');
  else if (release === 'close') browser.close();
  else if (release === 'file-loss') { renameSync(f.file, f.file + '.held'); browser.children.tick(); }
  else if (release === 'custody-loss') { f.refuse(); await browser.children.refresh(); }
  else f.advance(6000);
  if (release === 'escape' || release === 'close') await view;
  assert.ok(!f.retained().includes(main), 'main protection ends on disposal, loss or lease expiry');
  assert.ok(f.retained().includes(side));
  if (release === 'file-loss' || release === 'custody-loss') {
    assert.equal(browser.children.verifiedWorktree, null);
    assert.match(f.component().render(200).join('\n'), /Unavailable/);
    assert.doesNotMatch(f.component().render(200).join('\n'), /FINAL_1/);
  }
  retainChild(children, f.child(10), f.retained()); assert.equal(children.size, 8);
  assert.ok(!children.has(initial[0].id), 'released oldest main attempt becomes evictable');
  assert.ok(children.has(initial[7].id), 'AGENTS pin remains independent');
  browser.close(); await view;
});

test('only an explicitly focused main choice is protected; renewal, switching and picker Esc stay independent', async t => {
  const f = await fixture(t), older = f.child(1), newer = f.child(2); f.set([older, newer]);
  await f.side.start(f.parent, f.file); const side = f.side.selected, browser = await f.open();
  browser.children.action('next'); const main = browser.children.selected;
  assert.ok(!f.retained().includes(main), 'an unfocused default/remembered choice has no main retention');
  browser.activate();
  assert.notEqual(main, side); assert.ok(f.retained().includes(main));
  for (let i = 0; i < 3; i++) { f.advance(4000); browser.children.tick(); await browser.children.refresh(); assert.ok(f.retained().includes(main)); }
  browser.suspend(); assert.ok(!f.retained().includes(main)); assert.ok(f.retained().includes(side));
  browser.activate(); browser.children.action('next');
  assert.ok(!f.retained().includes(main)); assert.equal(f.side.selected, side);
});

test('main picker distinguishes real file loss, stays revoked after restore, and gives working explicit close/open recovery', async t => {
  const f = await fixture(t), item = f.child(1); f.set([item]); const browser = await f.open();
  renameSync(f.file, f.file + '.held'); browser.children.tick();
  assert.match(f.picker(), /Actual session file replaced\/unavailable/);
  assert.match(f.picker(), /\/fight-workspace close/); assert.match(f.picker(), /\/fight-workspace open/);
  assert.doesNotMatch(f.picker(), /No eligible agents/);
  renameSync(f.file + '.held', f.file);
  for (let i = 0; i < 3; i++) { browser.children.tick(); await browser.children.refresh(); }
  assert.equal(browser.children.sources.size, 0, 'restoring a file cannot resurrect a revoked reader');
  browser.close(); const reopened = await f.open();
  assert.equal(reopened.children.sources.size, 1); assert.equal(reopened.children.sources.get(reopened.children.selected).id, item.id);
});

for (const failure of ['refused', 'discovery', 'pending']) test(`main picker exposes ${failure} reader state instead of healthy empty`, async t => {
  const f = await fixture(t);
  if (failure === 'refused') f.refuse();
  if (failure === 'discovery') f.loseDiscovery();
  if (failure === 'pending') rmSync(f.file);
  await f.open();
  assert.match(f.picker(), failure === 'refused' ? /refused/ : /unavailable/);
  assert.doesNotMatch(f.picker(), /No eligible agents/);
});
