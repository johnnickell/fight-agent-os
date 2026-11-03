import test from 'node:test';
import assert from 'node:assert/strict';
import { randomUUID } from 'node:crypto';
import { retainChild } from '../src/observation/custodian.mjs';
import { limits } from '../src/observation/custody.mjs';

// Custody, progress and immutable identity validation belong to the owning routes.
// These checks exercise their shared bounded post-admission history policy.
const child = (order, values = {}) => ({ id: randomUUID(), generation: randomUUID(), order,
  lifecycle: 'running', state: 'connected', ...values });

test('twelve sequential monitors reuse terminal disconnected history without raising the eight-child bound', () => {
  const children = new Map();
  for (let order = 1; order <= 12; order++) {
    const next = child(order);
    retainChild(children, next);
    assert.equal(children.get(next.id), next);
    assert.ok(children.size <= limits.children);
    next.lifecycle = order === 3 ? 'failed' : 'completed'; next.state = 'disconnected';
  }
  assert.deepEqual([...children.values()].map(value => value.order), [5, 6, 7, 8, 9, 10, 11, 12]);
});

test('history pressure preserves selected and pinned generations and evicts the oldest eligible reader only', () => {
  const values = Array.from({ length: 8 }, (_, index) => child(index + 1, { lifecycle: 'completed', state: 'disconnected' }));
  const children = new Map(values.map(value => [value.id, value]));
  const retained = values.slice(0, 2).map(value => `${value.generation}:${value.id}`);
  const next = child(9); retainChild(children, next, retained);
  assert.ok(children.has(values[0].id)); assert.ok(children.has(values[1].id));
  assert.equal(children.has(values[2].id), false); assert.equal(children.size, 8);
  assert.equal(children.get(next.id), next);
});

for (const [lifecycle, state] of [['running', 'connected'], ['running', 'disconnected'],
  ['starting', 'disconnected'], ['settled-awaiting-exit', 'disconnected'], ['completed', 'connected'], ['failed', 'connected']]) {
  test(`${lifecycle}/${state} monitor cannot be evicted to hide a capacity failure`, () => {
    const values = Array.from({ length: 8 }, (_, index) => child(index + 1, { lifecycle, state }));
    const children = new Map(values.map(value => [value.id, value]));
    assert.throws(() => retainChild(children, child(9)), /Observation capacity reached/);
    assert.deepEqual([...children.values()], values);
  });
}

test('all retained terminal readers still refuse overflow; preference text cannot create membership', () => {
  const values = Array.from({ length: 8 }, (_, index) => child(index + 1, { lifecycle: 'failed', state: 'disconnected' }));
  const children = new Map(values.map(value => [value.id, value]));
  const retained = values.map(value => `${value.generation}:${value.id}`);
  assert.throws(() => retainChild(children, child(9), retained), /Observation capacity reached/);
  assert.deepEqual([...children.values()], values);
  retainChild(children, child(9), [`${randomUUID()}:${values[0].id}`, 'forged']);
  assert.equal(children.has(values[0].id), false); assert.equal(children.size, 8);
  assert.equal(children.has('forged'), false);
});

test('mixed SDK and monitor terminal history uses one bound without retiring a still-connected monitor', () => {
  const values = Array.from({ length: 8 }, (_, index) => child(index + 1));
  values[0].lifecycle = 'completed'; // Still-connected terminal monitor remains protected
  Object.assign(values[1], { source: 'pi-subagents', lifecycle: 'completed' });
  Object.assign(values[2], { lifecycle: 'failed', state: 'disconnected' });
  const children = new Map(values.map(value => [value.id, value]));
  retainChild(children, child(9));
  assert.ok(children.has(values[0].id)); assert.equal(children.has(values[1].id), false);
  retainChild(children, child(10, { source: 'pi-subagents' }));
  assert.equal(children.has(values[2].id), false); assert.equal(children.size, 8);
  assert.throws(() => retainChild(children, child(11)), /Observation capacity reached/);
});
