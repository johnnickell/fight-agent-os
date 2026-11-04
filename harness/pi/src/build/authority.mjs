import { realpathSync } from 'node:fs';
import { sep } from 'node:path';

// In-process trusted-host state, keyed by the actual SDK SessionManager, not an id,
// transcript entry, profile or prompt. Symbol storage also shares it across Pi's
// extension loader and native ESM imports. This is authority, not a child registry.
const key = Symbol.for('fight:session-workflow-authority');
const state = globalThis[key] ??= { workflows: new WeakMap(), stages: new WeakMap(), sdk: new WeakSet() };
export const engineerEffects = Object.freeze(['source', 'checks', 'commit', 'task-handoff']);
const fail = code => { throw new Error(code); };

export function rememberSdkSession(manager) { state.sdk.add(manager); }
export function isSdkSession(manager) { return state.sdk.has(manager); }
export function publishWorkflow(manager, workflow) { state.workflows.set(manager, workflow); }
export function sessionWorkflow(manager) { return state.workflows.get(manager); }
export function stageBinding(manager) { return state.stages.get(manager); }
export function closeWorkflow(manager) {
  state.workflows.delete(manager);
  const stage = state.stages.get(manager);
  if (stage) stage.live = false;
}

/** Admit the actual fresh Engineer before upstream binds extensions or prompts */
export function bindEngineerSession(session, assignment) {
  const manager = session.sessionManager;
  if (!manager || state.stages.has(manager) || session.messages?.length !== 0 ||
      manager.getEntries().some(entry => ['message', 'custom_message'].includes(entry.type))) fail('engineer-session-not-fresh');
  if (manager.getSessionId() === assignment.parent || !/^TASK-\d{5}$/.test(assignment.task) ||
      assignment.role !== 'engineer' || assignment.skill !== 'work' ||
      JSON.stringify(assignment.effects) !== JSON.stringify(engineerEffects)) fail('engineer-assignment-invalid');
  if (realpathSync(manager.getCwd()) !== assignment.checkout || manager.getCwd() !== assignment.checkout) fail('engineer-checkout-mismatch');
  const file = manager.getSessionFile?.();
  if (!file || !assignment.sessionRoots.some(root => file.startsWith(root + sep)) ||
      realpathSync(manager.getSessionDir()) !== manager.getSessionDir()) fail('engineer-session-storage-unqualified');
  if (session.model?.provider !== assignment.provider || session.model?.id !== assignment.model ||
      session.thinkingLevel !== assignment.thinking) fail('engineer-model-mismatch');
  if (typeof session.subscribe !== 'function' || !session.resourceLoader?.getExtensions ||
      !session.extensionRunner?.hasHandlers('session_start')) fail('engineer-session-api-unavailable');
  const loaded = session.resourceLoader.getExtensions();
  if (loaded.errors.length || !assignment.requiredExtensions.every(path => loaded.extensions.some(extension => extension.path === path))) fail('engineer-fight-extensions-unavailable');
  if (!assignment.instructions.every(content => content && session.systemPrompt.includes(content))) fail('engineer-governing-context-missing');
  rememberSdkSession(manager);
  const binding = { ...assignment, sessionId: manager.getSessionId(), live: true };
  state.stages.set(manager, binding);
  // Fight checks actual initialized admission in its input handler before the
  // first provider request. A mismatch consumes input and is never a success.
  return binding;
}

/** Return display identity only for the exact bound, initialized session */
export function engineerIdentity(session, parent) {
  const binding = stageBinding(session.sessionManager), workflow = sessionWorkflow(session.sessionManager);
  if (!binding?.live || binding.parent !== parent || binding.sessionId !== session.sessionManager.getSessionId() ||
      binding.checkout !== realpathSync(session.sessionManager.getCwd()) ||
      session.model?.provider !== binding.provider || session.model?.id !== binding.model || session.thinkingLevel !== binding.thinking ||
      workflow?.skill !== 'work' || workflow.role !== 'engineer' || workflow.taskId !== binding.task || workflow.checkout !== binding.checkout) return null;
  return { task: binding.task, role: 'engineer', attempt: binding.attempt };
}
