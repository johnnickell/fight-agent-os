import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { mkdtempSync, readFileSync, readdirSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = fileURLToPath(new URL('..', import.meta.url));
const destination = mkdtempSync(join(tmpdir(), 'pi-harness-pack.'));
const engine = 'vendor/pi-subagents';
try {
  execFileSync('npm', ['pack', '--ignore-scripts', '--loglevel=warn', '--pack-destination', destination],
    { cwd: root, stdio: ['ignore', 'ignore', 'inherit'] });
  const archives = readdirSync(destination);
  assert.equal(archives.length, 1, 'Expected one local package archive');
  const [filename] = archives;
  const archive = join(destination, filename);
  const extract = (path) => execFileSync('tar', ['-xOf', archive, `package/${path}`]);
  const manifest = JSON.parse(extract('package.json'));
  const engineManifest = JSON.parse(extract(`${engine}/package.json`));
  const files = execFileSync('tar', ['-tf', archive], { encoding: 'utf8' }).trim().split('\n')
    .map((path) => path.replace(/^package\//, ''));
  for (const path of [
    ...Object.values(manifest.bin), ...manifest.pi.extensions,
    ...engineManifest.pi.extensions.map((path) => `${engine}/${path.replace(/^\.\//, '')}`),
    'README.md', 'SUBAGENTS.md', 'WORKFLOW.md', 'WORKFLOW_RULE.md', 'WORKSPACE.md',
    'COMPACTION.md', 'TOOL_POLICY.md', `${engine}/README.md`, `${engine}/docs/packages.md`,
  ]) assert.ok(files.includes(path.replace(/^\.\//, '')), `Missing packaged file: ${path}`);
  for (const directory of [...manifest.pi.subagents.agents, 'profiles']) {
    assert.ok(files.some((path) => path.startsWith(`${directory.replace(/^\.\//, '')}/`)),
      `Missing packaged directory: ${directory}`);
  }
  assert.ok(!files.some((path) => /(^|\/)(node_modules|tests?|scripts)\//.test(path)),
    'Packaged development files');
  for (const path of ['LICENSE', `${engine}/LICENSE`]) {
    assert.deepEqual(extract(path), readFileSync(join(root, path)), `Changed packaged license: ${path}`);
  }
  assert.match(extract(`${engine}/LICENSE`).toString(), /Copyright[^\n]*tintinweb/i);
  assert.equal(engineManifest.author, 'tintinweb');
  assert.equal(engineManifest.license, 'MIT');
  console.log(`${filename}: ${files.length} files; runtime entries, docs and tintinweb MIT attribution verified.`);
} finally {
  rmSync(destination, { recursive: true, force: true });
}
