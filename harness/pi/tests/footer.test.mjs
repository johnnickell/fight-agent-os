import test from 'node:test';
import assert from 'node:assert/strict';
import { collectFooterUsage, footerStatusRows, footerTokens } from '../src/footer.mjs';

const statuses = () => new Map([
  ['fight-jev', 'Jev: configured'], ['codex-weekly-usage', 'Codex week: 73% left · resets tomorrow'],
  ['fight-file-screen', 'File screening: active'],
]);

test('Codex usage occupies the row immediately above the combined Fight statuses', () => {
  assert.deepEqual(footerStatusRows(statuses()), [
    'Codex week: 73% left · resets tomorrow', 'File screening: active · Jev: configured',
  ]);
});

test('other statuses are preserved and missing or cleared statuses leave no empty rows', () => {
  const values = statuses(); values.set('z-other', 'Last'); values.set('a-other', 'First');
  assert.equal(footerStatusRows(values)[0], 'First Last');
  values.delete('codex-weekly-usage'); values.delete('fight-file-screen');
  assert.deepEqual(footerStatusRows(values), ['First Last', 'Jev: configured']);
  assert.deepEqual(footerStatusRows(new Map()), []);
});

test('changes and errors remain visible and embedded newlines cannot reorder status rows', () => {
  const values = statuses(); values.set('fight-file-screen', 'File screening: degraded'); values.set('fight-jev', 'Jev: billing');
  assert.equal(footerStatusRows(values).at(-1), 'File screening: degraded · Jev: billing');
  values.set('codex-weekly-usage', 'Codex\nweek:\t unavailable\r');
  assert.equal(footerStatusRows(values)[0], 'Codex week: unavailable');
});

test('usage includes every billed entry form but not user/custom metadata or absent summary usage', () => {
  const usage = {input: 5, output: 3, cacheRead: 15, cacheWrite: 0, cost: {total: 0.01}};
  const entries = [
    {type: 'message', message: {role: 'assistant', usage}},
    {type: 'message', message: {role: 'toolResult', usage}},
    {type: 'usage', usage}, {type: 'compaction', usage}, {type: 'branch_summary', usage},
    {type: 'message', message: {role: 'user', usage}}, {type: 'custom', usage},
    {type: 'message', message: {role: 'toolResult'}}, {type: 'compaction'},
  ];
  assert.deepEqual(collectFooterUsage(entries), {input: 25, output: 15, cacheRead: 75, cacheWrite: 0, cost: 0.05, cacheHitRate: 75});
  entries.push({type: 'message', message: {role: 'assistant', usage: {...usage, input: 0, cacheRead: 0}}});
  assert.equal(collectFooterUsage(entries).cacheHitRate, undefined);
});

test('token units match compact footer boundaries', () => {
  assert.deepEqual([0, 999, 1000, 9999, 10000, 128000, 1000000, 10000000].map(footerTokens),
    ['0', '999', '1.0k', '10.0k', '10k', '128k', '1.0M', '10M']);
});
