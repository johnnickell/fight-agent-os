import type { ScreeningProfiles, IntakeRequest } from './screening-profile.mjs';
export declare const buildMonitor: string;
export declare function safeChildObservation(line: string): Record<string, unknown> | null;
export type DispatchInput = { cwd: string; request: string; task: string; role: string; provider: string; model: string;
  thinking: string; timeout?: number; intake?: IntakeRequest | null };
export type DispatchPlan = { version: 1; id: string; cwd: string; authority: string; task: string; role: string; provider: string;
  model: string; thinking: string; timeout: number; expiresAt: number; monitor: string; monitorDigest: string;
  requestDigest: string; intake: string | null };
export type DispatchResult = { ticket: string; state: 'completed' | 'incomplete'; observations: number; childOutput: string;
  provider: string; model: string; thinking: string };
export declare class ScreeningDispatch {
  constructor(profiles: ScreeningProfiles, dependencies?: { now?: () => number; monitor?: string });
  prepare(input: DispatchInput): DispatchPlan & { requestPath: string; attempt: string };
  inspect(cwd: string): { ticket: string; task: string; role: string; provider: string; model: string; thinking: string;
    expiresAt: number; state: 'available' | 'consumed-or-uncertain' | 'completed' | 'incomplete' }[];
  claim(cwd: string, id: string): DispatchPlan & { directory: string; attempt: string };
  run(cwd: string, id: string, options?: { signal?: AbortSignal; observe?: (event: Record<string, unknown>) => void; validate?: () => void }): Promise<DispatchResult>;
}
