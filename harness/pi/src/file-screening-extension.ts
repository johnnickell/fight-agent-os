import { readFileSync } from 'node:fs';
import { Type } from 'typebox';
import type { ExtensionAPI, ExtensionContext } from '@earendil-works/pi-coding-agent';
import { FileScreening, type ScreenResult } from './jev/file-screening.mjs';
import { FileScreeningSettings } from './jev/local-store.mjs';
import type { ConsumerConnection, Connection, OperatorConfiguration } from './jev/judgments.mjs';

const rule = readFileSync(new URL('../FILE_SCREENING_RULE.md', import.meta.url), 'utf8');
const parameters = Type.Object({
  path: Type.Optional(Type.String({ minLength: 1, maxLength: 4096 })),
  paths: Type.Optional(Type.Array(Type.String({ minLength: 1, maxLength: 4096 }), { minItems: 1, maxItems: 50 })),
  glob: Type.Optional(Type.String({ minLength: 1, maxLength: 4096 })),
  need: Type.String({ minLength: 1, maxLength: 4096 }),
  question: Type.Union([
    Type.Object({ type: Type.Literal('yes-no'), instructions: Type.String({ minLength: 1, maxLength: 4096 }) }, { additionalProperties: false }),
    Type.Object({ type: Type.Literal('choice'), instructions: Type.String({ minLength: 1, maxLength: 4096 }),
      choices: Type.Record(Type.String(), Type.String({ minLength: 1, maxLength: 4096 })),
      unknownChoice: Type.String(), otherChoice: Type.String(), relevantChoices: Type.Array(Type.String(), { minItems: 1, maxItems: 16 }),
    }, { additionalProperties: false }),
  ]),
}, { additionalProperties: false });

type Dependencies = { settings?: FileScreeningSettings; configuration: () => OperatorConfiguration | null };

/** Couple available session tool declarations and the single shared instruction rule */
export function installFileScreening(pi: ExtensionAPI, dependencies: Dependencies) {
  const settings = dependencies.settings ?? new FileScreeningSettings();
  let screening: FileScreening | undefined;
  function connect(authorize: ConsumerConnection['authorize']) {
    let capability: Connection | { state: string } | undefined;
    pi.events.emit('fight:jev:connect', { callerId: 'file-screen-v1', authorize,
      connected: (value: Connection | { state: string }) => { capability = value; } });
    return capability;
  }
  function register(available: boolean) {
    pi.registerTool({ name: 'jev_read_file', label: 'Screen file relevance',
      description: 'Screen operator-authorized UTF-8 files without returning contents. Supply exactly one of path, paths (1-50), or glob (*, ?, ** segments; no shell/braces/classes). Bounds: 32 KiB/file, 1 MiB source, 10000 entries, 60 seconds. Per-file positives alone permit selective follow-up; incomplete coverage is not absence. Valid cache hits still recheck source/access. Prefer yes/no; classification predeclares relevance and unknown/other exits. Does not grant permissions.',
      exposure: available ? 'direct' : 'hidden', parameters,
      outputSchema: Type.Object({ status: Type.String(), threshold: Type.Union([
        Type.Literal('positive'), Type.Literal('not-positive'), Type.Literal('unresolved'),
      ]), judgment: Type.Unknown(), coverage: Type.Object({ complete: Type.Boolean() }, { additionalProperties: true }) }, { additionalProperties: true }),
      promptGuidelines: available ? [rule] : [],
      annotations: { readOnlyHint: true, destructiveHint: false, idempotentHint: false, openWorldHint: true },
      async execute(_id, input, signal) {
        const result: ScreenResult = screening ? await screening.screen(input, { signal }) :
          { status: 'file-profile-unavailable', threshold: 'unresolved', policyVersion: 'file-screen-v1', judgment: null,
            source: null, receipt: null, coverage: { complete: false, evaluatedBytes: 0, totalBytes: null } };
        const usage = result.accounting?.measuredUsage ?? result.receipt?.usage;
        // Pi requires numeric cost fields. Never substitute zero for unavailable provider cost.
        const measuredUsage = usage?.costUsd !== null && usage?.costUsd !== undefined ? {
          input: usage.inputTokens, output: usage.outputTokens, cacheRead: 0, cacheWrite: 0,
          totalTokens: usage.inputTokens + usage.outputTokens,
          cost: { input: usage.costUsd, output: 0, cacheRead: 0, cacheWrite: 0, total: usage.costUsd },
        } : undefined;
        return { content: [{ type: 'text', text: JSON.stringify(result) }], details: result, structuredContent: result,
          isError: !['complete', 'incomplete'].includes(result.status), usage: measuredUsage };
      },
    });
  }
  function refresh(ctx: ExtensionContext) {
    const status = screening?.availability() ?? 'file-profile-unavailable';
    register(status === 'available');
    if (ctx.mode === 'tui') ctx.ui.setStatus('fight-file-screen', `File screening: ${status} (instruction-based)`);
  }
  register(false);
  pi.on('session_start', (_event, ctx) => {
    screening?.close();
    screening = new FileScreening({ settings, configuration: dependencies.configuration, connect,
      sessionId: ctx.sessionManager.getSessionId(), cwd: ctx.cwd });
    refresh(ctx);
  });
  pi.on('before_agent_start', (_event, ctx) => { refresh(ctx); });
  pi.on('session_shutdown', (_event, ctx) => {
    screening?.close(); screening = undefined; register(false);
    if (ctx.mode === 'tui') ctx.ui.setStatus('fight-file-screen', undefined);
  });
}
