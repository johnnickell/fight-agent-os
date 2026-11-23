import { build } from 'esbuild';
import { execFileSync } from 'node:child_process';
import { mkdir, mkdtemp, rm, symlink, writeFile } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import process from 'node:process';

const reports = resolve('../.runs/build/reports/client');
const temporary = await mkdtemp(join(tmpdir(), 'fight-react-consumer-'));
try {
  const [packed] = JSON.parse(
    execFileSync(
      'npm',
      ['pack', '../packages/react', '--ignore-scripts', '--json', '--pack-destination', temporary],
      { encoding: 'utf8' }
    )
  );
  const modules = join(temporary, 'node_modules');
  const installed = join(modules, '@fight/react');
  await mkdir(installed, { recursive: true });
  execFileSync('tar', [
    '-xzf',
    join(temporary, packed.filename),
    '--strip-components=1',
    '-C',
    installed
  ]);
  // Use the one locked dependency install; the library itself must resolve from extracted bytes.
  for (const dependency of [
    'react',
    'react-dom',
    'react-bootstrap',
    'bootstrap',
    'formik',
    '@types'
  ]) {
    await symlink(resolve('node_modules', dependency), join(modules, dependency), 'dir');
  }
  await writeFile(
    join(temporary, 'consumer.tsx'),
    `
import { createAccountSession, LoginPage } from '@fight/react/account';
import { BrandProvider, PageHeading, ShellLayout } from '@fight/react/controls';
import '@fight/react/styles.css';
export function Account() {
  const session = createAccountSession({ schemaVersion: 2, apiBasePath: '/api/v1' }, () => ({ clear() {}, dispose() {} }));
  return <BrandProvider brand={{ name: 'Harbor Desk' }}><ShellLayout><PageHeading>Sign in</PageHeading><LoginPage service={session.coordinator} schemas={session.schemas} /></ShellLayout></BrandProvider>;
}
`
  );
  await writeFile(join(temporary, 'css.d.ts'), "declare module '*.css';\n");
  execFileSync(
    process.execPath,
    [
      resolve('node_modules/typescript/bin/tsc'),
      '--noEmit',
      '--strict',
      '--jsx',
      'react-jsx',
      '--lib',
      'ES2022,DOM,DOM.Iterable',
      '--module',
      'ESNext',
      '--moduleResolution',
      'Bundler',
      'consumer.tsx',
      'css.d.ts'
    ],
    { cwd: temporary, stdio: 'inherit' }
  );
  const bundle = await build({
    absWorkingDir: temporary,
    entryPoints: ['consumer.tsx'],
    bundle: true,
    outdir: 'out',
    write: false,
    format: 'esm',
    jsx: 'automatic',
    metafile: true,
    logLevel: 'silent'
  });
  const inputs = Object.keys(bundle.metafile.inputs);
  if (
    !inputs.includes('node_modules/@fight/react/dist/styles.css') ||
    !inputs.includes('node_modules/@fight/react/dist/account.js') ||
    !inputs.includes('node_modules/@fight/react/dist/controls.js')
  ) {
    throw new Error('Consumer did not resolve all public entry points from the packed library');
  }
  await writeFile(join(reports, 'fight-react-pack.json'), `${JSON.stringify(packed, null, 2)}\n`);
  console.log(`Qualified ${packed.id}: ${packed.entryCount} packed files, ${packed.integrity}`);
} finally {
  await rm(temporary, { recursive: true, force: true });
}
