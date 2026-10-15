import type { ScreeningProfiles } from './screening-profile.mjs';
import type { ScreeningDispatch, DispatchResult } from './screening-dispatch.mjs';
export type BuildRole = 'engineer' | 'review' | 'qa' | 'land';
export type BuildWorkOperation = 'identity' | 'changes' | 'evidence' | 'check' | 'commit' | 'publication';
export type BuildModel = { provider: string; model: string; thinking: string };
export declare const buildRoles: Readonly<Record<BuildRole, string>>;
export declare const defaultBuildModels: Readonly<Record<BuildRole, BuildModel>>;
export type BuildView = { build: string; task: string; models: Record<BuildRole, BuildModel>; expiresAt: number };
export declare class ScreeningBuild {
  constructor(profiles: ScreeningProfiles, options?: { dispatch?: ScreeningDispatch; now?: () => number });
  authorize(input: { cwd: string; task: string; context: string; models: Record<BuildRole, BuildModel>; repairs?: number; timeout?: number; taskPath?: string }): BuildView;
  inspect(cwd: string): (BuildView & { repairsUsed: number; repairsRemaining: number; held: boolean; attempts: { ticket: string; role: BuildRole; head: string; state: string }[]; externalWork: { request: string; ticket: string; role: BuildRole; operation: BuildWorkOperation; state: 'pending' | 'uncertain' }[] })[];
  stage(cwd: string, id: string, role: BuildRole, options?: { signal?: AbortSignal; observe?: (event: Record<string, unknown>) => void; validate?: () => void }): Promise<DispatchResult & { build: string; handoff: string | null; repairsUsed: number; repairsRemaining: number }>;
  work(cwd: string, id: string, input: { operation: BuildWorkOperation; evidence?: string; side?: 'base' | 'current'; offset?: number; limit?: number }, options?: { signal?: AbortSignal; validate?: () => void; onPending?: (event: Record<string, unknown>) => void; wait?: boolean }): Promise<Record<string, unknown>>;
  pendingWork(cwd: string): Record<string, unknown>[];
  completeWork(cwd: string, request: string, result: Record<string, unknown>): Promise<Record<string, unknown>>;
  report(cwd: string, markdown: string, session: string): Record<string, unknown>;
  handoff(cwd: string, build: string, ticket: string, offset: number, limit: number): { ticket: string; digest: string; session: string; role: string; head: string; base: string; status: string; sourceDigest: string; verdict: string | null; offset: number; limit: number; text: string; trust: string };
}
