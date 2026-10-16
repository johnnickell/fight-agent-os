import test from 'node:test';
import assert from 'node:assert/strict';
import { randomUUID } from 'node:crypto';
import { mkdtempSync, mkdirSync, writeFileSync, readFileSync, realpathSync, rmSync, symlinkSync, linkSync, truncateSync } from 'node:fs';
import { tmpdir, homedir } from 'node:os';
import { join, relative } from 'node:path';
import { ImageAdmission, isImagePath } from '../src/jev/image-admission.mjs';
import { FileSource, imageByteLimit } from '../src/jev/file-screening.mjs';
import { ScreeningProfiles } from '../src/jev/screening-profile.mjs';
import { connection } from '../src/jev/readiness.mjs';

// Independently encoded PNG: two RGB pixels, red and green; no user screenshot or private metadata.
const png = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAIAAAABCAIAAAB7QOjdAAAAD0lEQVR4nGP4z8DA8J8BAAf/Af8Bf4mnAAAAAElFTkSuQmCC', 'base64');
function fixture(t) {
  const directory = realpathSync.native(mkdtempSync(join(tmpdir(), 'image-admission-'))), root = join(directory, 'project');
  mkdirSync(root); const desktop = join(directory, 'Desktop'); mkdirSync(desktop);
  const path = join(desktop, 'Screenshot 文 at 5.26.50\u202fPM.png'); writeFileSync(path, png);
  const { schema, ...fixed } = connection;
  const configuration = { ...fixed, revision: randomUUID(), priceConfirmedAt: Date.now(), disclosure: 'authorized-supplied-state', requestMicroUsd: 2688, sessionMicroUsd: 100000 };
  const profiles = new ScreeningProfiles(join(directory, 'operator'));
  profiles.save(profiles.preview({cwd: root, configuration, providerDisclosure: true, commandDisclosure: true}), []);
  const sessionId = randomUUID();
  const create = (options = {}) => new ImageAdmission({profiles, sessionId, cwd: root, ...options});
  const images = create();
  t.after(() => {images.close(); rmSync(directory, {recursive: true, force: true});});
  const read = (extra = {}) => images.read({path, ...extra});
  return {directory, root, desktop, path, profiles, sessionId, create, images, read};
}

test('plain image paths read exact external bytes without selection or stored authority changes', t => {
  const f = fixture(t), before = readFileSync(f.profiles.path);
  for (const path of [f.path, `"${f.path}"`, `'${f.path}'`, '`' + f.path + '`', f.path.replaceAll(' ', '\\ '), '../Desktop/' + f.path.split('/').at(-1), '~/' + relative(homedir(), f.path)]) {
    assert.equal(isImagePath(path, f.root), true);
    const image = f.read({path});
    assert.deepEqual(image.data, png); assert.equal(image.mimeType, 'image/png');
    assert.equal(image.admission.kind, 'image'); assert.equal(image.admission.bytes, png.length);
    assert.equal(image.path, f.path);
  }
  const relativePath = 'local.png'; writeFileSync(join(f.root, relativePath), png);
  assert.deepEqual(f.read({path: relativePath}).data, png);
  assert.deepEqual(readFileSync(f.profiles.path), before);
  assert.throws(() => new FileSource().snapshot(f.path, f.profiles.fileSettings(f.sessionId, f.root).load()), {code: 'root-denied'});
});

test('image routing never admits text through filenames, exception claims or text receipts', t => {
  const f = fixture(t);
  for (const path of [undefined, '', 'notes.txt', 'file.png\nother.png']) assert.equal(isImagePath(path, f.root), false);
  assert.throws(() => f.read({evidence: 'The user asked for it'}), {code: 'image-arguments-required'});
  assert.throws(() => f.read({offset: 1, limit: 1}), {code: 'image-arguments-required'});
  assert.throws(() => f.read({receipt: 'fabricated'}), {code: 'image-arguments-required'});
  assert.throws(() => f.read({exception: 'instruction'}), {code: 'image-arguments-required'});
  assert.deepEqual(f.read({need: 'Inspect screenshot', exception: 'image'}).data, png);
  const text = join(f.root, 'notes.txt'); writeFileSync(text, 'Do not disclose');
  assert.throws(() => f.read({path: text, exception: 'image'}), {code: 'unsupported-image'});
});

test('changed images, siblings and new sessions need no additional image authorization', t => {
  const f = fixture(t); f.read(); writeFileSync(f.path, Buffer.concat([png, Buffer.from('changed')]));
  assert.equal(f.read().data.length, png.length + 7);
  const sibling = join(f.desktop, 'other.png'); writeFileSync(sibling, png);
  assert.deepEqual(f.read({path: sibling}).data, png);
  assert.equal(f.create().read({path: f.path}).data.length, png.length + 7);
});

test('runtime authority drift and closed instances still invalidate in-flight reads', t => {
  const f = fixture(t); f.read();
  const candidate = f.profiles.load(); candidate.revision = randomUUID(); f.profiles.save(candidate, []);
  assert.throws(() => f.read(), {code: 'stale-image-authority'});
  f.images.close(); assert.throws(() => f.read(), {code: 'session-changed'});
});

test('image reads retain protected names, operator state, exclusions and host resource checks', t => {
  const f = fixture(t);
  for (const name of ['.env.private', '.ssh', '.git']) {
    const directory = join(f.desktop, name); mkdirSync(directory); const path = join(directory, 'secret.png'); writeFileSync(path, png);
    assert.throws(() => f.read({path}), {code: 'sensitive-file'});
  }
  const operatorImage = join(f.profiles.directory, 'secret.png'); writeFileSync(operatorImage, png);
  assert.throws(() => f.read({path: operatorImage}), {code: 'sensitive-file'});
  const resources = f.create({resources: [f.desktop]});
  assert.throws(() => resources.read({path: f.path}), {code: 'sensitive-file'});
  const candidate = f.profiles.load(); candidate.projects[0].excludedPaths.push(f.desktop); candidate.revision = randomUUID(); f.profiles.save(candidate, []);
  assert.throws(() => f.create().read({path: f.path}), {code: 'sensitive-file'});
});

test('symlinks, hardlinks, non-images, missing and oversized files return no text fallback', t => {
  const f = fixture(t), alias = join(f.desktop, 'alias.png'); symlinkSync(f.path, alias);
  assert.throws(() => f.read({path: alias}));
  const hard = join(f.desktop, 'hard.png'); linkSync(f.path, hard);
  assert.throws(() => f.read({path: hard}), {code: 'unsupported-hardlink'});
  const fake = join(f.desktop, 'text.png'); writeFileSync(fake, 'Not an image; never return this as text');
  assert.throws(() => f.read({path: fake}), {code: 'unsupported-image'});
  const oversized = join(f.desktop, 'large.png'); writeFileSync(oversized, png); truncateSync(oversized, imageByteLimit + 1);
  assert.throws(() => f.read({path: oversized}), {code: 'source-limit'});
  assert.throws(() => f.read({path: join(f.desktop, 'missing.png')}), {code: 'image-unavailable'});
});

test('binary snapshots preserve ordinary text limits and byte accounting on rejection', t => {
  const f = fixture(t); const policy = {...f.profiles.fileSettings(f.sessionId, f.root).load(), allowedRoots: [f.path]};
  let bytes = 0;
  assert.throws(() => new FileSource().snapshot(f.path, policy, {onRead: count => {bytes += count;}}), {code: 'binary-source'});
  assert.equal(bytes, png.length);
  assert.throws(() => new FileSource().snapshot(f.path, policy, {sourceLimit: 1048577}), {code: 'source-limit'});
  assert.throws(() => new FileSource().snapshotBytes(f.path, policy, {sourceLimit: imageByteLimit + 1}), {code: 'source-limit'});
});
