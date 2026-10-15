#!/usr/bin/env node
import { resolve } from 'node:path';
import { ScreeningProfiles } from './screening-profile.mjs';
import { ScreeningBuild } from './screening-build.mjs';
import { operatorDirectory } from './local-store.mjs';

// External trusted executor only. This is deliberately not a shell tool, automatic executor, or authority grant.
// It accepts fixed metadata, never commands, source bodies, arbitrary report prose or model/path overrides.
const [action, ...args] = process.argv.slice(2), flags = {};
try {
  if (!['inspect', 'complete'].includes(action) || args.length % 2) throw new Error('usage');
  for (let i = 0; i < args.length; i += 2) {
    const key = args[i];
    if (!['--cwd', '--directory', '--request', '--outcome', '--evidence-digest', '--url', '--head', '--attest', '--check-kind', '--exit-code', '--tests', '--assertions', '--warnings', '--finding-evidence', '--finding-line', '--finding-code'].includes(key) || Object.hasOwn(flags, key)) throw new Error('usage');
    flags[key] = args[i + 1];
  }
  if (!flags['--cwd'] || process.env.FIGHT_SCREENING_CHILD === '1') throw new Error('host-only');
  const cwd = resolve(flags['--cwd']), build = new ScreeningBuild(new ScreeningProfiles(flags['--directory'] ? resolve(flags['--directory']) : operatorDirectory()));
  if (action === 'inspect') {
    if (Object.keys(flags).some(k => !['--cwd', '--directory'].includes(k))) throw new Error('usage');
    console.log(JSON.stringify({ requests: build.pendingWork(cwd), trust: 'pending-work-not-execution-authority' }, null, 2));
  } else {
    if (flags['--attest'] !== 'executed-within-existing-authority') throw new Error('attestation-required');
    const result = { outcome: flags['--outcome'], evidenceDigest: flags['--evidence-digest'] };
    if (flags['--url'] || flags['--head']) result.publication = { url: flags['--url'], head: flags['--head'] };
    const numeric = key => flags[key] === undefined ? null : Number(flags[key]);
    if (['--check-kind', '--exit-code', '--tests', '--assertions', '--warnings', '--finding-evidence', '--finding-line', '--finding-code'].some(k => flags[k] !== undefined)) {
      result.summary = { kind: flags['--check-kind'], exitCode: numeric('--exit-code'), tests: numeric('--tests'), assertions: numeric('--assertions'), warnings: numeric('--warnings') };
      if (flags['--finding-evidence'] || flags['--finding-line'] || flags['--finding-code']) result.summary.finding = {
        evidence: flags['--finding-evidence'], offset: numeric('--finding-line'), code: flags['--finding-code'] };
    }
    console.log(JSON.stringify(await build.completeWork(cwd, flags['--request'], result)));
  }
} catch (error) {
  console.error(`Screened work host: ${error.code ?? 'invalid-or-unavailable-handoff'}. Preserve original request/effects; inspect existing authority and private evidence. No command executed.`);
  process.exitCode = 1;
}
