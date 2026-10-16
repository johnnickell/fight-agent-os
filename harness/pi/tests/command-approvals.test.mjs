import test from 'node:test';
import assert from 'node:assert/strict';
import { CommandApprovals } from '../src/jev/command-approvals.mjs';

const tick = () => new Promise(resolve => setImmediate(resolve));
function fixture() {
  const events = [], dialogs = [];
  const queue = new CommandApprovals(active => events.push(active));
  const open = signal => new Promise(resolve => { dialogs.push({ signal, resolve }); });
  return { events, dialogs, queue, open };
}

test('parallel commands receive separate ordered decisions and balanced blocked signals', async () => {
  const f = fixture();
  const first = f.queue.run(f.open), second = f.queue.run(f.open), third = f.queue.run(f.open);
  await tick(); assert.equal(f.dialogs.length, 1);
  f.dialogs[0].resolve(true); assert.equal(await first, true);
  await tick(); assert.equal(f.dialogs.length, 2);
  f.dialogs[1].resolve(false); assert.equal(await second, false);
  await tick(); assert.equal(f.dialogs.length, 3);
  f.dialogs[2].resolve(true); assert.equal(await third, true);
  assert.deepEqual(f.events, [true, false, true, false, true, false]);
});

test('cancelled waiters return promptly without bypassing the visible dialog', async () => {
  const f = fixture(), controller = new AbortController();
  const first = f.queue.run(f.open), second = f.queue.run(f.open, { signal: controller.signal });
  await tick(); controller.abort(); assert.equal(await second, false);
  const third = f.queue.run(f.open);
  await tick(); assert.equal(f.dialogs.length, 1); assert.equal(f.dialogs[0].signal.aborted, false);
  f.dialogs[0].resolve(true); await first; await tick(); assert.equal(f.dialogs.length, 2);
  f.dialogs[1].resolve(false); await third; assert.deepEqual(f.events, [true, false, true, false]);
});

test('cancellation dismisses the active dialog and releases all waiters without approval', async () => {
  const f = fixture();
  const requests = [f.queue.run(f.open), f.queue.run(f.open), f.queue.run(f.open)];
  await tick(); f.queue.cancel(); assert.deepEqual(await Promise.all(requests), [false, false, false]);
  await tick(); assert.equal(f.dialogs.length, 1); assert.equal(f.dialogs[0].signal.aborted, true);
  assert.deepEqual(f.events, [true, false]);
  const next = f.queue.run(f.open); await tick(); f.dialogs[1].resolve(true); assert.equal(await next, true);
  // A late result from the dismissed dialog cannot authorize another request or clear its state.
  f.dialogs[0].resolve(true); await tick(); assert.deepEqual(f.events, [true, false, true, false]);
});

test('dialog failure releases ownership and stale queued authority never opens a dialog', async () => {
  const f = fixture();
  const failure = assert.rejects(f.queue.run(async () => { throw Error('Dialog failed'); }), /Dialog failed/);
  const stale = assert.rejects(f.queue.run(f.open, { validate: () => { throw Error('Stale authority'); } }), /Stale authority/);
  const next = f.queue.run(f.open); await failure; await stale; await tick();
  assert.equal(f.dialogs.length, 1); f.dialogs[0].resolve(false); await next;
  assert.deepEqual(f.events, [true, false, true, false]);
});

test('already cancelled commands never open or report blocked', async () => {
  const f = fixture(), controller = new AbortController(); controller.abort();
  assert.equal(await f.queue.run(f.open, { signal: controller.signal }), false);
  assert.deepEqual(f.events, []); assert.deepEqual(f.dialogs, []);
});
