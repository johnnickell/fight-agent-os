import { existsSync, lstatSync, readFileSync, readdirSync, realpathSync } from 'node:fs';
import { dirname, join, relative, resolve, sep } from 'node:path';
import { Unavailable } from './readiness.mjs';

export const within = (path, root) => path === root || path.startsWith(root + sep);
const fail = () => { throw new Unavailable('repository-metadata-unavailable'); };
const identity = path => {
  const info = lstatSync(path);
  if (info.isSymbolicLink() || realpathSync.native(path) !== path) fail();
  return `${info.dev}:${info.ino}`;
};
const text = path => {
  const info = lstatSync(path);
  if (!info.isFile() || info.nlink !== 1 || info.size > 4096) fail();
  const before = identity(path), value = readFileSync(path, 'utf8').trim();
  if (identity(path) !== before || !value || /[\x00-\x1f\x7f]/.test(value)) fail();
  return value;
};
const pointer = root => {
  const marker = join(root, '.git'), info = lstatSync(marker);
  identity(marker);
  if (info.isDirectory()) return { common: marker, gitdir: marker, base: root };
  const match = /^gitdir: (.+)$/.exec(text(marker));
  if (!match) fail();
  const gitdir = resolve(root, match[1]); identity(gitdir);
  const common = resolve(gitdir, text(join(gitdir, 'commondir'))); identity(common);
  if (dirname(gitdir) !== join(common, 'worktrees') ||
      resolve(gitdir, text(join(gitdir, 'gitdir'))) !== marker ||
      !lstatSync(common).isDirectory() || !lstatSync(gitdir).isDirectory()) fail();
  const base = dirname(common);
  if (join(base, '.git') !== common || !lstatSync(common).isDirectory()) fail();
  return { common, gitdir, base };
};

/** Resolve only registered reciprocal Git identities beneath an enrolled repository's common directory */
export class RepositoryAccess {
  #identities = new Map();
  #anchor;
  constructor(root) { this.root = root; }
  claims(cwd) {
    const common = this.#anchor ?? join(this.root, '.git');
    for (let cursor = cwd; ; cursor = dirname(cursor)) {
      const marker = join(cursor, '.git');
      if (existsSync(marker)) {
        try {
          if (lstatSync(marker).isFile()) {
            const match = /^gitdir: (.+)$/.exec(text(marker));
            return Boolean(match && within(resolve(cursor, match[1]), join(common, 'worktrees')));
          }
        } catch { return within(cursor, this.root); }
        return within(cursor, this.root);
      }
      if (cursor === dirname(cursor)) return false;
    }
  }
  #pin(key, paths) {
    const current = paths.map(identity).join('|'), previous = this.#identities.get(key);
    if (previous !== undefined && previous !== current) fail();
    this.#identities.set(key, current);
  }
  scope() {
    if (!existsSync(this.root)) return null;
    if (realpathSync.native(this.root) !== this.root || !lstatSync(this.root).isDirectory()) fail();
    this.#pin('enrolled-root', [this.root]);
    // A non-Git enrolled directory keeps its exact original scope. It never discovers sibling roots.
    if (!existsSync(join(this.root, '.git'))) {
      if (this.#anchor) fail();
      return { roots: [this.root], base: this.root, excludedPaths: [] };
    }
    const anchor = pointer(this.root);
    if (this.#anchor && this.#anchor !== anchor.common) fail();
    this.#anchor = anchor.common; this.#pin('common', [anchor.common]);
    const roots = [];
    const accept = root => {
      const member = pointer(root);
      if (member.common !== anchor.common) fail();
      this.#pin(member.gitdir, [root, join(root, '.git'), member.gitdir]);
      roots.push(root);
    };
    accept(anchor.base);
    const registrations = join(anchor.common, 'worktrees');
    if (existsSync(registrations)) {
      identity(registrations);
      const entries = readdirSync(registrations, { withFileTypes: true });
      if (entries.length > 128) fail();
      for (const entry of entries) {
        if (!entry.isDirectory() || entry.isSymbolicLink()) continue;
        try {
          const gitdir = join(registrations, entry.name);
          const marker = resolve(gitdir, text(join(gitdir, 'gitdir')));
          if (marker !== join(dirname(marker), '.git')) continue;
          const root = dirname(marker);
          if (pointer(root).gitdir !== gitdir) continue;
          accept(root);
        } catch { /* A stale/forged registration withdraws only that member, never another project */ }
      }
    }
    if (!roots.includes(this.root)) fail();
    return { roots: [...new Set(roots)], base: anchor.base, excludedPaths: [] };
  }
}

/** Do not let an enclosing enrolled checkout admit an unregistered nested repository */
export function assertRepositoryPath(path, roots) {
  const enclosing = roots.filter(root => within(path, root)).sort((a, b) => b.length - a.length)[0];
  if (!enclosing) return;
  for (let cursor = dirname(path); cursor !== enclosing && within(cursor, enclosing); cursor = dirname(cursor)) {
    if (existsSync(join(cursor, '.git')) && !roots.includes(cursor)) throw new Unavailable('repository-metadata-unavailable');
  }
}

/** Carry repository-relative exclusions to every member without widening unrelated absolute exclusions */
export function repositoryExclusions(project, roots) {
  return [...new Set(project.excludedPaths.flatMap(path => within(path, project.root)
    ? roots.map(root => join(root, relative(project.root, path))) : [path]))];
}
