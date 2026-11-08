import { realpathSync } from 'node:fs';
import { dirname } from 'node:path';

// Actual SessionManager objects share authority across Pi's module loaders. No
// process.env mutation, transcript entry, profile or agent name grants a role.
const key = Symbol.for('fight:session-workflow-authority');
const state = globalThis[key] ??= { workflows: new WeakMap(), stages: new WeakMap(), sdk: new WeakSet() };
export const stageDefinitions = Object.freeze(Object.fromEntries(Object.entries({
  engineer: { skill: 'work', agent: 'fight-engineer', effects: ['source', 'checks', 'commit', 'task-handoff'] },
  reviewer: { skill: 'review', agent: 'fight-reviewer', effects: ['inspect', 'checks', 'review-report'] },
  qa: { skill: 'qa', agent: 'fight-qa', effects: ['inspect', 'checks', 'qa-report'] },
  land: { skill: 'land', agent: 'fight-land', effects: ['source', 'checks', 'commit', 'authorized-delivery', 'owned-cleanup', 'task-handoff'] },
}).map(([role, value]) => [role, Object.freeze({ ...value, effects: Object.freeze(value.effects) })])));
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

/** Bind one actual fresh stage before upstream initializes its extensions */
export function bindStageSession(session, assignment) {
  const manager = session.sessionManager, definition = stageDefinitions[assignment.role];
  if (!manager || state.stages.has(manager) || session.messages?.length !== 0 ||
      manager.getEntries().some(entry => ['message', 'custom_message'].includes(entry.type))) fail('stage-session-not-fresh');
  if (manager.getSessionId() === assignment.parent || !/^TASK-\d{5}$/.test(assignment.task) ||
      !definition || assignment.skill !== definition.skill ||
      JSON.stringify(assignment.effects) !== JSON.stringify(definition.effects)) fail('stage-assignment-invalid');
  if (realpathSync(manager.getCwd()) !== assignment.checkout || manager.getCwd() !== assignment.checkout) fail('stage-checkout-mismatch');
  const file = manager.getSessionFile?.();
  if (!file || dirname(file) !== assignment.sessionDir || manager.getSessionDir() !== assignment.sessionDir ||
      realpathSync(assignment.sessionDir) !== assignment.sessionDir) fail('stage-session-storage-unqualified');
  if (session.model?.provider !== assignment.provider || session.model?.id !== assignment.model ||
      session.thinkingLevel !== assignment.thinking) fail('stage-model-mismatch');
  if (typeof session.subscribe !== 'function' || !session.resourceLoader?.getExtensions ||
      !session.extensionRunner?.hasHandlers('session_start')) fail('stage-session-api-unavailable');
  const loaded = session.resourceLoader.getExtensions();
  if (loaded.errors.length || !assignment.requiredExtensions.every(path => loaded.extensions.some(extension => extension.path === path))) fail('stage-fight-extensions-unavailable');
  if (!assignment.instructions.every(content => content && session.systemPrompt.includes(content))) fail('stage-governing-context-missing');
  rememberSdkSession(manager);
  const binding = { ...assignment, sessionId: manager.getSessionId(), live: true };
  state.stages.set(manager, binding);
  return binding;
}

/** Return identity only for this live binding and its initialized workflow */
export function stageIdentity(session, parent) {
  const binding = stageBinding(session.sessionManager), workflow = sessionWorkflow(session.sessionManager);
  if (!binding?.live || binding.parent !== parent || binding.sessionId !== session.sessionManager.getSessionId() ||
      binding.checkout !== realpathSync(session.sessionManager.getCwd()) ||
      session.model?.provider !== binding.provider || session.model?.id !== binding.model || session.thinkingLevel !== binding.thinking ||
      workflow?.skill !== binding.skill || workflow.role !== binding.role || workflow.taskId !== binding.task || workflow.checkout !== binding.checkout) return null;
  return { task: binding.task, role: binding.role, attempt: binding.attempt };
}
