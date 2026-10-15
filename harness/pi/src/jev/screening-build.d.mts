import type { ScreeningProfiles } from './screening-profile.mjs';
import type { ScreeningDispatch, DispatchResult } from './screening-dispatch.mjs';
export type BuildRole = 'engineer' | 'review' | 'qa' | 'land';
export type BuildModel = { provider: string; model: string; thinking: string };
export declare const buildRoles: Readonly<Record<BuildRole, string>>;
export declare const defaultBuildModels: Readonly<Record<BuildRole, BuildModel>>;
export type BuildView = { build: string; task: string; models: Record<BuildRole, BuildModel>; expiresAt: number };
export declare class ScreeningBuild {
  constructor(profiles: ScreeningProfiles, options?: { dispatch?: ScreeningDispatch; now?: () => number });
  authorize(input: { cwd: string; task: string; context: string; models: Record<BuildRole, BuildModel>; repairs?: number; timeout?: number; taskPath?: string }): BuildView;
  inspect(cwd: string): (BuildView & { repairsUsed: number; repairsRemaining: number; held: boolean; attempts: { ticket: string; role: BuildRole; head: string; state: string }[] })[];
  stage(cwd: string, id: string, role: BuildRole, options?: { signal?: AbortSignal; observe?: (event: Record<string, unknown>) => void; validate?: () => void }): Promise<DispatchResult & { build: string; handoff: string | null; repairsUsed: number; repairsRemaining: number }>;
  report(cwd: string, markdown: string, session: string): Record<string, unknown>;
  handoff(cwd: string, build: string, ticket: string, offset: number, limit: number): { ticket: string; digest: string; session: string; role: string; head: string; verdict: string | null; offset: number; limit: number; text: string; trust: string };
}
