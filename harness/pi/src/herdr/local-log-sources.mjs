import { execFile } from 'node:child_process';
import { promisify } from 'node:util';
import { realpath, stat } from 'node:fs/promises';
import { basename, isAbsolute } from 'node:path';

const execute = promisify(execFile);
const name = /^[a-zA-Z0-9][a-zA-Z0-9_.-]{0,127}$/;
const projectName = /^[a-z0-9][a-z0-9_-]{0,127}$/;
const id = /^[a-f0-9]{64}$/;
const environment = () => Object.fromEntries(['HOME', 'PATH', 'TMPDIR', 'LANG', 'LC_ALL'].filter(key => process.env[key]).map(key => [key, process.env[key]]));
// Deliberately exclude environment, commands and daemon diagnostics from inspected metadata.
const inspection = '{"id":{{json .Id}},"created":{{json .Created}},"started":{{json .State.StartedAt}},"restarts":{{json .RestartCount}},"running":{{json .State.Running}},"image":{{json .Image}},"project":{{json (index .Config.Labels "com.docker.compose.project")}},"service":{{json (index .Config.Labels "com.docker.compose.service")}},"directory":{{json (index .Config.Labels "com.docker.compose.project.working_dir")}},"mounts":{{json .Mounts}}}';

/** Read bounded fixed metadata without evaluating project code or retaining diagnostics */
export async function metadata(command, args, processes) {
  try {
    const pending = execute(command, args, { cwd: '/', env: environment(), timeout: 1800, killSignal: 'SIGKILL', maxBuffer: 65536 });
    if (processes) { processes.add(pending.child); pending.child.once('close', () => processes.delete(pending.child)); }
    const { stdout } = await pending;
    return stdout;
  } catch {
    throw Error(command === 'git' ? 'Checkout metadata unavailable; open Pi in a registered Git checkout, then press r to retry.' : 'Docker unavailable or access denied. Start Docker or check local access, then press r to retry. Panes stay open.');
  }
}

/** Match only the actual canonical Git checkout, including registered linked worktrees */
export async function checkoutIdentity(cwd, query = metadata) {
  const directory = await realpath(cwd);
  const root = (await query('git', ['-C', directory, 'rev-parse', '--show-toplevel'])).trim();
  if (!isAbsolute(root) || await realpath(root) !== root) throw Error('Checkout root is not canonical; no log source selected.');
  const common = await realpath((await query('git', ['-C', root, 'rev-parse', '--path-format=absolute', '--git-common-dir'])).trim());
  const records = (await query('git', ['-C', root, 'worktree', 'list', '--porcelain', '-z'])).split('\0');
  if (records.length > 1024 || !records.includes(`worktree ${root}`)) throw Error('Checkout is not a verified registered worktree; no logs selected.');
  const directoryInfo = await stat(root), commonInfo = await stat(common);
  return { root, common, directory: [directoryInfo.dev, directoryInfo.ino], metadata: [commonInfo.dev, commonInfo.ino] };
}

/** Pin the actual local socket, not a remote context or only an endpoint label */
async function unixEndpoint(endpoint) {
  if (typeof endpoint !== 'string' || !/^unix:\/\/\/[^\x00-\x1f\x7f]{1,990}$/.test(endpoint)) throw Error('Current Docker context is not local. Select a local Docker context outside Pi, then press r. No remote or shared fallback.');
  const path = await realpath(endpoint.slice(7)), info = await stat(path);
  if (!info.isSocket()) throw Error('Docker local socket unavailable; start Docker, then press r.');
  return { path, device: info.dev, inode: info.ino };
}

/** Discover existing exact-checkout sources under this explicit workspace invocation */
export class LocalLogSources {
  constructor({ query, endpointIdentity = unixEndpoint } = {}) {
    this.endpointIdentity = endpointIdentity;
    this.processes = new Set(); this.closed = false;
    this.query = async (command, args) => {
      if (this.closed) throw Error('Source discovery closed');
      return query ? query(command, args) : metadata(command, args, this.processes);
    };
  }
  async close() {
    this.closed = true;
    await Promise.all([...this.processes].map(child => new Promise(done => {
      const deadline = setTimeout(done, 2000);
      child.once('close', () => { clearTimeout(deadline); done(); });
      child.kill('SIGKILL');
    })));
  }
  async inspect(endpoint, ids) {
    const text = await this.query('docker', ['--host', endpoint, 'container', 'inspect', '--format', inspection, ...ids]);
    const rows = text.trim() ? text.trim().split('\n').map(line => JSON.parse(line)) : [];
    if (rows.length !== ids.length || rows.some(row => !ids.includes(row.id)) || new Set(rows.map(row => row.id)).size !== ids.length) throw Error('Container metadata changed or exceeded bounds; press s to inspect sources again.');
    return rows;
  }
  async associated(row, root) {
    if (!id.test(row.id) || !id.test(row.image?.replace(/^sha256:/, '')) || !row.running || !projectName.test(row.project) || !name.test(row.service) ||
        row.directory !== root || typeof row.created !== 'string' || !Number.isFinite(Date.parse(row.created)) || typeof row.started !== 'string' || !Number.isFinite(Date.parse(row.started)) || !Number.isSafeInteger(row.restarts) || row.restarts < 0 || !Array.isArray(row.mounts) || row.mounts.length > 32) return false;
    // A Compose label/name is not source ownership. Require a real canonical root bind.
    for (const mount of row.mounts) {
      if (mount.Type !== 'bind' || !isAbsolute(mount.Source ?? '') || !isAbsolute(mount.Destination ?? '')) continue;
      try { if (await realpath(mount.Source) === root) return true; } catch { /* Stale mount grants no association */ }
    }
    return false;
  }
  async discover(cwd) {
    const checkout = await checkoutIdentity(cwd, this.query);
    const context = (await this.query('docker', ['context', 'show'])).trim();
    if (!name.test(context)) throw Error('Docker context metadata unavailable; check Docker, then press r.');
    const endpoint = JSON.parse(await this.query('docker', ['context', 'inspect', context, '--format', '{{json .Endpoints.docker.Host}}']));
    if (typeof endpoint !== 'string' || !/^unix:\/\/\/[^\x00-\x1f\x7f]{1,990}$/.test(endpoint)) throw Error('Current Docker context is not local. Select a local Docker context outside Pi, then press r. No remote or shared fallback.');
    let socket;
    try { socket = await this.endpointIdentity(endpoint); }
    catch { throw Error('Docker local socket unavailable; start Docker or check access, then press r.'); }
    // Only exact-root labelled candidates, at most 16. Never enumerate arbitrary log streams.
    const text = await this.query('docker', ['--host', endpoint, 'container', 'ls', '--all', '--no-trunc', '--filter', `label=com.docker.compose.project.working_dir=${checkout.root}`, '--format', '{{.ID}}']);
    const ids = text.trim() ? text.trim().split('\n') : [];
    if (ids.length > 16 || ids.some(value => !id.test(value)) || new Set(ids).size !== ids.length) throw Error('Too many or invalid matching containers; narrow the local runtime inventory, then press r.');
    const rows = ids.length ? await this.inspect(endpoint, ids) : [];
    const choices = [];
    for (const row of rows) {
      // Replication/duplicate identity is ambiguous even if only one replica is running.
      if (rows.filter(other => other.project === row.project && other.service === row.service).length !== 1 || !await this.associated(row, checkout.root)) continue;
      choices.push(Object.freeze({ main: checkout.root, context, project: row.project, service: row.service, purpose: 'test',
        allowedServices: [row.service], endpoint, socket, checkout, witness: JSON.stringify(row), container: row.id,
        label: `${row.service} / ${row.project} · ${basename(checkout.root)} · ${row.id.slice(0, 12)}` }));
    }
    return { checkout, choices: choices.sort((a, b) => a.label.localeCompare(b.label)), notice: choices.length ? 'Only verified existing local sources are listed.' : 'No matching running runtime with verified checkout mounts. Start your intended runtime separately, then press r. No shared fallback.' };
  }
  async verify(source, container, endpoint) {
    if (container !== source.container || endpoint !== source.endpoint) throw Error('Source replaced or endpoint changed; press s to inspect and choose a fresh source.');
    const checkout = await checkoutIdentity(source.main, this.query);
    const host = JSON.parse(await this.query('docker', ['context', 'inspect', source.context, '--format', '{{json .Endpoints.docker.Host}}']));
    const socket = await this.endpointIdentity(host);
    if (JSON.stringify(checkout) !== JSON.stringify(source.checkout) || JSON.stringify(socket) !== JSON.stringify(source.socket) || host !== source.endpoint) throw Error('Checkout or local endpoint association changed; press s to inspect sources.');
    const [row] = await this.inspect(source.endpoint, [source.container]);
    if (!await this.associated(row, source.main) || JSON.stringify(row) !== source.witness) throw Error('Source stopped, restarted, replaced or mounts changed; press s to inspect and choose a fresh source.');
  }
}
