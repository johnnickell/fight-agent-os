import { execFileSync } from 'node:child_process';
import { createHash } from 'node:crypto';
import { readFile, writeFile } from 'node:fs/promises';
import process from 'node:process';

// A local install receipt ties checks to explicit npm ci, not an implicit repair.
// This is provenance/staleness detection, not a tamper-proof node_modules verifier.
const receiptPath = 'node_modules/.fight-install.json';
const manifest = JSON.parse(await readFile('package.json', 'utf8'));
const npm = execFileSync('npm', ['--version'], { encoding: 'utf8' }).trim();
if (process.versions.node !== manifest.engines.node || npm !== manifest.engines.npm) {
  throw new Error('Frontend runtime mismatch; use bin/client setup and storybook-setup');
}
const hashes = {};
for (const path of ['package.json', 'package-lock.json', 'node_modules/.package-lock.json']) {
  hashes[path] = createHash('sha256')
    .update(await readFile(path))
    .digest('hex');
}
const receipt = JSON.stringify({ node: process.versions.node, npm, hashes });
if (process.argv[2] === 'record') {
  await writeFile(receiptPath, `${receipt}\n`);
} else {
  let installed;
  try {
    installed = (await readFile(receiptPath, 'utf8')).trim();
  } catch {
    throw new Error('Missing frontend install receipt; run ./bin/client setup explicitly');
  }
  if (installed !== receipt) {
    throw new Error('Stale frontend installation; run ./bin/client setup explicitly');
  }
}
