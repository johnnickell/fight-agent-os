import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { appendFileSync, mkdtempSync, readFileSync, readdirSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

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
    'COMPACTION.md', 'TOOL_POLICY.md', 'src/observation/session-file-birth.php',
    'src/observation/run-stage.php', 'src/observation/stage-observation.php', 'src/observation/stage-discovery.php',
    `${engine}/README.md`, `${engine}/docs/packages.md`,
  ]) assert.ok(files.includes(path.replace(/^\.\//, '')), `Missing packaged file: ${path}`);
  for (const directory of [...manifest.pi.subagents.agents, 'profiles']) {
    assert.ok(files.some((path) => path.startsWith(`${directory.replace(/^\.\//, '')}/`)),
      `Missing packaged directory: ${directory}`);
  }
  assert.ok(!files.some((path) => /(^|\/)(node_modules|tests?|scripts)\//.test(path)),
    'Packaged development files');
  for (const path of ['run-stage.py', 'observation.py', 'automatic_observation.py']) {
    assert.ok(!files.includes(`src/observation/${path}`), `Packaged superseded process tool: ${path}`);
  }
  for (const path of ['LICENSE', `${engine}/LICENSE`]) {
    assert.deepEqual(extract(path), readFileSync(join(root, path)), `Changed packaged license: ${path}`);
  }
  assert.match(extract(`${engine}/LICENSE`).toString(), /Copyright[^\n]*tintinweb/i);
  assert.equal(engineManifest.author, 'tintinweb');
  assert.equal(engineManifest.license, 'MIT');
  console.log(`${filename}: ${files.length} files; runtime entries, docs and tintinweb MIT attribution verified.`);

  if (['linux', 'darwin'].includes(process.platform)) {
    execFileSync('tar', ['-xf', archive, '-C', destination]);
    const { sessionFileIdentity, sameSessionFile } = await import(pathToFileURL(join(destination, 'package/src/observation/custody.mjs')));
    const fixture = join(destination, 'session.jsonl');
    writeFileSync(fixture, 'package lifetime fixture');
    const first = sessionFileIdentity(fixture);
    appendFileSync(fixture, '\nappend');
    assert.ok(sameSessionFile(first, sessionFileIdentity(fixture)));
    assert.throws(() => sessionFileIdentity(destination), /Persistent parent session unavailable/);
    if (process.platform === 'linux') assert.throws(() => sessionFileIdentity('/proc/self/status'));
    console.log(`Packaged session-file observation and refusal verified on ${process.platform}.`);
    const monitor = join(destination, 'package/src/observation/run-stage.php');
    assert.match(execFileSync('php', [monitor, '--help'], { encoding: 'utf8' }), /Run one Pi role/);
  }
} finally {
  rmSync(destination, { recursive: true, force: true });
}
