import { homedir } from 'node:os';
import { resolve } from 'node:path';
import { FileSource, imageByteLimit } from './file-screening.mjs';
import { Unavailable } from './readiness.mjs';

const fail = code => { throw new Unavailable(code); };

/** Normalize a literal image path without evaluating shell syntax */
function imagePath(value, cwd) {
  if (typeof value !== 'string' || value.length > 4096 || /[\x00-\x1f\x7f]/.test(value)) return null;
  let path = value.trim();
  if (['"', "'", '`'].includes(path[0]) && path.at(-1) === path[0]) path = path.slice(1, -1);
  path = path.replace(/\\ /g, ' ');
  if (!/\.(png|jpe?g|gif|webp)$/i.test(path)) return null;
  if (path.startsWith('~/')) path = resolve(homedir(), path.slice(2));
  return resolve(cwd, path);
}

/** Route supported image paths independently of text relevance receipts */
export function isImagePath(value, cwd) { return imagePath(value, cwd) !== null; }

function mimeType(data) {
  if (data.subarray(0, 8).equals(Buffer.from([137, 80, 78, 71, 13, 10, 26, 10]))) return 'image/png';
  if (data[0] === 255 && data[1] === 216 && data[2] === 255) return 'image/jpeg';
  if (/^GIF8[79]a/.test(data.subarray(0, 6).toString('ascii'))) return 'image/gif';
  if (data.subarray(0, 4).toString('ascii') === 'RIFF' && data.subarray(8, 12).toString('ascii') === 'WEBP') return 'image/webp';
  fail('unsupported-image');
}

/** Read images directly for the main model, retaining file-safety checks without a selection gate */
export class ImageAdmission {
  #closed = false;
  #authority;
  constructor({ profiles, sessionId, cwd, resources = [], source = new FileSource() }) {
    this.profiles = profiles; this.sessionId = sessionId; this.cwd = cwd; this.resources = resources; this.source = source;
    this.#authority = profiles.selection(cwd)?.identity;
  }
  close() { this.#closed = true; }
  read(input) {
    if (input.exception !== undefined && input.exception !== 'image' || input.receipt !== undefined || input.evidence !== undefined ||
      input.questionDigest !== undefined || input.offset !== undefined || input.limit !== undefined) fail('image-arguments-required');
    if (this.#closed) fail('session-changed');
    const selected = this.profiles.selection(this.cwd);
    if (!selected || selected.identity !== this.#authority) fail('stale-image-authority');
    const path = imagePath(input.path, this.cwd);
    if (!path) fail('unsupported-image');
    const policy = this.profiles.fileSettings(this.sessionId, this.cwd).load();
    // Images bypass text relevance screening, including outside project roots, but not protected-file checks.
    let snapshot;
    try {
      snapshot = this.source.snapshotBytes(path, { ...policy, allowedRoots: [path],
        excludedPaths: [...policy.excludedPaths, ...this.resources, ...selected.profile.projects.flatMap(p => p.excludedPaths)] },
      { sourceLimit: imageByteLimit });
    } catch (error) { if (error instanceof Unavailable) throw error; fail('image-unavailable'); }
    return { data: snapshot.data, path: snapshot.canonical, mimeType: mimeType(snapshot.data),
      admission: { kind: 'image', pathId: snapshot.id, digest: snapshot.digest, bytes: snapshot.bytes } };
  }
}
