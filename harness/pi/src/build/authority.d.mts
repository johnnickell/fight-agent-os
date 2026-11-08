import type { AgentSession, ExtensionContext } from '@earendil-works/pi-coding-agent';
import type { WorkflowAccess } from '../workflow/access.mjs';
export type StageRole = 'engineer' | 'reviewer' | 'qa' | 'land';
export declare const stageDefinitions: Readonly<Record<StageRole, { skill: string; agent: string; effects: readonly string[] }>>;
export type StageAssignment = {
  parent: string; task: string; role: StageRole; skill: string; effects: readonly string[];
  checkout: string; attempt: string; provider: string; model: string; thinking: string;
  head: string; base: string; report: string | null;
  requiredExtensions: string[]; instructions: string[]; sessionDir: string;
};
export type StageBinding = StageAssignment & { sessionId: string; live: boolean; admitted?: boolean; failure?: string };
export declare function rememberSdkSession(manager: object): void;
export declare function isSdkSession(manager: object): boolean;
export declare function publishWorkflow(manager: object, workflow: WorkflowAccess): void;
export declare function sessionWorkflow(manager: object): WorkflowAccess | undefined;
export declare function stageBinding(manager: object): StageBinding | undefined;
export declare function closeWorkflow(manager: object): void;
export declare function bindStageSession(session: AgentSession, assignment: StageAssignment): StageBinding;
export declare function stageIdentity(session: {
  sessionManager: ExtensionContext['sessionManager']; model?: { provider: string; id: string }; thinkingLevel?: string;
}, parent: string): { task: string; role: StageRole; attempt: string } | null;
