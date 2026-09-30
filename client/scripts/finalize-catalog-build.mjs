import { readFile, writeFile } from 'node:fs/promises';
import { URL } from 'node:url';

// Storybook emits a wall-clock telemetry timestamp even with telemetry disabled.
// Keep useful tool metadata, but omit that non-runtime field for reproducible output.
const path = new URL(
  '../../.runs/client/storybook/project.json',
  import.meta.url,
);
const metadata = JSON.parse(await readFile(path, 'utf8'));
delete metadata.generatedAt;
await writeFile(path, `${JSON.stringify(metadata)}\n`);
