import test from 'node:test';
import assert from 'node:assert/strict';
import { mkdtempSync, mkdirSync, realpathSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { LocalLogSources } from '../src/herdr/local-log-sources.mjs';

function fixture(t) {
  const root = realpathSync(mkdtempSync(join(tmpdir(), 'local-source-'))), common = join(root, '.git'); mkdirSync(common);
  t.after(() => rmSync(root, { recursive: true, force: true }));
  const endpoint = 'unix:///controlled.sock', calls = [];
  const row = { id: 'a'.repeat(64), image: `sha256:${'b'.repeat(64)}`, running: true, project: 'owned', service: 'web', directory: root,
    created: '2026-01-01T00:00:00Z', started: '2026-01-01T00:00:01Z', restarts: 0, mounts: [{ Type: 'bind', Source: root, Destination: '/app' }] };
  let rows = [row], host = endpoint, registered = true;
  const sources = new LocalLogSources({ endpointIdentity: async () => ({ path: '/controlled.sock', device: 1, inode: 2 }), query: async (command, args) => {
    calls.push({ command, args });
    if (command === 'git') {
      if (args.includes('--show-toplevel')) return root;
      if (args.includes('--git-common-dir')) return common;
      return registered ? `worktree ${root}\0` : 'worktree /unrelated\0';
    }
    if (args.includes('context') && args.includes('show')) return 'local';
    if (args.includes('context') && args.includes('inspect')) return JSON.stringify(host);
    if (args.includes('ls')) return rows.map(value => value.id).join('\n');
    return rows.map(value => JSON.stringify(value)).join('\n');
  } });
  t.after(() => sources.close());
  return { root, row, sources, calls, set: value => { rows = value; }, endpoint: value => { host = value; }, register: value => { registered = value; } };
}

test('exact canonical checkout, root bind and local endpoint are required before source admission', async t => {
  const f = fixture(t); const result = await f.sources.discover(f.root);
  assert.equal(result.choices.length, 1); const source = result.choices[0];
  await f.sources.verify(source, source.container, source.endpoint);
  for (const patch of [{ mounts: [] }, { mounts: [{ Type: 'volume', Source: f.root, Destination: '/app' }] }, { directory: '/unrelated' }, { running: false }]) {
    f.set([{ ...f.row, ...patch }]); assert.equal((await f.sources.discover(f.root)).choices.length, 0);
  }
  f.register(false); await assert.rejects(f.sources.discover(f.root), /registered worktree/);
  assert.ok(f.calls.every(call => !call.args.some(value => ['up', 'run', 'exec', 'start', 'stop', 'restart', 'compose', 'logs'].includes(value))));
});

test('replication and inventory overflow never produce an automatic source', async t => {
  const f = fixture(t);
  f.set([f.row, { ...f.row, id: 'c'.repeat(64) }]); assert.equal((await f.sources.discover(f.root)).choices.length, 0);
  f.set(Array.from({ length: 17 }, (_, index) => ({ ...f.row, id: index.toString(16).padStart(64, '0') })));
  await assert.rejects(f.sources.discover(f.root), /Too many/);
});

test('revalidation rejects lifecycle, mount, ID and endpoint changes before any log byte', async t => {
  const f = fixture(t); const { choices: [source] } = await f.sources.discover(f.root);
  for (const patch of [{ started: '2026-01-01T00:00:02Z' }, { restarts: 1 }, { mounts: [] }, { running: false }]) {
    f.set([{ ...f.row, ...patch }]); await assert.rejects(f.sources.verify(source, source.container, source.endpoint), /stopped, restarted/);
  }
  await assert.rejects(f.sources.verify(source, 'c'.repeat(64), source.endpoint), /replaced/);
  f.endpoint('tcp://remote:2375'); await assert.rejects(f.sources.discover(f.root), /not local/);
  await assert.rejects(f.sources.verify(source, source.container, source.endpoint), /endpoint association/);
});
