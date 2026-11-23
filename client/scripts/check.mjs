import { spawnSync } from 'node:child_process';
import { mkdirSync } from 'node:fs';
import process from 'node:process';

mkdirSync('../.runs/build/reports/client', { recursive: true });

const steps = [
  ['node', 'scripts/dependencies.mjs'],
  ['node', '--version'],
  ['npm', '--version'],
  ['npm', 'ls', '--depth=0'],
  ...[
    'package-build',
    'pack-check',
    'typecheck',
    'lint',
    'format-check',
    'coverage',
    'build-check',
    'storybook-build',
    'storybook-test',
    'storybook-capture'
  ].map((step) => ['npm', 'run', step])
];

for (const [command, ...args] of steps) {
  console.log(`\n== Frontend: ${command} ${args.join(' ')} ==`);
  const result = spawnSync(command, args, { stdio: 'inherit' });
  if (result.error) throw result.error;
  if (result.signal) throw new Error(`Frontend check interrupted by ${result.signal}`);
  if (result.status !== 0) process.exit(result.status ?? 1);
}
