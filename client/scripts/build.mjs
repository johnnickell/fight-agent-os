import { build } from 'esbuild';
import { rename, writeFile } from 'node:fs/promises';
import { basename } from 'node:path';

// Build only source imports, never serialize process.env or emit production maps.
// Keep old hashed assets for pages still open during a deployment. Deployments
// should publish a complete release directory atomically, not prune live assets.
const result = await build({
  entryPoints: ['src/main.tsx'],
  outdir: '../public/build',
  entryNames: '[name]-[hash]',
  chunkNames: 'chunk-[hash]',
  assetNames: '[name]-[hash]',
  bundle: true,
  splitting: true,
  format: 'esm',
  platform: 'browser',
  target: ['es2022'],
  jsx: 'automatic',
  minify: true,
  sourcemap: false,
  legalComments: 'external',
  define: { 'process.env.NODE_ENV': '"production"' },
  metafile: true,
  logLevel: 'info',
});
const entry = Object.entries(result.metafile.outputs).find(
  ([, output]) => output.entryPoint === 'src/main.tsx',
);
if (!entry || !entry[1].cssBundle)
  throw new Error('Missing client entry assets');
const manifest = {
  script: `/build/${basename(entry[0])}`,
  stylesheet: `/build/${basename(entry[1].cssBundle)}`,
};
await writeFile('../public/build/manifest.json.tmp', JSON.stringify(manifest));
await rename(
  '../public/build/manifest.json.tmp',
  '../public/build/manifest.json',
);
console.log(JSON.stringify(manifest));
