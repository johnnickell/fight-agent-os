// Direct real-Docker qualification, not native Pi/Herdr or independent QA evidence.
// Run: node harness/pi/tests/native/late-runtime.mjs SELECTED_CHECKOUT OWNED_FIXTURE EVIDENCE
// Both paths must already be registered and prepared through the explicit native resource procedure.
// The fixture must initially be down. This probe operates only its three tooling services, never main.
// Full TLS startup and native interaction remain separately required. Keep all runtime records human-only.
import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { mkdirSync, realpathSync, writeFileSync } from 'node:fs';
import { resolve, join } from 'node:path';
import { DiscoveredLogs } from '../../src/herdr/discovered-logs.mjs';

const [selected, fixture, evidence] = process.argv.slice(2).map(value => realpathSync(value));
assert.ok(selected && fixture && evidence && selected !== fixture, 'Explicit distinct registered checkouts/evidence required');
mkdirSync(evidence, { recursive: true });
const logs = new DiscoveredLogs(), cases = [];
const runtime = (...args) => execFileSync(join(selected, 'bin/runtime'), ['--worktree', fixture, 'docker', 'compose', ...args], { cwd: selected, stdio: ['ignore', 'ignore', 'ignore'], timeout: 120000 });
const record = label => cases.push({ label, passed: true });
async function until(predicate, label, seconds = 60) {
  const deadline = Date.now() + seconds * 1000;
  while (Date.now() < deadline) {
    await logs.refresh();
    if (predicate()) { record(label); return; }
    await new Promise(done => setTimeout(done, 250));
  }
  throw Error(`Timed out: ${label}`);
}
const mixed = () => new Set((logs.current?.history ?? []).map(line => line.split('/')[0])).size >= 2;
let passed = false;
try {
  await logs.rebind(selected, { main: true });
  await until(() => logs.current?.state === 'following' && mixed(), 'main all-service mixed output without service picker');
  const main = logs.current.source;
  assert.ok(main.main === main.checkout.primary && main.members.length > 1, 'Main resolves through registered identity');
  await logs.rebind(selected);
  await until(() => logs.current?.state === 'following' && mixed(), 'selected worktree mixed output, including non-source-mounted database');
  const selectedReader = logs.current;
  await logs.refresh({ force: true });
  assert.ok(logs.current === selectedReader, 'A second unrelated registration cannot steal this verified source'); record('unrelated worktree registration cannot steal selection');
  await logs.action('select'); await logs.action('down'); await logs.action('mark'); await logs.action('choose');
  assert.ok(logs.current.source.members.length < logs.current.source.services.length, 'Inspected service subset applies'); record('service subset');
  await logs.rebind(fixture);
  assert.ok(logs.current === null, 'Missing active fixture must not fall back to main'); record('active no-stack unavailable');
  runtime('up', '-d', '--wait', 'web', 'postgres', 'postgres_test');
  await until(() => logs.current?.state === 'following' && mixed(), 'late fixture startup automatically follows multiple services');
  assert.ok(logs.filterLabel() === 'All', 'Stack switch resets filters'); record('stack switch resets All');
  await logs.action('pause'); const previous = logs.current;
  runtime('up', '-d', '--force-recreate', 'web');
  await until(() => logs.current && logs.current !== previous && logs.current.state === 'following', 'container replacement freshly verifies complete stack');
  assert.ok(previous.history.length === 0 && previous.frozen === null && previous.processes.size === 0, 'Previous live/frozen data and processes must be gone');
  runtime('stop', 'postgres');
  await until(() => logs.current?.source.stopped.includes('postgres'), 'stopped service is disclosed while remaining verified stack follows');
  runtime('start', 'postgres');
  await until(() => logs.current?.state === 'following' && !logs.current.source.stopped.length, 'restart freshly verifies service-set/lifecycle');
  await logs.action('toggle'); const count = logs.readers.length;
  await logs.refresh({ force: true }); assert.ok(!logs.current.open && logs.readers.length === count, 'Manual close survives discovery'); record('manual close persists');
  await logs.action('reconnect'); await until(() => logs.current?.state === 'following', 'explicit recovery');
  await logs.rebind(selected, { main: true });
  assert.ok(logs.current.source.main === main.main, 'Return to verified main'); record('return to main');
  passed = true;
} finally {
  await logs.close();
  // Caller owns SAME public bin/down and guarded Worktrunk removal after this display exits.
  writeFileSync(join(resolve(evidence), 'summary.json'), JSON.stringify({ result: passed ? 'passed' : 'failed', selected, fixture, cases,
    trackedReadersAfterClose: logs.readers.reduce((sum, reader) => sum + reader.processes.size, 0),
    runtimeContentDisclosed: false, limits: 'Tooling stacks, not full TLS startup, native Herdr or independent acceptance' }, null, 2) + '\n');
}
console.log(JSON.stringify({ result: passed ? 'passed' : 'failed', cases: cases.length, runtimeContentDisclosed: false }));
