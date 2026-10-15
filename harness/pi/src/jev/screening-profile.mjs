import { createHash, randomUUID } from 'node:crypto';
import { existsSync, lstatSync, realpathSync } from 'node:fs';
import { dirname, isAbsolute, join, resolve, sep } from 'node:path';
import { operatorDirectory, privateDirectory, readPrivate, writePrivate } from './local-store.mjs';
import { configurationIdentity, reservationMicroUsd, Unavailable, validateConfiguration } from './readiness.mjs';
import { FileSource, evidenceByteLimit } from './file-screening.mjs';
import { canonicalPath } from '../tool-policy/policy.mjs';

export const profileVersion = 'screening-profile-v1';
export const hash = value => createHash('sha256').update(value).digest('hex');
const fail = code => { throw new Unavailable(code); };
const within = (path, root) => path === root || path.startsWith(root + sep);
const exact = (v, keys) => v && typeof v === 'object' && !Array.isArray(v) && Object.keys(v).sort().join() === [...keys].sort().join();
const path = v => typeof v === 'string' && isAbsolute(v) && v.length <= 4096 && !/[\x00-\x1f\x7f]/.test(v);
const list = (v, check, max = 128) => Array.isArray(v) && v.length <= max && v.every(check);
const bounded = v => Number.isSafeInteger(v) && v >= reservationMicroUsd;

/** Keep enrollment separate from connection setup, repository instructions and model-authored requests */
export function validateProfile(v, now = Date.now()) {
  if (!exact(v, ['version', 'revision', 'configuration', 'projects', 'excludedNames', 'providerDisclosure', 'expiresAt', 'requestMicroUsd', 'sessionMicroUsd']) ||
    v.version !== profileVersion || !/^[a-f0-9-]{36}$/.test(v.revision) || !/^[a-f0-9]{64}$/.test(v.configuration) ||
    !list(v.projects, p => exact(p, ['root', 'excludedPaths', 'instructions', 'targets']) && path(p.root) && p.root !== sep &&
      list(p.excludedPaths, path, 48) && list(p.instructions, i => exact(i, ['path', 'digest']) && path(i.path) && /^[a-f0-9]{64}$/.test(i.digest), 48) &&
      list(p.targets, t => exact(t, ['path', 'digest', 'purpose', 'offset', 'limit']) && path(t.path) && /^[a-f0-9]{64}$/.test(t.digest) && typeof t.purpose === 'string' && t.purpose.trim().length > 0 &&
        Buffer.byteLength(t.purpose) <= 512 && Number.isSafeInteger(t.offset) && t.offset > 0 && Number.isSafeInteger(t.limit) && t.limit > 0 && t.limit <= 1000, 48), 16) || !v.projects.length ||
    !list(v.excludedNames, n => typeof n === 'string' && n.length > 0 && n.length <= 128 && !/[\/\\\0]/.test(n)) ||
    typeof v.providerDisclosure !== 'boolean' || !Number.isSafeInteger(v.expiresAt) || v.expiresAt <= now ||
    !bounded(v.requestMicroUsd) || !bounded(v.sessionMicroUsd) || v.requestMicroUsd > v.sessionMicroUsd) fail('invalid-or-expired-profile');
  return structuredClone(v);
}

export class ScreeningProfiles {
  constructor(directory = operatorDirectory()) { this.directory = directory; this.path = join(directory, 'screening-profile.json'); }
  load() {
    if (!existsSync(this.directory)) return null;
    privateDirectory(this.directory);
    return readPrivate(this.path, null);
  }
  selection(cwd) {
    const value = this.load();
    if (value === null) return null;
    // A corrupt/expired enrolled file is a held profile, never an implicit opt-out.
    const profile = validateProfile(value);
    const canonical = realpathSync.native(cwd);
    const project = profile.projects.filter(p => within(canonical, p.root)).sort((a, b) => b.root.length - a.root.length)[0];
    if (!project) return null;
    for (const p of profile.projects) {
      if (realpathSync.native(p.root) !== p.root || !lstatSync(p.root).isDirectory()) fail('profile-root-changed');
      if (p.excludedPaths.some(path => canonicalPath(path) !== path)) fail('profile-exclusion-changed');
    }
    return { profile, project, identity: hash(JSON.stringify(profile)), enforcementPath: join(this.directory, `enforcement-${profile.revision}.json`) };
  }
  fileSettings(sessionId, cwd) {
    return { load: () => {
      const selected = this.selection(cwd);
      if (!selected) return null;
      const { profile, project } = selected;
      return { version: 'file-screen-v1', revision: profile.revision, configuration: profile.configuration,
        allowedRoots: [project.root], excludedPaths: [...project.excludedPaths, this.directory], excludedNames: profile.excludedNames,
        sessions: { [hash(sessionId)]: { requestMicroUsd: profile.requestMicroUsd, sessionMicroUsd: profile.sessionMicroUsd,
          expiresAt: profile.expiresAt, providerDisclosure: profile.providerDisclosure } } };
    } };
  }
  /** Prepare a visible candidate without storing authority or resolving credentials */
  preview({ cwd, configuration, excludedPaths = [], instructions = [], targets = [], providerDisclosure, days = 30 }) {
    const config = validateConfiguration(configuration);
    const root = realpathSync.native(resolve(cwd));
    if (root === sep || within(this.directory, root) || !Number.isSafeInteger(days) || days < 1 || days > 90) fail('invalid-profile-location');
    const previous = this.load();
    if (previous) validateProfile(previous, 0); // An operator can renew expiry, never erase a malformed enrollment.
    const policy = { allowedRoots: [root], excludedPaths: [...excludedPaths.map(p => canonicalPath(resolve(root, p))),
      ...['settings.json', 'models.json', 'mcp.json'].map(name => canonicalPath(join(root, '.pi', name))), this.directory], excludedNames: ['.env', 'auth.json'] };
    const source = new FileSource();
    const project = { root, excludedPaths: policy.excludedPaths.filter(p => p !== this.directory),
      // Exact provider instructions may live outside the target source root. This grants only that
      // instruction's main-Agent read, never a source root or Jev disclosure for its siblings.
      instructions: instructions.map(p => { const path = resolve(root, p);
        const s = source.snapshot(path, { ...policy, allowedRoots: [dirname(path)] }, { sourceLimit: evidenceByteLimit }); return { path: s.canonical, digest: s.digest }; }),
      targets: targets.map(t => { const s = source.snapshot(resolve(root, t.path), policy, { sourceLimit: evidenceByteLimit }); return { ...t, path: s.canonical, digest: s.digest }; }) };
    const candidate = { version: profileVersion, revision: randomUUID(), configuration: configurationIdentity(config),
      projects: [...(previous?.projects ?? []).filter(p => p.root !== root), project], excludedNames: ['.env', 'auth.json'],
      providerDisclosure: providerDisclosure === true, expiresAt: Date.now() + days * 86400000,
      requestMicroUsd: Math.min(config.requestMicroUsd, reservationMicroUsd), sessionMicroUsd: Math.min(config.sessionMicroUsd, 100000) };
    return validateProfile(candidate);
  }
  addWitness(cwd, kind, input) {
    const current = this.selection(cwd);
    if (!current || !['instruction', 'target'].includes(kind)) fail('profile-unavailable');
    const policy = this.fileSettings('operator-witness', cwd).load();
    const path = resolve(cwd, input.path);
    const snapshot = new FileSource().snapshot(path, kind === 'instruction' ? { ...policy, allowedRoots: [dirname(path)] } : policy, { sourceLimit: evidenceByteLimit });
    const project = current.project;
    if (kind === 'instruction') project.instructions = [...project.instructions.filter(i => i.path !== snapshot.canonical), { path: snapshot.canonical, digest: snapshot.digest }];
    else project.targets = [...project.targets.filter(t => t.path !== snapshot.canonical), { path: snapshot.canonical, digest: snapshot.digest,
      purpose: input.purpose, offset: input.offset, limit: input.limit }];
    current.profile.revision = randomUUID();
    return validateProfile(current.profile);
  }
  /** Ingest a trusted host/operator assignment; this API is deliberately not an Agent tool */
  intake(cwd, { instructions = [], assignments = [], references = [] }) {
    const current = this.selection(cwd);
    if (!current || !Array.isArray(instructions) || !Array.isArray(assignments) || !Array.isArray(references) ||
      instructions.length + assignments.length + references.length > 48) fail('invalid-trusted-intake');
    const policy = this.fileSettings('trusted-intake', cwd).load(), source = new FileSource();
    const snapshot = (p, instruction = false) => {
      const resolved = resolve(cwd, p);
      const external = instruction && current.project.instructions.some(i => i.path === resolved);
      return source.snapshot(resolved, external ? { ...policy, allowedRoots: [dirname(resolved)] } : policy, { sourceLimit: evidenceByteLimit });
    };
    const held = instructions.map(p => { const s = snapshot(p, true); return { path: s.canonical, digest: s.digest }; });
    const target = (t, evidence) => {
      if (!t || typeof t.purpose !== 'string' || !t.purpose.trim() || Buffer.byteLength(t.purpose) > 512 ||
        !Number.isSafeInteger(t.offset) || t.offset < 1 || !Number.isSafeInteger(t.limit) || t.limit < 1 || t.limit > 1000) fail('invalid-trusted-intake');
      const s = snapshot(t.path);
      if (t.offset + t.limit - 1 > s.text.split('\n').length) fail('range-outside-source');
      return { path: s.canonical, digest: s.digest, purpose: t.purpose, offset: t.offset, limit: t.limit, evidence };
    };
    const targets = assignments.map(t => {
      if (typeof t.reference !== 'string' || !/^[A-Za-z0-9._:/-]{1,128}$/.test(t.reference)) fail('assignment-reference-required');
      return target(t, { kind: 'trusted-assignment', reference: t.reference });
    });
    for (const t of references) {
      const s = snapshot(t.from, true);
      if (![...held, ...current.project.instructions].some(i => i.path === s.canonical && i.digest === s.digest)) fail('unestablished-instruction');
      const wanted = resolve(cwd, t.path);
      // A trusted host selects applicability/range. The source must actually name the exact reference.
      const links = [...s.text.matchAll(/\[[^\]\n]+\]\(([^)\s]+)\)/g)].map(m => m[1].split('#')[0]);
      if (!links.some(link => link && !/^[a-z]+:/i.test(link) && resolve(dirname(s.canonical), link) === wanted)) fail('required-reference-not-found');
      targets.push(target(t, { kind: 'required-reference', from: s.canonical, digest: s.digest }));
    }
    return { version: 1, authority: current.identity, cwd: realpathSync.native(cwd), instructions: held, targets };
  }
  save(candidate, resources) {
    const profile = validateProfile(candidate);
    privateDirectory(this.directory);
    const guard = { version: 1, profile: 'file-screening', revision: 1,
      roots: profile.projects.map(p => p.root), protectedPaths: [this.directory, ...resources,
        ...profile.projects.flatMap(p => [...p.excludedPaths, ...p.instructions.map(i => i.path)])],
      tools: ['read', 'write', 'edit'], effects: ['read', 'write'], scripts: [] };
    writePrivate(join(this.directory, `enforcement-${profile.revision}.json`), guard);
    writePrivate(this.path, profile);
  }
}
