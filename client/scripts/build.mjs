import { build } from 'esbuild';
import { mkdir, rename, rm, writeFile } from 'node:fs/promises';
import { basename, dirname, relative } from 'node:path';
import process from 'node:process';
import { fileURLToPath } from 'node:url';
import { compileAsync } from 'sass';

import { inspectArtifacts } from './inspect-artifacts.mjs';

// Build only source imports, never serialize process.env or emit production maps.
// Keep old hashed assets for pages still open during a deployment. Deployments
// should publish a complete release directory atomically, not prune live assets.
const checking = process.argv.includes('--check');
const outdir = checking ? '../.runs/client/production' : '../public/build';
// Quality checks start clean without deleting hashes needed by an open local /app page.
if (checking) await rm(outdir, { recursive: true, force: true });
await mkdir(outdir, { recursive: true });
const styleInputs = new Set();
const result = await build({
  entryPoints: ['src/main.tsx'],
  outdir,
  write: false,
  entryNames: '[name]-[hash]',
  chunkNames: 'chunk-[hash]',
  assetNames: '[name]-[hash]',
  bundle: true,
  plugins: [
    {
      name: 'sass',
      setup(builder) {
        builder.onLoad({ filter: /\.scss$/ }, async ({ path }) => {
          const result = await compileAsync(path, { loadPaths: ['node_modules'] });
          for (const url of result.loadedUrls) {
            styleInputs.add(relative(process.cwd(), fileURLToPath(url)));
          }
          return { contents: result.css, loader: 'css', resolveDir: dirname(path) };
        });
      }
    }
  ],
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
  logLevel: 'info'
});
// A blocking classic script runs in <head> before CSS or deferred application ESM.
// It bundles the very same preference mapping imported by the React owner.
const prepaint = await build({
  entryPoints: ['src/features/theme/prepaint.ts'],
  outdir,
  write: false,
  entryNames: 'prepaint-[hash]',
  bundle: true,
  format: 'iife',
  platform: 'browser',
  target: ['es2022'],
  minify: true,
  sourcemap: false,
  legalComments: 'external',
  metafile: true
});
const report = await inspectArtifacts(
  {
    inputs: { ...result.metafile.inputs, ...prepaint.metafile.inputs },
    outputs: { ...result.metafile.outputs, ...prepaint.metafile.outputs }
  },
  [...result.outputFiles, ...prepaint.outputFiles],
  styleInputs
);
const reportPrefix = checking ? 'production' : 'build';
await writeFile(
  `../.runs/client/${reportPrefix}-metafile.json`,
  JSON.stringify({ application: result.metafile, prepaint: prepaint.metafile }, null, 2)
);
await writeFile(`../.runs/client/${reportPrefix}-artifacts.json`, JSON.stringify(report, null, 2));
const entry = Object.entries(result.metafile.outputs).find(
  ([, output]) => output.entryPoint === 'src/main.tsx'
);
if (!entry || !entry[1].cssBundle) throw new Error('Missing client entry assets');
const prepaintEntry = Object.entries(prepaint.metafile.outputs).find(
  ([, output]) => output.entryPoint === 'src/features/theme/prepaint.ts'
);
if (!prepaintEntry) throw new Error('Missing theme prepaint asset');
const manifest = {
  prepaint: `/build/${basename(prepaintEntry[0])}`,
  script: `/build/${basename(entry[0])}`,
  stylesheet: `/build/${basename(entry[1].cssBundle)}`
};
for (const file of [...result.outputFiles, ...prepaint.outputFiles])
  await writeFile(file.path, file.contents);
await writeFile(`${outdir}/manifest.json.tmp`, JSON.stringify(manifest));
await rename(`${outdir}/manifest.json.tmp`, `${outdir}/manifest.json`);
console.log(JSON.stringify(manifest));
