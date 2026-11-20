import { execFileSync } from 'node:child_process';
import { createHash } from 'node:crypto';
import { readFileSync, writeFileSync, unlinkSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const mode = process.argv[2] ?? 'check';
if (!['check', 'setup'].includes(mode)) {
  console.error('Usage: node harness/pi/scripts/dependencies.mjs [check|setup]');
  process.exit(2);
}
const npm = execFileSync('npm', ['--version'], { encoding: 'utf8' }).trim();
const packages = [
  { path: root, name: 'Pi Harness', tools: [] },
  { path: join(root, 'vendor/pi-subagents'), name: 'Pi subagent engine',
    tools: ['biome', 'tsc', 'vitest'] },
];

function identity(path) {
  const hashes = {};
  for (const file of ['package.json', 'package-lock.json', 'node_modules/.package-lock.json']) {
    hashes[file] = createHash('sha256').update(readFileSync(join(path, file))).digest('hex');
  }
  return JSON.stringify({ node: process.versions.node, npm, platform: process.platform,
    arch: process.arch, hashes });
}

function validate(pkg) {
  // Host-provided Pi peers are intentionally absent from the Harness runtime install.
  execFileSync('npm', ['ls', '--all', '--omit=peer', '--legacy-peer-deps'],
    { cwd: pkg.path, stdio: 'pipe' });
  for (const tool of pkg.tools) {
    // Execute the local binary: existence alone cannot qualify native optional packages.
    execFileSync(join(pkg.path, 'node_modules/.bin', tool), ['--version'],
      { cwd: pkg.path, stdio: 'pipe' });
  }
}

let failed = false;
for (const pkg of packages) {
  const receipt = join(pkg.path, 'node_modules/.fight-install.json');
  try {
    if (readFileSync(receipt, 'utf8').trim() !== identity(pkg.path)) {
      throw new Error('lockfile or runtime changed');
    }
    validate(pkg);
    console.log(`${pkg.name}: locked dependencies ready; installation reused.`);
    continue;
  } catch (error) {
    if (mode === 'check') {
      console.error(`${pkg.name}: dependencies are missing, stale, or unusable ` +
        `(${error.message.split('\n')[0]}). ` +
        'Run ./bin/pi-dependencies setup from this checkout (also run by ./bin/runtime setup).');
      failed = true;
      continue;
    }
  }
  try {
    // An interrupted install must never retain a previous success receipt.
    try { unlinkSync(receipt); } catch (error) { if (error.code !== 'ENOENT') throw error; }
    console.log(`${pkg.name}: installing locked dependencies for ${process.platform}/${process.arch}.`);
    execFileSync('npm', ['ci', '--include=dev', '--include=optional', '--ignore-scripts',
      '--legacy-peer-deps', '--no-audit', '--no-fund'], { cwd: pkg.path, stdio: 'inherit' });
    validate(pkg);
    writeFileSync(receipt, `${identity(pkg.path)}\n`);
    console.log(`${pkg.name}: locked dependencies installed and validated.`);
  } catch (error) {
    console.error(`${pkg.name}: preparation failed; no successful install receipt recorded.`);
    if (error.stderr) process.stderr.write(error.stderr);
    failed = true;
    break;
  }
}
process.exitCode = failed ? 1 : 0;
