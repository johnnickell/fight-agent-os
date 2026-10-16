#!/usr/bin/env node
import { fileURLToPath } from 'node:url';
import { dirname, resolve } from 'node:path';
import { ScreeningProfiles } from './jev/screening-profile.mjs';

/** Read-only launcher preflight; the native extension binds the actual session after startup */
export function prepareScreening(cwd, profiles = new ScreeningProfiles()) {
  const selection = profiles.selection(cwd);
  return selection ? { enrolled: true, profileIdentity: selection.identity,
    extension: resolve(dirname(fileURLToPath(import.meta.url)), '..'), state: 'pending-native-session-binding' } :
    { enrolled: false, state: 'not-enrolled' };
}
if (process.argv[1] && resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
  try {
    if (process.argv.length !== 4 || process.argv[2] !== 'prepare') throw new Error('invalid invocation');
    process.stdout.write(JSON.stringify(prepareScreening(process.argv[3])) + '\n');
  } catch (error) {
    console.error(error.code === 'repository-metadata-unavailable'
      ? 'File screening: repository-metadata-unavailable; inspect registered worktrees from the enrolled repository, repair with Git worktree repair or return to a valid checkout, then reload. No per-worktree enrollment or accounting reset.'
      : 'File screening: launcher preflight failed; inspect operator enrollment before launch');
    process.exitCode = 1;
  }
}
