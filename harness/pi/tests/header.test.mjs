import assert from 'node:assert/strict';
import test from 'node:test';
import { displayMode, hasConversation, headerRows, modes, parseMode, renderHeader } from '../src/header.mjs';

const text = (_role, value) => value;

test('auto presents the welcome until work begins and compacts existing conversations', () => {
  assert.equal(displayMode('auto', false), 'port');
  assert.equal(displayMode('auto', true), 'compact');
  assert.equal(hasConversation([{ type: 'session' }, { type: 'model_change' }]), false);
  assert.equal(hasConversation([{ type: 'message', message: { role: 'user' } }]), true);
  assert.equal(hasConversation([{ type: 'message', message: { role: 'assistant' } }]), true);
});

test('explicit display choices persist when work begins', () => {
  for (const mode of modes.filter(value => value !== 'auto')) {
    assert.equal(displayMode(mode, false), mode);
    assert.equal(displayMode(mode, true), mode);
  }
});

test('invalid preferences are rejected rather than treated as a display mode', () => {
  for (const input of [undefined, null, '', 'bogus', true]) assert.equal(parseMode(input), undefined);
  assert.throws(() => headerRows('bogus', 80), RangeError);
});

test('rendering respects terminal widths across all modes, including tiny widths', () => {
  for (const mode of modes) {
    for (const width of [0, 1, 4, 14, 15, 39, 55, 56, 80, 160]) {
      for (const line of renderHeader(mode, width, text)) assert.ok([...line].length <= width);
    }
  }
  assert.deepEqual(headerRows('port', NaN), []);
});

test('narrow and plain terminals receive a readable ASCII identity', () => {
  assert.equal(renderHeader('port', 40, text).join(''), 'F FIGHT AGENT OS');
  assert.equal(renderHeader('port', 4, text).join(''), 'FIGH');
  for (const mode of modes.filter(value => value !== 'off')) {
    const output = renderHeader(mode, 80, () => { throw new Error('Plain rendering must not style'); }, true);
    assert.match(output.join(''), /^[\x20-\x7e]+$/);
  }
});

test('working identity occupies one row and off supplies no replacement', () => {
  assert.equal(renderHeader('compact', 80, text).length, 1);
  assert.deepEqual(renderHeader('off', 80, text), []);
  assert.match(renderHeader('port', 80, text).join('\n'), /F I G H T/);
  assert.match(renderHeader('wordmark', 80, text).join('\n'), /A G E N T   O S/);
});

test('semantic color spans are passed to the adapter without changing source rows', () => {
  const original = headerRows('port', 80);
  const seen = new Set();
  renderHeader('port', 80, (role, value) => { seen.add(role); return value; });
  assert.deepEqual([...seen].sort(), ['accent', 'dim', 'muted', 'text']);
  assert.deepEqual(headerRows('port', 80), original);
});
