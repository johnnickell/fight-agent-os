import { readFileSync } from 'node:fs';
import { Type } from 'typebox';
import type { ExtensionAPI, ExtensionContext } from '@earendil-works/pi-coding-agent';
import { WorkflowAccess, skillEffects } from './workflow/access.mjs';
import { inChildSessionContext } from '../vendor/pi-subagents/src/child-context.ts';
import { closeWorkflow, stageIdentity, isSdkSession, publishWorkflow, rememberSdkSession, stageBinding } from './build/authority.mjs';
import { installStatusFooter } from './status-footer.ts';

const rule = readFileSync(new URL('../WORKFLOW_RULE.md', import.meta.url), 'utf8');

/** Preserve session-local workflow authority independently of providers and file tools */
export default function installWorkflow(pi: ExtensionAPI) {
  installStatusFooter(pi);
  const childFactory = inChildSessionContext();
  let workflow = new WorkflowAccess('review');
  let sessionId = '', failure: string | undefined;
  const status = (ctx: ExtensionContext) => {
    const binding = stageBinding(ctx.sessionManager);
    return { state: failure ? 'held' : 'active', reason: failure ?? null, phase: workflow.role,
      workflow: { skill: workflow.skill, effect: skillEffects[workflow.skill], taskId: workflow.taskId,
        checkout: workflow.checkout, mode: workflow.mode, launcherRole: workflow.launcher },
      ...(binding ? { stage: { role: binding.role, skill: binding.skill, task: binding.task, attempt: binding.attempt,
        parent: binding.parent, candidate: binding.head, evidenceBase: binding.base, canonicalReport: binding.report,
        effects: binding.effects, live: binding.live } } : {}) };
  };
  const persistRole = (role: string) => {
    try { pi.appendEntry('fight-workflow-role-v3', workflow.checkpoint(sessionId, role)); }
    catch (error) {
      failure = 'workflow-role-persistence-failed';
      try { pi.appendEntry('fight-workflow-role-v3', workflow.checkpoint(sessionId, 'reviewer')); } catch { /* Remain held */ }
      throw error;
    }
  };
  const hold = (ctx: ExtensionContext, error: unknown) => {
    failure = failure === 'workflow-role-persistence-failed' ? failure :
      error instanceof Error && 'code' in error && typeof error.code === 'string' ? error.code : 'workflow-unavailable';
    publishWorkflow(ctx.sessionManager, new WorkflowAccess('review', { checkout: ctx.cwd }));
    if (ctx.mode === 'tui') ctx.ui.notify(`Fight workflow held: ${failure}. Correct the interactive workflow selection; persistence failures require storage repair and reload. Native tools remain unchanged.`, 'warning');
  };
  pi.registerTool({ name: 'fight_workflow_status', label: 'Inspect Fight workflow',
    description: 'Inspect session-local workflow skill, effect, subject, selected checkout and trusted stage binding. Read-only; no provider access or file-tool gate.',
    parameters: Type.Object({}, { additionalProperties: false }), promptGuidelines: [rule],
    async execute(_id, _args, _signal, _update, ctx) {
      const value = status(ctx);
      return { content: [{ type: 'text', text: JSON.stringify(value) }], details: value };
    } });
  pi.on('session_start', (_event, ctx) => {
    sessionId = ctx.sessionManager.getSessionId(); failure = undefined;
    try {
      if (childFactory) rememberSdkSession(ctx.sessionManager);
      const binding = stageBinding(ctx.sessionManager), sdk = isSdkSession(ctx.sessionManager);
      workflow = binding?.live ? new WorkflowAccess(binding.skill, { taskId: binding.task, checkout: binding.checkout }) :
        WorkflowAccess.restore({ phase: sdk ? 'review' : process.env.FIGHT_WORKFLOW_PHASE, sessionId,
          taskId: sdk ? undefined : process.env.FIGHT_WORKFLOW_TASK,
          checkout: sdk ? ctx.cwd : process.env.FIGHT_WORKFLOW_CHECKOUT ?? ctx.cwd,
          entries: ctx.sessionManager.getEntries(), parentSession: ctx.sessionManager.getHeader()?.parentSession });
      persistRole(workflow.role);
      publishWorkflow(ctx.sessionManager, workflow);
    } catch (error) { workflow = new WorkflowAccess('review', { checkout: ctx.cwd }); hold(ctx, error); }
  });
  pi.on('input', (event, ctx) => {
    const binding = stageBinding(ctx.sessionManager);
    if (binding) {
      if (!stageIdentity({ sessionManager: ctx.sessionManager, model: ctx.model, thinkingLevel: ctx.thinkingLevel }, binding.parent) || failure) {
        binding.failure = 'stage-initialized-admission-mismatch';
        return { action: 'handled' as const };
      }
      binding.admitted = true;
      return;
    }
    // SDK input may report "interactive" but is not an operator selection.
    if (isSdkSession(ctx.sessionManager) || failure === 'workflow-role-persistence-failed') return;
    try {
      if (workflow.input(event.text, event.source, persistRole)) {
        failure = undefined; publishWorkflow(ctx.sessionManager, workflow);
      }
    } catch (error) { hold(ctx, error); }
  });
  pi.registerCommand('fight-workflow', { description: 'Inspect workflow or explicitly select role engineer|reviewer|qa|land in native TUI',
    handler: async (args, ctx) => {
      const action = args.trim();
      if (action.startsWith('role ')) {
        if (ctx.mode !== 'tui' || isSdkSession(ctx.sessionManager) || stageBinding(ctx.sessionManager) || failure === 'workflow-role-persistence-failed') return;
        try { workflow.select(action.slice(5).trim(), persistRole); failure = undefined; publishWorkflow(ctx.sessionManager, workflow); }
        catch (error) { hold(ctx, error); }
      }
      const value = JSON.stringify(status(ctx), null, 2);
      if (ctx.hasUI) ctx.ui.notify(value, failure ? 'warning' : 'info');
      else console.error(`Fight workflow: ${value}`);
    } });
  pi.on('session_shutdown', (_event, ctx) => { closeWorkflow(ctx.sessionManager); });
}
