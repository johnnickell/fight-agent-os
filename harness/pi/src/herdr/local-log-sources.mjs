import { execFile } from 'node:child_process';
import { promisify } from 'node:util';
import { realpath, stat } from 'node:fs/promises';
import { basename, isAbsolute } from 'node:path';
import { logLimits } from '../workspace/runtime-logs.mjs';

const execute = promisify(execFile);
const name = /^[a-zA-Z0-9][a-zA-Z0-9_.-]{0,127}$/;
const projectName = /^[a-z0-9][a-z0-9_-]{0,127}$/;
const id = /^[a-f0-9]{64}$/;
export const sourceLimit = logLimits.sources;
const environment = () => Object.fromEntries(['HOME', 'PATH', 'TMPDIR', 'LANG', 'LC_ALL'].filter(key => process.env[key]).map(key => [key, process.env[key]]));
// Inspect only association/lifecycle facts, never environments, commands or diagnostics.
const inspection = '{"id":{{json .Id}},"created":{{json .Created}},"started":{{json .State.StartedAt}},"restarts":{{json .RestartCount}},"running":{{json .State.Running}},"image":{{json .Image}},"project":{{json (index .Config.Labels "com.docker.compose.project")}},"service":{{json (index .Config.Labels "com.docker.compose.service")}},"directory":{{json (index .Config.Labels "com.docker.compose.project.working_dir")}},"files":{{json (index .Config.Labels "com.docker.compose.project.config_files")}},"hash":{{json (index .Config.Labels "com.docker.compose.config-hash")}},"oneoff":{{json (index .Config.Labels "com.docker.compose.oneoff")}},"mounts":{{json .Mounts}},"networks":{{json .NetworkSettings.Networks}}}';
const networkInspection = '{"id":{{json .Id}},"project":{{json (index .Labels "com.docker.compose.project")}},"network":{{json (index .Labels "com.docker.compose.network")}},"containers":{{json .Containers}}}';
const stable = value => JSON.stringify(value, (_key, item) => item && typeof item === 'object' && !Array.isArray(item) ? Object.fromEntries(Object.entries(item).sort(([a], [b]) => a.localeCompare(b))) : item);

/** Read bounded fixed metadata without evaluating project code or retaining diagnostics */
export async function metadata(command, args, processes) {
  try {
    const pending = execute(command, args, { cwd: '/', env: environment(), timeout: 1800, killSignal: 'SIGKILL', maxBuffer: 65536 });
    if (processes) { processes.add(pending.child); pending.child.once('close', () => processes.delete(pending.child)); }
    const { stdout } = await pending;
    return stdout;
  } catch {
    throw Error(command === 'git' ? 'Checkout metadata unavailable; restore this registered Git checkout. Discovery will recheck.' : 'Docker unavailable or access denied. Check local access; discovery will retry. Panes stay open.');
  }
}

/** Match the canonical registered checkout and its actual main checkout */
export async function checkoutIdentity(cwd, query = metadata) {
  const directory = await realpath(cwd);
  const root = (await query('git', ['-C', directory, 'rev-parse', '--show-toplevel'])).trim();
  if (!isAbsolute(root) || await realpath(root) !== root) throw Error('Checkout root is not canonical; no log source selected.');
  const common = await realpath((await query('git', ['-C', root, 'rev-parse', '--path-format=absolute', '--git-common-dir'])).trim());
  const records = (await query('git', ['-C', root, 'worktree', 'list', '--porcelain', '-z'])).split('\0');
  if (records.length > 1024 || !records.includes(`worktree ${root}`) || !records[0].startsWith('worktree ')) throw Error('Checkout is not a verified registered worktree; no logs selected.');
  const primary = records[0].slice(9);
  if (!isAbsolute(primary) || await realpath(primary) !== primary) throw Error('Main checkout identity unavailable.');
  const directoryInfo = await stat(root), commonInfo = await stat(common);
  return { root, primary, common, directory: [directoryInfo.dev, directoryInfo.ino], metadata: [commonInfo.dev, commonInfo.ino] };
}

async function unixEndpoint(endpoint) {
  if (typeof endpoint !== 'string' || !/^unix:\/\/\/[^\x00-\x1f\x7f]{1,990}$/.test(endpoint)) throw Error('Current Docker context is not local. Select a local context outside Pi; no remote fallback.');
  const path = await realpath(endpoint.slice(7)), info = await stat(path);
  if (!info.isSocket()) throw Error('Docker local socket unavailable.');
  return { path, device: info.dev, inode: info.ino };
}

/** Prove a complete Compose project through source anchors and actual project-network endpoints */
export class LocalLogSources {
  constructor({ query, endpointIdentity = unixEndpoint } = {}) {
    this.endpointIdentity = endpointIdentity; this.processes = new Set(); this.closed = false;
    this.query = async (command, args) => {
      if (this.closed) throw Error('Source discovery closed');
      return query ? query(command, args) : metadata(command, args, this.processes);
    };
  }
  async close() {
    this.closed = true;
    await Promise.all([...this.processes].map(child => new Promise(done => {
      const deadline = setTimeout(done, 2000);
      child.once('close', () => { clearTimeout(deadline); done(); }); child.kill('SIGKILL');
    })));
  }
  async list(endpoint, label) {
    const text = await this.query('docker', ['--host', endpoint, 'container', 'ls', '--all', '--no-trunc', '--filter', `label=${label}`, '--format', '{{.ID}}']);
    const ids = text.trim() ? text.trim().split('\n') : [];
    if (ids.length > sourceLimit || ids.some(value => !id.test(value)) || new Set(ids).size !== ids.length) throw Error('Too many or invalid matching containers (limit 16); source unavailable.');
    return ids;
  }
  async inspect(endpoint, ids, network = false) {
    const text = await this.query('docker', ['--host', endpoint, network ? 'network' : 'container', 'inspect', '--format', network ? networkInspection : inspection, ...ids]);
    const rows = text.trim() ? text.trim().split('\n').map(line => JSON.parse(line)) : [];
    if (rows.length !== ids.length || rows.some(row => !ids.includes(row.id)) || new Set(rows.map(row => row.id)).size !== ids.length) throw Error('Runtime metadata changed or exceeded bounds; retry discovery.');
    return rows.sort((a, b) => a.id.localeCompare(b.id));
  }
  async anchor(row, root) {
    for (const mount of row.mounts) {
      if (mount.Type !== 'bind' || !isAbsolute(mount.Source ?? '') || !isAbsolute(mount.Destination ?? '')) continue;
      try { if (await realpath(mount.Source) === root) return true; } catch { /* Stale mounts grant nothing */ }
    }
    return false;
  }
  async stack(checkout, context, endpoint, socket, project) {
    // Query the whole project, not merely root-labelled candidates: foreign/missing members refuse it.
    const ids = await this.list(endpoint, `com.docker.compose.project=${project}`);
    if (!ids.length) throw Error('Selected stack unavailable; waiting for its runtime.');
    const rows = await this.inspect(endpoint, ids), files = rows[0].files;
    if (typeof files !== 'string' || !files.length || files.length > 8192 || files.split(',').length > 8 || files.split(',').some(path => !isAbsolute(path) || /[\x00-\x1f\x7f]/.test(path))) throw Error('Compose configuration association unavailable.');
    for (const row of rows) {
      if (!id.test(row.image?.replace(/^sha256:/, '')) || row.project !== project || row.directory !== checkout.root || row.files !== files ||
          !name.test(row.service) || !id.test(row.hash) || !['True', 'False'].includes(row.oneoff) || typeof row.running !== 'boolean' ||
          !Number.isFinite(Date.parse(row.created)) || !Number.isFinite(Date.parse(row.started)) || !Number.isSafeInteger(row.restarts) || row.restarts < 0 ||
          !Array.isArray(row.mounts) || row.mounts.length > 32 || !row.networks || Object.keys(row.networks).length > 8) throw Error('Complete project association unproved; no partial or foreign stack fallback.');
    }
    const running = rows.filter(row => row.running && row.oneoff === 'False');
    if (!running.length) throw Error('Stack stopped; observation never starts containers.');
    const networkIds = [...new Set(running.flatMap(row => Object.values(row.networks).map(net => net.NetworkID)))];
    if (!networkIds.length || networkIds.length > 16 || networkIds.some(value => !id.test(value))) throw Error('Runtime network association unavailable or over limit.');
    const networks = (await this.inspect(endpoint, networkIds, true)).filter(net => net.project === project && name.test(net.network));
    const attached = row => networks.filter(net => Object.values(row.networks).some(peer => peer.NetworkID === net.id && id.test(peer.EndpointID) && net.containers?.[row.id]?.EndpointID === peer.EndpointID)).map(net => net.id);
    const proved = new Set(), connected = new Set();
    for (const row of running) if (await this.anchor(row, checkout.root)) { proved.add(row.id); attached(row).forEach(net => connected.add(net)); }
    if (!proved.size) throw Error('No running canonical source anchor; stack unavailable.');
    // Networks must be owned by this project and reciprocally confirm actual container endpoints.
    // Names/labels alone never admit a database, catcher or gateway without a source mount.
    for (let pass = 0; pass < running.length; pass++) for (const row of running) {
      const links = attached(row);
      if (links.some(net => connected.has(net))) { proved.add(row.id); links.forEach(net => connected.add(net)); }
    }
    if (proved.size !== running.length || running.some(row => !attached(row).length)) throw Error('Project service network/source association unproved; stack unavailable.');
    const members = running.map(row => ({ service: row.service, container: row.id }));
    return Object.freeze({ main: checkout.root, context, project, endpoint, socket, checkout, members,
      services: [...new Set(members.map(row => row.service))].sort(),
      witness: stable(rows), stopped: rows.filter(row => !row.running && row.oneoff === 'False').map(row => row.service).sort(),
      label: `${basename(checkout.root)} · ${project} · ${members.length} containers` });
  }
  async discover(cwd, { main = false } = {}) {
    let checkout = await checkoutIdentity(cwd, this.query);
    if (main && checkout.root !== checkout.primary) checkout = await checkoutIdentity(checkout.primary, this.query);
    const context = (await this.query('docker', ['context', 'show'])).trim();
    if (!name.test(context)) throw Error('Docker context metadata unavailable.');
    const endpoint = JSON.parse(await this.query('docker', ['context', 'inspect', context, '--format', '{{json .Endpoints.docker.Host}}']));
    if (typeof endpoint !== 'string' || !/^unix:\/\/\/[^\x00-\x1f\x7f]{1,990}$/.test(endpoint)) throw Error('Current Docker context is not local; no remote fallback.');
    const socket = await this.endpointIdentity(endpoint);
    const ids = await this.list(endpoint, `com.docker.compose.project.working_dir=${checkout.root}`);
    const candidates = ids.length ? await this.inspect(endpoint, ids) : [];
    const projects = [...new Set(candidates.map(row => row.project))].sort();
    if (projects.length > 4 || projects.some(value => !projectName.test(value))) throw Error('Project ambiguity exceeds bounded discovery (four projects).');
    const choices = [], refused = [];
    for (const project of projects) {
      try { choices.push(await this.stack(checkout, context, endpoint, socket, project)); }
      catch (error) { refused.push(error.message); }
    }
    // An unproved candidate is genuine ambiguity, never silently choose its neighbour.
    if (refused.length) throw Error(refused[0]);
    return { checkout, choices, notice: choices.length ? 'Verified complete local stack; bounded recent tail/live output.' : 'Stack unavailable — no matching runtime. Start the intended stack separately; automatic discovery continues. No main fallback.' };
  }
  async verify(source) {
    const checkout = await checkoutIdentity(source.main, this.query);
    const host = JSON.parse(await this.query('docker', ['context', 'inspect', source.context, '--format', '{{json .Endpoints.docker.Host}}']));
    const socket = await this.endpointIdentity(host);
    if (stable(checkout) !== stable(source.checkout) || stable(socket) !== stable(source.socket) || host !== source.endpoint) throw Error('Checkout or local endpoint association changed; inspect a fresh stack.');
    const fresh = await this.stack(checkout, source.context, host, socket, source.project);
    if (fresh.witness !== source.witness) throw Error('Stack stopped, restarted, replaced or service set changed; fresh verification required.');
  }
}
