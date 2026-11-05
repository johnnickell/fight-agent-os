import type { AgentSession, ExtensionContext } from '@earendil-works/pi-coding-agent';
import type { WorkflowAccess } from '../jev/workflow-access.mjs';
export declare const engineerEffects: readonly string[];
export type EngineerAssignment = {
  parent: string; task: string; role: 'engineer'; skill: 'work'; effects: readonly string[];
  checkout: string; attempt: string; provider: string; model: string; thinking: string;
  requiredExtensions: string[]; instructions: string[]; sessionRoots: string[];
};
export type EngineerBinding = EngineerAssignment & { sessionId: string; live: boolean; admitted?: boolean; failure?: string };
export declare function rememberSdkSession(manager: object): void;
export declare function isSdkSession(manager: object): boolean;
export declare function publishWorkflow(manager: object, workflow: WorkflowAccess): void;
export declare function sessionWorkflow(manager: object): WorkflowAccess | undefined;
export declare function selectEngineerRoute(manager: Pick<ExtensionContext['sessionManager'], 'getSessionId'>, selected: boolean): void;
export declare function engineerRouteSelected(manager: Pick<ExtensionContext['sessionManager'], 'getSessionId'>): boolean;
export declare function stageBinding(manager: object): EngineerBinding | undefined;
export declare function closeWorkflow(manager: object): void;
export declare function bindEngineerSession(session: AgentSession, assignment: EngineerAssignment): EngineerBinding;
export declare function engineerIdentity(session: {
  sessionManager: ExtensionContext['sessionManager']; model?: { provider: string; id: string }; thinkingLevel: string;
}, parent: string): { task: string; role: 'engineer'; attempt: string } | null;
