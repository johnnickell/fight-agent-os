import test from 'node:test';
import assert from 'node:assert/strict';
import { mkdtempSync, mkdirSync, realpathSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { LocalLogSources } from '../src/herdr/local-log-sources.mjs';

function fixture(t) {
  const root = realpathSync(mkdtempSync(join(tmpdir(), 'local-source-'))), common = join(root, '.git'), linked = join(root, 'linked');
  mkdirSync(common); mkdirSync(linked); t.after(() => rmSync(root, { recursive: true, force: true }));
  const endpoint = 'unix:///controlled.sock', calls = [], network = 'd'.repeat(64), peer = 'e'.repeat(64);
  const row = { id: 'a'.repeat(64), image: `sha256:${'b'.repeat(64)}`, running: true, project: 'owned', service: 'web', directory: root,
    files: join(root, 'compose.yaml'), hash: 'f'.repeat(64), oneoff: 'False',
    created: '2026-01-01T00:00:00Z', started: '2026-01-01T00:00:01Z', restarts: 0,
    mounts: [{ Type: 'bind', Source: root, Destination: '/app' }], networks: { default: { NetworkID: network, EndpointID: peer } } };
  let rows = [row], host = endpoint, registered = true, net = { id: network, project: 'owned', network: 'default', containers: {} };
  const sources = new LocalLogSources({ endpointIdentity: async () => ({ path: '/controlled.sock', device: 1, inode: 2 }), query: async (command, args) => {
    calls.push({ command, args });
    if (command === 'git') {
      if (args.includes('--show-toplevel')) return args[1] === linked ? linked : root;
      if (args.includes('--git-common-dir')) return common;
      return registered ? `worktree ${root}\0\0worktree ${linked}\0` : 'worktree /unrelated\0';
    }
    if (args.includes('context') && args.includes('show')) return 'local';
    if (args.includes('context') && args.includes('inspect')) return JSON.stringify(host);
    if (args.includes('network')) return JSON.stringify(net);
    if (args.includes('ls')) return rows.map(value => value.id).join('\n');
    return rows.map(value => JSON.stringify(value)).join('\n');
  } });
  const set = value => { rows = value; net.containers = Object.fromEntries(rows.filter(row => row.running).map(row => [row.id, { EndpointID: peer }])); };
  set(rows); t.after(() => sources.close());
  return { root, linked, row, sources, calls, set, net, endpoint: value => { host = value; }, register: value => { registered = value; } };
}

test('all services form one stack, including legitimate non-source-mounted services', async t => {
  const f = fixture(t);
  f.set([f.row, { ...f.row, id: 'c'.repeat(64), service: 'postgres', mounts: [{ Type: 'volume', Name: 'data', Destination: '/data' }] }]);
  const { choices: [stack] } = await f.sources.discover(f.root);
  assert.deepEqual(stack.services, ['postgres', 'web']); assert.equal(stack.members.length, 2);
  await f.sources.verify(stack);
  assert.ok(f.calls.every(call => !call.args.some(value => ['up', 'run', 'exec', 'start', 'stop', 'restart', 'compose', 'logs'].includes(value))));
});

test('idle main is resolved from native registration, not the linked checkout or newest worktree', async t => {
  const f = fixture(t); const result = await f.sources.discover(f.linked, { main: true });
  assert.equal(result.checkout.root, f.root); assert.equal(result.choices[0].main, f.root);
  f.register(false); await assert.rejects(f.sources.discover(f.root), /registered worktree/);
});

test('labels, matching names or foreign/shared network membership cannot replace a source anchor', async t => {
  const f = fixture(t);
  for (const patch of [{ mounts: [] }, { mounts: [{ Type: 'volume', Source: f.root, Destination: '/app' }] }, { directory: '/foreign' }, { files: '' }, { hash: null }]) {
    f.set([{ ...f.row, ...patch }]); await assert.rejects(f.sources.discover(f.root));
  }
  f.set([f.row, { ...f.row, id: 'c'.repeat(64), service: 'database', mounts: [], networks: {} }]);
  await assert.rejects(f.sources.discover(f.root), /association unproved/);
  f.set([f.row]); f.net.project = 'foreign'; await assert.rejects(f.sources.discover(f.root), /association unproved/);
  f.net.project = 'owned'; f.net.containers[f.row.id].EndpointID = 'f'.repeat(64);
  await assert.rejects(f.sources.discover(f.root), /association unproved/);
});

test('complete-project inventory refuses unproved members, and bounds all discovery', async t => {
  const f = fixture(t);
  f.set([f.row, { ...f.row, id: 'c'.repeat(64), service: 'foreign', directory: '/foreign' }]);
  await assert.rejects(f.sources.discover(f.root), /Complete project association/);
  f.set(Array.from({ length: 17 }, (_, index) => ({ ...f.row, id: index.toString(16).padStart(64, '0') })));
  await assert.rejects(f.sources.discover(f.root), /Too many/);
});

test('revalidation rejects lifecycle, service-set, mount, socket and endpoint changes before reading', async t => {
  const f = fixture(t); const { choices: [source] } = await f.sources.discover(f.root);
  for (const patch of [{ started: '2026-01-01T00:00:02Z' }, { restarts: 1 }, { mounts: [] }, { running: false }, { service: 'worker' }]) {
    f.set([{ ...f.row, ...patch }]); await assert.rejects(f.sources.verify(source));
  }
  f.set([f.row, { ...f.row, id: 'c'.repeat(64), service: 'worker', mounts: [] }]);
  await assert.rejects(f.sources.verify(source), /service set changed/);
  f.endpoint('tcp://remote:2375'); await assert.rejects(f.sources.discover(f.root), /not local/);
  await assert.rejects(f.sources.verify(source), /endpoint association/);
});

test('stopped services are disclosed while verified running services remain distinguishable', async t => {
  const f = fixture(t); f.set([f.row, { ...f.row, id: 'c'.repeat(64), running: false, service: 'database', mounts: [], networks: {} }]);
  const { choices: [source] } = await f.sources.discover(f.root);
  assert.equal(source.members.length, 1); assert.deepEqual(source.stopped, ['database']);
});
