import { createHash } from 'node:crypto';
import { lstatSync, realpathSync, readFileSync, statSync, mkdirSync, mkdtempSync, rmSync, statfsSync } from 'node:fs';
import { dirname, isAbsolute, join, resolve, sep } from 'node:path';
import { supportsRuntime } from './runtime.mjs';

/** @type {readonly ['read', 'write', 'edit', 'bash']} */
export const supportedTools = Object.freeze(['read', 'write', 'edit', 'bash']);
const effects = ['read', 'write', 'execute'];
const digest = (bytes) => createHash('sha256').update(bytes).digest('hex');
const within = (path, root) => path === root || path.startsWith(root + sep);
const fail = (code) => { throw new PolicyDenied(code); };
const object = (value) => value !== null && typeof value === 'object' && !Array.isArray(value);
const exactKeys = (value, keys) => object(value) && Object.keys(value).sort().join() === [...keys].sort().join();
const list = (value, check, max = 64) => Array.isArray(value) && value.length <= max && value.every(check);
const token = (value) => typeof value === 'string' && /^[A-Za-z0-9_./-]{1,256}$/.test(value);

/** Keep policy failures independent of untrusted paths, arguments and exception bodies */
export class PolicyDenied extends Error {
  constructor(code) { super(`Fight policy blocked: ${code}`); this.code = code; }
}

/** Ask the selected volume about names, including names whose real targets do not exist */
class PathNames {
  #path; #identity; #names = new Map();
  constructor(directory, paths) {
    // Darwin APFS has volume-wide lookup semantics. Unknown filesystems/devices fail closed;
    // Linux is used only by the owning tests, never a supported production runtime.
    if (process.platform === 'darwin' && statfsSync(directory).type !== 26) fail('unsupported-filesystem');
    const info = lstatSync(directory);
    if (!info.isDirectory() || (info.mode & 0o077) !== 0 || info.uid !== process.getuid()) fail('unsafe-policy-directory');
    this.#path = mkdtempSync(join(directory, '.fight-policy-names-'));
    this.#identity = lstatSync(this.#path);
    try {
      const names = new Set(['.git', '.pi', 'skills', '.ssh', '.aws', '.gnupg', 'auth.json', '.env', '.env.example',
        ...paths.flatMap((path) => path.split(sep).filter(Boolean))]);
      for (const name of names) {
        const bucket = join(this.#path, String(this.#names.size));
        mkdirSync(bucket, { mode: 0o700 });
        mkdirSync(join(bucket, name), { mode: 0o700 });
        this.#names.set(name, { bucket, path: join(bucket, name), identity: lstatSync(join(bucket, name)).ino });
      }
    } catch (error) { this.close(); throw error; }
  }
  check(path) {
    let ancestor = path;
    while (true) {
      try {
        if (statSync(ancestor).dev !== this.#identity.dev) fail('unsupported-filesystem');
        break;
      } catch (error) {
        if (error.code !== 'ENOENT') throw error;
        ancestor = dirname(ancestor);
      }
    }
    const info = lstatSync(this.#path);
    if (!info.isDirectory() || (info.mode & 0o077) !== 0 || info.uid !== process.getuid() ||
        info.dev !== this.#identity.dev || info.ino !== this.#identity.ino) fail('filesystem-probe-changed');
  }
  equal(value, reference) {
    if (value === reference) return true;
    const entry = this.#names.get(reference);
    if (!entry) fail('filesystem-probe-unavailable');
    let referenceInfo;
    try { referenceInfo = lstatSync(entry.path); } catch { fail('filesystem-probe-unavailable'); }
    if (!referenceInfo.isDirectory() || referenceInfo.dev !== this.#identity.dev ||
        referenceInfo.ino !== entry.identity) fail('filesystem-probe-changed');
    try {
      const info = lstatSync(join(entry.bucket, value));
      return info.dev === referenceInfo.dev && info.ino === referenceInfo.ino;
    } catch (error) { if (error.code === 'ENOENT') return false; fail('filesystem-probe-unavailable'); }
  }
  within(path, root) {
    const parts = path.split(sep).filter(Boolean), roots = root.split(sep).filter(Boolean);
    return roots.length <= parts.length && roots.every((part, index) => this.equal(parts[index], part));
  }
  reserved(path, projectFiles = false) {
    return path.split(sep).filter(Boolean).some((part, index, parts) => {
      const dot = part.indexOf('.', 1);
      const envName = dot < 0 ? part : part.slice(0, dot);
      return ['.git', '.ssh', '.aws', '.gnupg', 'auth.json'].some((name) => this.equal(part, name)) ||
        (this.equal(part, '.pi') && !(projectFiles && parts[index + 1] && this.equal(parts[index + 1], 'skills'))) ||
        (this.equal(envName, '.env') && !(projectFiles && this.equal(part, '.env.example')));
    });
  }
  close() { rmSync(this.#path, { recursive: true, force: true }); }
}

/** Resolve existing ancestors and deliberately reject dangling links and special files */
export function canonicalPath(value, cwd = '/') {
  if (typeof value !== 'string' || !value || value.length > 4096 || value.startsWith('@') || /[\x00-\x1f\x7f\\%~]/.test(value)) fail('invalid-path');
  const path = resolve(cwd, value);
  const parts = path.split(sep).filter(Boolean);
  let current = sep;
  for (let i = 0; i < parts.length; i++) {
    current = join(current, parts[i]);
    let info;
    try { info = lstatSync(current); }
    catch (error) {
      if (error.code !== 'ENOENT') fail('path-unavailable');
      return join(current, ...parts.slice(i + 1));
    }
    // On Darwin the JS realpath implementation preserves input case/normalization aliases.
    // Native realpath returns the filesystem's stored spelling of each existing ancestor.
    try { current = realpathSync.native(current); } catch { fail('dangling-link'); }
    const target = info.isSymbolicLink() ? statSync(current) : info;
    if (!target.isFile() && !target.isDirectory()) fail('special-file');
    if (target.isFile() && target.nlink !== 1) fail('hard-link');
    if (i < parts.length - 1 && !target.isDirectory()) fail('invalid-parent');
  }
  return current;
}

function fileIdentity(path, privateFile = false) {
  try {
    const info = lstatSync(path);
    if (!info.isFile() || info.nlink !== 1 || info.size > 65536 ||
        (privateFile && ((info.mode & 0o077) !== 0 || info.uid !== process.getuid()))) fail('unsafe-policy-file');
    return digest(readFileSync(path));
  } catch (error) {
    if (error instanceof PolicyDenied) throw error;
    fail('resource-unavailable');
  }
}

function repositoryRoot(cwd) {
  for (let dir = cwd; ; dir = dirname(dir)) {
    try { lstatSync(join(dir, '.git')); return dir; } catch { /* No Git marker here */ }
    if (dirname(dir) === dir) return cwd;
  }
}

/** Validate a deliberately small versioned operator contract, never a shell allowlist */
function loadPolicy(path, cwd, resources, workflowAccess) {
  const identity = fileIdentity(path, true);
  let policy;
  try { policy = JSON.parse(readFileSync(path, 'utf8')); } catch { fail('invalid-policy'); }
  if (!exactKeys(policy, ['version', 'profile', 'revision', 'roots', 'protectedPaths', 'tools', 'effects', 'scripts']) ||
      policy.version !== 1 || !/^[a-z0-9-]{1,48}$/.test(policy.profile) ||
      !Number.isSafeInteger(policy.revision) || policy.revision < 1 ||
      !list(policy.tools, (tool) => supportedTools.includes(tool), 4) || !policy.tools.length ||
      new Set(policy.tools).size !== policy.tools.length ||
      !list(policy.effects, (effect) => effects.includes(effect), 3) ||
      !list(policy.roots, (root) => typeof root === 'string' && isAbsolute(root), 16) || !policy.roots.length ||
      !list(policy.protectedPaths, (root) => typeof root === 'string' && isAbsolute(root)) ||
      !Array.isArray(policy.scripts) || policy.scripts.length > 32) fail('invalid-policy');
  const roots = policy.roots.map((root) => {
    const canonical = canonicalPath(root);
    if (!statSync(canonical).isDirectory() || canonical === sep) fail('invalid-root');
    return { configured: root, canonical };
  });
  if (within(path, repositoryRoot(cwd)) || roots.some(({ canonical }) => within(path, canonical))) fail('policy-in-workspace');
  if (workflowAccess && (policy.profile !== 'file-screening' || !roots.some(r => r.canonical === workflowAccess.root) ||
      !Array.isArray(workflowAccess.editableInstructions) || typeof workflowAccess.authorizeWrite !== 'function')) fail('invalid-workflow-access');
  // These exact paths were automatically protected by older enrollment writers. The trusted
  // consumer excludes explicit operator exclusions from this list; runtime resources remain protected.
  const protectedPaths = [path, dirname(path), ...resources,
    ...policy.protectedPaths.filter(root => !workflowAccess?.editableInstructions.includes(root)),
    ...roots.flatMap(({ canonical }) => workflowAccess ? [join(canonical, '.git')] : [join(canonical, '.git'), join(canonical, '.pi')])]
    .map((root) => ({ configured: root, canonical: canonicalPath(root) }));
  const scripts = policy.scripts.map((script) => {
    if (!exactKeys(script, ['path', 'sha256', 'cwd', 'args', 'effects']) || !isAbsolute(script.path) ||
        !token(script.path) || !/^[a-f0-9]{64}$/.test(script.sha256) || !isAbsolute(script.cwd) ||
        !list(script.args, (args) => list(args, token, 16), 32) || !script.args.length ||
        !list(script.effects, (effect) => effects.includes(effect), 3) || !script.effects.includes('execute')) fail('invalid-script');
    const canonical = canonicalPath(script.path);
    const workdir = canonicalPath(script.cwd);
    if (roots.some((root) => within(canonical, root.canonical)) ||
        !roots.some((root) => within(workdir, root.canonical)) || !statSync(workdir).isDirectory() ||
        fileIdentity(canonical) !== script.sha256 || !(statSync(canonical).mode & 0o111) ||
        !readFileSync(canonical, 'utf8').startsWith('#!')) fail('unsafe-script');
    protectedPaths.push({ configured: script.path, canonical });
    return { ...script, path: canonical, cwd: workdir };
  });
  return { ...policy, roots, protectedPaths, scripts, identity };
}

/** Own fresh admission and cancellation; no reusable allow token escapes this module */
export class ToolPolicy {
  #path; #cwd; #resources; #sources; #policy; #names; #session; #workflowAccess; #epoch = 0; #pending = new Set(); #reason = 'not-started';
  /** @param {{policyPath: string, cwd: string, resources?: string[], sources?: string[], workflowAccess?: {root: string, editableInstructions: string[], authorizeWrite: (path: string) => void}}} options */
  constructor({ policyPath, cwd, resources = [], sources = [], workflowAccess }) {
    this.#path = policyPath;
    this.#cwd = realpathSync.native(cwd);
    this.#resources = resources;
    this.#workflowAccess = workflowAccess;
    this.#sources = sources.map((path) => ({ path, identity: fileIdentity(path) }));
  }
  start(session, runtime) {
    this.cancel('session-changed');
    try {
      if (!supportsRuntime(runtime)) fail('unsupported-runtime-or-hooks');
      if (!isAbsolute(this.#path) || canonicalPath(this.#path) !== this.#path) fail('invalid-policy-location');
      this.#policy = loadPolicy(this.#path, this.#cwd, this.#resources, this.#workflowAccess);
      const paths = [...this.#policy.roots, ...this.#policy.protectedPaths]
        .flatMap(({ configured, canonical }) => [resolve(configured), canonical]);
      this.#names = new PathNames(dirname(this.#path), paths);
      for (const root of this.#policy.roots) this.#names.check(root.canonical);
      this.#names.check(this.#cwd);
      this.#session = session;
      this.#reason = undefined;
    } catch (error) {
      this.#names?.close(); this.#names = undefined;
      this.#reason = error instanceof PolicyDenied ? error.code : 'invalid-policy';
    }
    return this.inspect();
  }
  cancel(reason = 'cancelled') {
    this.#epoch++;
    this.#reason = reason;
    for (const controller of this.#pending) controller.abort();
    this.#pending.clear();
    this.#names?.close(); this.#names = undefined;
  }
  inspect() {
    if (!this.#reason) {
      try { this.#fresh(); } catch (error) { this.cancel(error instanceof PolicyDenied ? error.code : 'resource-unavailable'); }
    }
    return { state: this.#reason ? 'unavailable' : 'active', protection: 'deterministic protection only',
      profile: this.#policy?.profile, revision: this.#policy?.revision, reason: this.#reason,
      policySha256: this.#policy?.identity, tools: this.#policy?.tools ?? [], effects: this.#policy?.effects ?? [],
      roots: this.#policy?.roots.length ?? 0, protectedPaths: this.#policy?.protectedPaths.length ?? 0,
      scripts: this.#policy?.scripts.length ?? 0,
      coverage: 'wrapped read/write/edit/bash; all alternate/helper/nested/child tools denied; !/!! denied',
      limits: 'No Jev screening, filesystem/process isolation, subprocess containment or unload detection' };
  }
  #fresh() {
    if (this.#reason) fail(this.#reason);
    if (canonicalPath(this.#cwd) !== this.#cwd) fail('cwd-changed');
    this.#names.check(this.#cwd);
    if (fileIdentity(this.#path, true) !== this.#policy.identity) fail('policy-changed');
    for (const source of this.#sources) if (fileIdentity(source.path) !== source.identity) fail('guard-source-changed');
    for (const root of this.#policy.roots) if (canonicalPath(root.configured) !== root.canonical) fail('root-changed');
    for (const root of this.#policy.protectedPaths) if (canonicalPath(root.configured) !== root.canonical) fail('protected-resource-changed');
  }
  #live(operation) {
    if (operation.epoch !== this.#epoch || operation.session !== this.#session || operation.controller.signal.aborted) fail('stale-operation');
    try { this.#fresh(); }
    catch (error) { this.cancel(error instanceof PolicyDenied ? error.code : 'resource-unavailable'); throw error; }
  }
  /** Clone final arguments after all Pi hooks; validate again at each effect */
  begin(tool, input, session, cwd, signal) {
    if (session !== this.#session || realpathSync.native(cwd) !== this.#cwd) fail('session-or-cwd-changed');
    this.#fresh();
    if (!this.#policy.tools.includes(tool)) fail('disallowed-tool');
    if (!object(input)) fail('invalid-input');
    if (tool === 'write' && (typeof input.content !== 'string' || Buffer.byteLength(input.content) > 1048576)) fail('invalid-content');
    if (tool === 'read' && ['offset', 'limit'].some((key) => input[key] !== undefined &&
        (!Number.isSafeInteger(input[key]) || input[key] < 1))) fail('invalid-input');
    if (tool === 'edit' && (!Array.isArray(input.edits) || !input.edits.length || input.edits.length > 128 ||
        input.edits.some((edit) => !object(edit) || typeof edit.oldText !== 'string' || typeof edit.newText !== 'string') ||
        Buffer.byteLength(JSON.stringify(input.edits)) > 1048576)) fail('invalid-input');
    const controller = new AbortController();
    const abort = () => controller.abort();
    signal?.addEventListener('abort', abort, { once: true });
    if (signal?.aborted) abort();
    const operation = { tool, input: structuredClone(input), session, epoch: this.#epoch, controller,
      close: () => { signal?.removeEventListener('abort', abort); this.#pending.delete(controller); } };
    this.#pending.add(controller);
    try {
      if (tool === 'bash') this.command(operation);
      else {
        if (!object(operation.input) || typeof operation.input.path !== 'string') fail('invalid-input');
        operation.input.path = this.target(operation, operation.input.path);
      }
      return operation;
    } catch (error) { operation.close(); throw error; }
  }
  target(operation, path) {
    this.#live(operation);
    const effect = operation.tool === 'read' ? 'read' : 'write';
    if (!this.#policy.effects.includes(effect) ||
        (operation.tool === 'edit' && !this.#policy.effects.includes('read'))) fail('disallowed-effect');
    const canonical = canonicalPath(path, this.#cwd);
    const lexical = resolve(this.#cwd, path);
    this.#names.check(canonical);
    if (this.#names.reserved(lexical, Boolean(this.#workflowAccess)) || this.#names.reserved(canonical, Boolean(this.#workflowAccess))) fail('protected-path');
    if (!this.#policy.roots.some((root) => within(canonical, root.canonical))) fail('out-of-root');
    if (this.#policy.protectedPaths.some((root) => this.#names.within(canonical, root.canonical) ||
        this.#names.within(lexical, resolve(root.configured)))) fail('protected-path');
    if (effect === 'write') this.#workflowAccess?.authorizeWrite(canonical);
    // Never replace a root/ancestor directory as if it were a normal file.
    try {
      const info = statSync(canonical);
      if (!info.isFile()) fail('invalid-target-type');
      if (info.size > 1048576) fail('file-too-large');
    } catch (error) { if (error.code !== 'ENOENT') throw error; }
    return canonical;
  }
  command(operation) {
    this.#live(operation);
    const { command, timeout } = operation.input ?? {};
    if (typeof command !== 'string' || command.length > 4096 ||
        !/^[A-Za-z0-9_./-]+(?: [A-Za-z0-9_./-]+)*$/.test(command) ||
        (timeout !== undefined && (!Number.isFinite(timeout) || timeout <= 0 || timeout > 30))) fail('unrecognized-command');
    const [path, ...args] = command.split(' ');
    const script = this.#policy.scripts.find((script) => script.path === path && script.cwd === this.#cwd &&
      script.args.some((allowed) => JSON.stringify(allowed) === JSON.stringify(args)));
    if (!script) fail('unapproved-command-or-arguments');
    if (script.effects.some((effect) => !this.#policy.effects.includes(effect))) fail('disallowed-effect');
    if (canonicalPath(script.path) !== script.path || fileIdentity(script.path) !== script.sha256) fail('script-changed');
    return { path: script.path, args, cwd: this.#cwd, timeout: timeout ?? 30 };
  }
}
