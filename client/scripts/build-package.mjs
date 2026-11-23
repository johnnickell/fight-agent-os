import { execFileSync } from 'node:child_process';
import { cp, mkdir, mkdtemp, readdir, rm, symlink, writeFile } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import process from 'node:process';
import { compileAsync } from 'sass';

const library = resolve('../packages/react');
const temporary = await mkdtemp(join(tmpdir(), 'fight-react-'));
try {
  // Use the owning frontend installation to compile the library, without a second npm install.
  await symlink(resolve('node_modules'), join(temporary, 'node_modules'), 'dir');
  await cp(join(library, 'src'), join(temporary, 'src'), { recursive: true });
  await cp(join(library, 'tsconfig.json'), join(temporary, 'tsconfig.json'));
  execFileSync(process.execPath, [resolve('node_modules/typescript/bin/tsc'), '-p', temporary], {
    stdio: 'inherit'
  });
  const { css } = await compileAsync(join(library, 'styles/index.scss'), {
    loadPaths: [resolve('node_modules')],
    quietDeps: true,
    // The qualified Bootstrap 5 stylesheet still requires legacy Sass imports.
    silenceDeprecations: ['import']
  });
  await writeFile(join(temporary, 'dist/styles.css'), css);
  const output = join(library, 'dist');
  await mkdir(output, { recursive: true });
  for (const entry of await readdir(output)) {
    await rm(join(output, entry), { recursive: true, force: true });
  }
  await cp(join(temporary, 'dist'), output, { recursive: true });
} finally {
  await rm(temporary, { recursive: true, force: true });
}
