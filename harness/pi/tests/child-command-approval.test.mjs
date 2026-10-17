import test from 'node:test';
import assert from 'node:assert/strict';
import { createConnection, createServer } from 'node:net';
import { randomUUID } from 'node:crypto';
import { openChildApprovals, requestChildApproval } from '../src/jev/child-command-approval.mjs';
import { CommandApprovals } from '../src/jev/command-approvals.mjs';

const decision = () => ({ command: 'printf controlled', cwd: '/project', task: 'TASK-00491',
  role: 'engineer', session: randomUUID(), outcome: 'uncertain', reason: 'complete' });
const deferred = () => { let resolve; const promise = new Promise(r => { resolve = r; }); return {promise, resolve}; };

test('each decision requires its own confirmation of the exact command and context', async t => {
  const seen = [];
  const bridge = await openChildApprovals({validate() {}, confirm: request => { seen.push(request); return seen.length === 1; }});
  t.after(bridge.close);
  const command = decision();
  assert.equal(await requestChildApproval(bridge.environment, command), true);
  assert.equal(await requestChildApproval(bridge.environment, command), false);
  assert.equal(seen.length, 2);
  assert.notEqual(seen[0].id, seen[1].id);
  for (const [key, value] of Object.entries(command)) assert.equal(seen[0][key], value);
});

test('missing, malformed and wrong-token endpoints never confirm', async t => {
  let confirmations = 0;
  const bridge = await openChildApprovals({validate() {}, confirm() { confirmations++; return true; }});
  t.after(bridge.close);
  for (const endpoint of [undefined, '{}', '{', 'null', JSON.stringify({...JSON.parse(bridge.environment), token:'0'.repeat(64)})])
    assert.equal(await requestChildApproval(endpoint, decision()), false);
  assert.equal(confirmations, 0);
});

test('context rejection happens before a dialog and after an apparently accepted dialog', async t => {
  let valid = false, confirmations = 0;
  const bridge = await openChildApprovals({ validate() { if (!valid) throw new Error('changed'); },
    confirm() { confirmations++; valid = false; return true; } });
  t.after(bridge.close);
  assert.equal(await requestChildApproval(bridge.environment, decision()), false);
  assert.equal(confirmations, 0);
  valid = true;
  assert.equal(await requestChildApproval(bridge.environment, decision()), false);
  assert.equal(confirmations, 1);
});

test('malformed command or role never opens a dialog', async t => {
  let confirmations = 0;
  const bridge = await openChildApprovals({validate() {}, confirm() { confirmations++; return true; }});
  t.after(bridge.close);
  for (const change of [{command:'x'.repeat(16385)}, {command:'\0'}, {cwd:'relative'}, {role:'build'}, {task:'other'}, {outcome:'ordinary'}])
    assert.equal(await requestChildApproval(bridge.environment, {...decision(), ...change}), false);
  assert.equal(confirmations, 0);
});

test('client cancellation dismisses the active dialog and refuses a late decision', async t => {
  const entered = deferred(), cancelled = deferred(), answer = deferred();
  const bridge = await openChildApprovals({validate() {}, confirm(_request, signal) {
    signal.addEventListener('abort', () => cancelled.resolve(), {once:true}); entered.resolve(); return answer.promise;
  }});
  t.after(bridge.close);
  const controller = new AbortController();
  const pending = requestChildApproval(bridge.environment, decision(), {signal:controller.signal});
  await entered.promise; controller.abort();
  assert.equal(await pending, false);
  await cancelled.promise; answer.resolve(true);
});

test('parent cancellation closes pending clients and makes its endpoint unusable', async () => {
  const controller = new AbortController(), entered = deferred(), answer = deferred();
  const bridge = await openChildApprovals({signal:controller.signal, validate() {}, confirm() { entered.resolve(); return answer.promise; }});
  const pending = requestChildApproval(bridge.environment, decision());
  await entered.promise; controller.abort();
  assert.equal(await pending, false);
  answer.resolve(true);
  assert.equal(await requestChildApproval(bridge.environment, decision()), false);
});

test('timeout refuses and cancels the outstanding dialog', async t => {
  const cancelled = deferred();
  const bridge = await openChildApprovals({timeoutMs:100, validate() {}, confirm(_request, signal) {
    return new Promise(resolve => signal.addEventListener('abort', () => {cancelled.resolve(); resolve(true);}, {once:true}));
  }});
  t.after(bridge.close);
  assert.equal(await requestChildApproval(bridge.environment, decision()), false);
  await cancelled.promise;
});

test('shared dialog queue serializes children without sharing decisions', async t => {
  const queue = new CommandApprovals(), entered = deferred(), answer = deferred();
  let count = 0;
  const bridge = await openChildApprovals({validate() {}, confirm: (_request, signal, validate) => queue.run(async () => {
    count++; if (count === 1) {entered.resolve(); return answer.promise;} return false;
  }, {signal, validate})});
  t.after(bridge.close);
  const first = requestChildApproval(bridge.environment, decision());
  await entered.promise;
  const second = requestChildApproval(bridge.environment, decision());
  answer.resolve(true);
  assert.deepEqual(await Promise.all([first, second]), [true, false]);
  assert.equal(count, 2);
});

test('a mismatched or nonboolean response cannot approve a different command', async t => {
  for (const response of [{id:'wrong', approved:true}, {approved:'true'}]) {
    const server = createServer(socket => socket.on('data', chunk => {
      const {request} = JSON.parse(chunk.toString());
      socket.end(JSON.stringify({id:request.id, digest:'wrong', ...response}) + '\n');
    }));
    await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
    t.after(() => server.close());
    assert.equal(await requestChildApproval(JSON.stringify({port:server.address().port, token:'0'.repeat(64)}), decision()), false);
  }
});

test('oversized or multiple frames are disconnected without a dialog', async t => {
  let count = 0;
  const bridge = await openChildApprovals({validate() {}, confirm() {count++; return true;}});
  t.after(bridge.close);
  const endpoint = JSON.parse(bridge.environment);
  for (const data of ['x'.repeat(65537), '{}\n{}\n']) {
    await new Promise(resolve => {
      const socket = createConnection({host:'127.0.0.1',port:endpoint.port});
      socket.on('connect', () => socket.write(data)); socket.on('error', () => {}); socket.on('close', resolve);
    });
  }
  assert.equal(count, 0);
});
