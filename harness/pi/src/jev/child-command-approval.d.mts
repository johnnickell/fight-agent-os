import type { CommandDecision } from './command-risk.mjs';
export declare const childApprovalEnvironment = 'FIGHT_CHILD_COMMAND_APPROVAL';
export type ChildCommandDecision = CommandDecision & { task: string | null; role: string; session: string };
export type ChildCommandRequest = ChildCommandDecision & { id: string; task: string };
export declare function openChildApprovals(options: {
  confirm(request: ChildCommandRequest, signal: AbortSignal, validate: () => void): Promise<boolean>;
  validate(request: ChildCommandRequest): void;
  signal?: AbortSignal;
  timeoutMs?: number;
}): Promise<{ environment: string; close(): void }>;
export declare function requestChildApproval(environment: string | undefined, decision: ChildCommandDecision,
  options?: { signal?: AbortSignal; timeoutMs?: number }): Promise<boolean>;
