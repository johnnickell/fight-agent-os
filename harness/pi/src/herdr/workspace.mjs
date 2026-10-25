import { execFile } from 'node:child_process';
import { promisify } from 'node:util';
import { createHash, randomUUID } from 'node:crypto';
import { mkdir, readFile, writeFile, rename, stat } from 'node:fs/promises';
import { resolve, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import net from 'node:net';
import { NativeWorkspaceContext } from './context.mjs';
import { testBindings } from '../workspace/worktree-logs.mjs';
import { runtimeSelection } from '../workspace/runtime-logs.mjs';

const execute = promisify(execFile);
const pause = (ms) => new Promise((done) => setTimeout(done, ms));
const quote = (text) => `'${text.replaceAll("'", "'\\''")}'`;
const labels = ['Child activity — observation only', 'Runtime logs — human only'];
const logCompanion = fileURLToPath(new URL('./logs-terminal.mjs', import.meta.url));
const activityCompanion = fileURLToPath(new URL('./activity-terminal.mjs', import.meta.url));

/** Prove a tracked reader is still a direct child, not a recycled/foreign foreground PID */
async function directChild(pid, parent) {
  try {
    const { stdout } = await execute('ps', ['-p', String(pid), '-o', 'ppid='], { timeout: 1000, maxBuffer: 128 });
    return stdout.trim() === String(parent);
  } catch { return false; }
}

/** Run bounded, explicit Herdr operations without inheriting a focused target */
export class HerdrClient {
  constructor(env = process.env) { this.env = { ...env }; }
  async call(...args) {
    try {
      const { stdout } = await execute('herdr', args, { env: this.env, timeout: 2500, maxBuffer: 1024 * 1024 });
      // `pane run` acknowledges admission with an empty successful stdout in the qualified CLI.
      if (!stdout.trim() && args[0] === 'pane' && args[1] === 'run') return {};
      const response = JSON.parse(stdout);
      if (!response.result) throw new Error('invalid-response');
      return response.result;
    } catch (error) {
      let code;
      try { code = JSON.parse(error.stderr || error.stdout).error.code; } catch { /* Opaque transport failure */ }
      throw new Error(code === 'pane_not_found' ? 'pane-not-found' : 'Herdr request failed or outcome uncertain');
    }
  }
}

/** Prove a live companion, not just an old PID or a saved pane handle */
export async function probeCompanion(file, token) {
  let receipt;
  try { receipt = JSON.parse(await readFile(file, 'utf8')); } catch { return undefined; }
  if (receipt.token !== token || !Number.isInteger(receipt.port) || receipt.port < 1 || receipt.port > 65535) return undefined;
  return new Promise((done) => {
    let bytes = '';
    const socket = net.createConnection({ host: '127.0.0.1', port: receipt.port });
    const finish = (value) => { socket.destroy(); done(value); };
    socket.setTimeout(300, () => finish(undefined));
    socket.on('error', () => finish(undefined));
    socket.on('connect', () => socket.write(`${token}\n`));
    socket.on('data', (chunk) => {
      bytes += chunk;
      if (bytes.length > 2048) return finish(undefined);
      if (!bytes.includes('\n')) return;
      try {
        const live = JSON.parse(bytes);
        finish(live.token === token && live.pid === receipt.pid && live.paneId === receipt.paneId ? { ...live, port: receipt.port } : undefined);
      } catch { finish(undefined); }
    });
    socket.on('end', () => finish(undefined));
  });
}

/** Own only companions created around one actual native Pi process */
export class HerdrWorkspace {
  constructor({ cwd, sessionId, sessionFile, pid = process.pid, env = process.env, client, probe = probeCompanion, childOf = directChild, currentContext, selectLogs = async () => ({ tests: [], shared: null }) }) {
    this.context = { cwd: resolve(cwd), sessionId, sessionFile: sessionFile || null, pid };
    this.currentContext = currentContext || (() => this.context);
    this.selectLogs = selectLogs;
    this.env = { ...env };
    this.client = client || new HerdrClient(this.env);
    this.probe = probe;
    this.busy = false;
    this.childOf = childOf;
  }

  async primary(cleanup = false) {
    if (this.stopping && !cleanup) throw new Error('Workspace is shutting down; no new mutations');
    if (this.env.HERDR_ENV !== '1' || !this.env.HERDR_SOCKET_PATH || !this.env.HERDR_PANE_ID || !this.env.HERDR_TAB_ID || !this.env.HERDR_WORKSPACE_ID) {
      throw new Error('Requires native Pi inside Herdr with complete caller context; ordinary Pi remains available');
    }
    if (!this.context.sessionId) throw new Error('Native session identity unavailable');
    const socket = await stat(this.env.HERDR_SOCKET_PATH);
    if (!socket.isSocket()) throw new Error('Herdr socket unavailable');
    const { pane } = await this.client.call('pane', 'current', '--current');
    const { process_info: info } = await this.client.call('pane', 'process-info', '--current');
    if (pane?.pane_id !== this.env.HERDR_PANE_ID || pane.tab_id !== this.env.HERDR_TAB_ID || pane.workspace_id !== this.env.HERDR_WORKSPACE_ID || !pane.terminal_id || pane.foreground_cwd && resolve(pane.foreground_cwd) !== this.context.cwd || !info?.foreground_processes?.some((p) => p.pid === this.context.pid)) {
      throw new Error('Caller is stale, moved or not the foreground native Pi process; no layout changes');
    }
    const identity = { ...this.context, paneId: pane.pane_id, terminalId: pane.terminal_id, tabId: pane.tab_id, workspaceId: pane.workspace_id, socket: this.env.HERDR_SOCKET_PATH, device: socket.dev, inode: socket.ino };
    if (this.identity && JSON.stringify(identity) !== JSON.stringify(this.identity)) throw new Error('Primary identity changed; companions retained without mutation');
    this.identity = identity;
    return pane;
  }

  async save() {
    const temp = `${this.file}.tmp`;
    await writeFile(temp, JSON.stringify(this.state, null, 2) + '\n', { mode: 0o600 });
    await rename(temp, this.file);
  }

  async initialize() {
    await this.primary();
    if (this.state) return;
    // The schema is capability evidence, not a version equality gate.
    await this.client.call('pane', 'layout', '--current');
    const key = createHash('sha256').update(JSON.stringify(this.identity)).digest('hex').slice(0, 24);
    this.directory = join(this.context.cwd, 'var', 'herdr-workspace');
    await mkdir(this.directory, { recursive: true, mode: 0o700 });
    this.file = join(this.directory, `${key}.json`);
    let candidate;
    try { candidate = JSON.parse(await readFile(this.file, 'utf8')); }
    catch (error) { if (error.code !== 'ENOENT') throw new Error('Ownership state unreadable; no layout changes'); }
    if (candidate && (JSON.stringify(candidate.primary) !== JSON.stringify(this.identity) || !Array.isArray(candidate.panes) || candidate.panes.length > 2)) {
      throw new Error('Ownership state mismatched; no layout changes');
    }
    // Only admitted ownership may be cached, saved or used by failure/shutdown cleanup.
    this.state = candidate || { primary: this.identity, panes: [], uncertain: false };
    await this.save();
  }

  async inspect(owned) {
    let pane;
    try { ({ pane } = await this.client.call('pane', 'get', owned.paneId)); }
    catch (error) { if (error.message === 'pane-not-found') return 'missing'; throw error; }
    if (pane.pane_id === this.identity.paneId || pane.terminal_id !== owned.terminalId || pane.tab_id !== this.identity.tabId || pane.workspace_id !== this.identity.workspaceId || pane.agent) return 'ambiguous';
    const { process_info: info } = await this.client.call('pane', 'process-info', '--pane', owned.paneId);
    const live = await this.probe(owned.receipt, owned.token);
    if (live?.paneId === owned.paneId) {
      const processes = info?.foreground_processes ?? [];
      const owner = processes.filter(process => process.pid === live.pid);
      if (owner.length === 1 && processes.length === 1) return 'healthy';
      // Herdr includes the fixed Docker reader in the companion's foreground group.
      // Admit only the PID tracked by that live log companion AND proven to be its direct child.
      const reader = processes.find(process => process.pid !== live.pid);
      if (this.state.panes[1] === owned && owner.length === 1 && processes.length === 2 &&
          info.shell_pid === live.pid && info.foreground_process_group_id === live.pid &&
          Array.isArray(live.readerPids) && live.readerPids.length === 1 &&
          Number.isSafeInteger(reader?.pid) && reader.pid > 0 && reader.name === 'docker' &&
          live.readerPids[0] === reader.pid && await this.childOf(reader.pid, live.pid)) {
        const current = await this.probe(owned.receipt, owned.token);
        if (current?.pid === live.pid && current.paneId === owned.paneId && current.readerPids?.length === 1 && current.readerPids[0] === reader.pid) return 'healthy';
      }
    }
    if (owned.phase === 'shell' && owned.shellPid && info?.shell_pid === owned.shellPid) {
      const shell = info.foreground_processes?.find((p) => p.pid === owned.shellPid && /^(?:-?bash|-?zsh|fish|sh)$/.test(p.name));
      if (shell && (info.foreground_process_group_id === undefined || info.foreground_process_group_id === owned.shellPid)) {
        if (info.foreground_processes.length === 1) return 'shell';
        // Prompt startup can briefly run jobs in the same shell group. Waiting grants no input/cleanup authority.
        if (info.foreground_process_group_id === owned.shellPid) return 'pending-shell';
      }
    }
    return 'ambiguous';
  }

  async waitForShell(owned) {
    const deadline = Date.now() + 2500;
    let ready = 0;
    do {
      await this.primary();
      const status = await this.inspect(owned);
      if (status !== 'shell' && status !== 'pending-shell') throw new Error('Companion changed before startup; no input submitted');
      ready = status === 'shell' ? ready + 1 : 0;
      if (ready === 3) return;
      await pause(100);
    } while (Date.now() < deadline);
    throw new Error('New shell is not ready; no input submitted');
  }

  invalidate() { this.activityContext?.close(); this.activityContext = undefined; }

  async observationHealthy() {
    try {
      // Process ownership alone cannot prove a usable native/observer lifetime.
      const context = this.activityContext.snapshot();
      const health = await Promise.all(this.state.panes.map(owned => this.probe(owned.receipt, owned.token)));
      return health.length === 2 && health.every(live => live?.generation === context.generation && live.invalidated === false);
    } catch { return false; }
  }

  async closeOwned() {
    this.invalidate();
    for (const owned of [...this.state.panes].reverse()) {
      await this.primary(true);
      const status = await this.inspect(owned);
      if (!['missing', 'healthy', 'shell'].includes(status)) throw new Error('Companion ownership/process changed; retained without input or cleanup');
      if (status !== 'missing') await this.client.call('pane', 'close', owned.paneId);
      this.state.panes = this.state.panes.filter((p) => p !== owned);
      await this.save();
    }
  }

  async create(index, target) {
    await this.primary();
    if (index) {
      const parent = this.state.panes.find((p) => p.paneId === target);
      if (!parent || await this.inspect(parent) !== 'healthy') throw new Error('Split target is no longer a live owned companion');
    }
    const token = randomUUID();
    // Persist uncertainty BEFORE mutation. Lost replies must never cause a blind split retry.
    this.state.uncertain = true;
    await this.save();
    // Disposable companions do not need the operator's zsh prompt hooks. Those hooks can
    // start foreground jobs during initialization; never weaken the process guard for them.
    const shellDirectory = join(this.directory, 'shell');
    await mkdir(shellDirectory, { recursive: true, mode: 0o700 });
    const { pane } = await this.client.call('pane', 'split', target, '--direction', index ? 'down' : 'right', '--ratio', '0.5', '--cwd', this.context.cwd, '--env', `ZDOTDIR=${shellDirectory}`, '--no-focus');
    if (!pane?.pane_id || !pane.terminal_id || pane.pane_id === this.identity.paneId || pane.tab_id !== this.identity.tabId || pane.workspace_id !== this.identity.workspaceId || this.state.panes.some((p) => p.paneId === pane.pane_id)) throw new Error('Split returned ambiguous identity; inspect actual topology before recovery');
    const owned = { paneId: pane.pane_id, terminalId: pane.terminal_id, token, receipt: join(this.directory, `${token}.json`), phase: 'shell' };
    this.state.panes.push(owned);
    this.state.uncertain = false;
    await this.save();
    const { process_info: info } = await this.client.call('pane', 'process-info', '--pane', owned.paneId);
    owned.shellPid = info?.shell_pid;
    await this.save();
    if (!owned.shellPid) throw new Error('New shell is not ready; no input submitted');
    await this.waitForShell(owned);
    this.state.uncertain = false;
    await this.save();
    await this.primary();
    await this.client.call('pane', 'rename', owned.paneId, labels[index]);
    // The pane is a freshly returned, verified foreground shell. No primary input is sent.
    await this.primary();
    await this.waitForShell(owned);
    owned.phase = 'starting';
    await this.save();
    let command;
    if (index === 0) {
      this.activityContext = new NativeWorkspaceContext(this.context, this.currentContext);
      const binding = await this.activityContext.start();
      const bindingFile = join(this.directory, `${binding.generation}.context.json`);
      this.activityContext.publish(bindingFile, binding);
      owned.bindingFile = bindingFile;
      await this.save();
      command = ['exec', quote(process.execPath), quote(activityCompanion), quote(owned.receipt), quote(token), quote(bindingFile)].join(' ');
    } else {
      const child = this.state.panes[0];
      if (await this.inspect(child) !== 'healthy') throw Error('Child companion ownership lost before log startup');
      const endpoint = await this.probe(child.receipt, child.token);
      if (!Number.isInteger(endpoint?.port) || endpoint.generation !== this.activityContext.generation) throw Error('Child selection endpoint unavailable');
      this.activityContext.logs = { ...this.logConfiguration, companion: { port: endpoint.port, token: child.token } };
      command = ['exec', quote(process.execPath), quote(logCompanion), quote(owned.receipt), quote(token), quote(child.bindingFile)].join(' ');
    }
    await this.client.call('pane', 'run', owned.paneId, command);
    for (let attempt = 0; attempt < 12; attempt++) {
      if (await this.inspect(owned) === 'healthy') {
        owned.phase = 'running';
        await this.save();
        return owned.paneId;
      }
      await pause(100);
    }
    throw new Error('Companion startup unconfirmed; no automatic command replay');
  }

  async shutdown() {
    this.invalidate();
    this.stopping = true;
    for (let attempt = 0; this.busy && attempt < 80; attempt++) await pause(100);
    if (this.busy) throw new Error('Workspace operation still settling; cleanup incomplete');
    if (this.state) await this.closeOwned();
  }

  async act(action) {
    if (this.busy) throw new Error('Workspace operation already in progress');
    this.busy = true;
    try {
      await this.initialize();
      if (this.state.uncertain) throw new Error('Previous split outcome uncertain; inspect saved IDs and owned topology before recovery. No retry performed');
      if (action === 'close') {
        await this.closeOwned();
        return 'Owned companions closed; native Pi unchanged';
      }
      const states = await Promise.all(this.state.panes.map((p) => this.inspect(p)));
      if (states.some((status) => !['missing', 'healthy', 'shell'].includes(status))) throw new Error('Saved companion is no longer proven-owned; no layout changes');
      if (states.length === 2 && states.every((s) => s === 'healthy') && await this.observationHealthy()) return 'Owned companions already open; native Pi unchanged';
      const { layout } = await this.client.call('pane', 'layout', '--current');
      const allowed = new Set([this.identity.paneId, ...this.state.panes.map((p) => p.paneId)]);
      if (!layout?.panes?.length || layout.panes.some((p) => !allowed.has(p.pane_id)) || layout.zoomed) throw new Error('Requires an unzoomed tab containing only this Pi and its owned companions; unrelated layout unchanged');
      // Never reload saved source preferences. Every new open generation requires fresh
      // interactive declarations (or an explicit no-logs choice), not model command text.
      const configuration = await this.selectLogs();
      if (!configuration) throw Error('Workspace open cancelled; no new panes');
      this.logConfiguration = { tests: testBindings(configuration.tests), shared: runtimeSelection(configuration.shared) };
      await this.primary();
      await this.closeOwned();
      const right = await this.create(0, this.identity.paneId);
      await this.create(1, right);
      return 'Owned child and human-only verified log companions open; Herdr owns focus, resize and zoom';
    } catch (error) {
      // Partial failures clean only admitted, live-proven companions. Lost split replies retain the uncertainty hold.
      if (this.state) {
        try { await this.closeOwned(); } catch { /* Preserve ambiguous effects for explicit recovery */ }
      }
      throw new Error(`${error.message}. Ordinary Pi is unchanged. Known companions retained: ${this.state?.panes.length || 0}; uncertain split: ${this.state?.uncertain ? 'yes' : 'no'}. Ownership state: ${this.file || 'not created'}`);
    } finally { this.busy = false; }
  }
}
