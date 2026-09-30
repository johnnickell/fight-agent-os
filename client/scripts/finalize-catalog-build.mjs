import { createHash } from 'node:crypto';
import { readdir, readFile, writeFile } from 'node:fs/promises';
import { URL } from 'node:url';

// Storybook emits a wall-clock telemetry timestamp even with telemetry disabled.
// Keep useful tool metadata, but omit that non-runtime field for reproducible output.
const path = new URL('../../.runs/client/storybook/project.json', import.meta.url);
const metadata = JSON.parse(await readFile(path, 'utf8'));
delete metadata.generatedAt;
await writeFile(path, `${JSON.stringify(metadata)}\n`);

// Inspect the static catalog directly; tool bundles are not production /app assets.
const root = new URL('../../.runs/client/storybook/', import.meta.url);
const artifacts = [];
async function inspect(directory, prefix = '') {
  for (const entry of await readdir(directory, { withFileTypes: true })) {
    const name = prefix + entry.name;
    const url = new URL(entry.name, directory);
    if (entry.isDirectory()) {
      await inspect(new URL(`${entry.name}/`, directory), `${name}/`);
      continue;
    }
    if (!entry.isFile() || name.endsWith('.map')) {
      throw new Error(`Unexpected catalog asset or source map: ${name}`);
    }
    const bytes = await readFile(url);
    if (/\.(?:js|css|html)$/.test(name) && /sourceMappingURL/.test(bytes.toString('utf8'))) {
      throw new Error(`Catalog source-map reference: ${name}`);
    }
    artifacts.push({
      name,
      bytes: bytes.length,
      sha256: createHash('sha256').update(bytes).digest('hex')
    });
  }
}
await inspect(root);
artifacts.sort((a, b) => a.name.localeCompare(b.name));
await writeFile(
  new URL('../../.runs/client/storybook-artifacts.json', import.meta.url),
  `${JSON.stringify(artifacts, null, 2)}\n`
);
console.log(
  `Catalog artifact policy passed: ${artifacts.length} files, ${artifacts.reduce((total, file) => total + file.bytes, 0)} bytes; no source maps`
);
