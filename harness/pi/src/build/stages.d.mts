import type { ExtensionAPI, ExtensionContext } from '@earendil-works/pi-coding-agent';
import type { StageRole } from './authority.mjs';
export type StageRequest = {
  task: string; role: StageRole; checkout: string; head: string; attempt: string;
  base: string; branch: string; context: string; record: string;
  provider: string; model: string; thinking: string; timeout: number;
  repair: boolean; repairsUsed: number; repairLimit: number;
};
export type StageOptions = {
  registry: unknown; profile: unknown; signal?: AbortSignal;
  dispatched?: (identity: unknown) => void; onShutdown?: (cancel: () => void) => () => void; progress?: (value: unknown) => void;
};
export type StageOutcome = {
  route: string; id: string | null; parent: string; task: string; attempt: string;
  state: string; reason: string | null; acceptance: false; admitted?: boolean; executionSettled?: boolean;
};
export declare function prepareStage(ctx: ExtensionContext, request: StageRequest, options: StageOptions): unknown;
export declare function runStage(pi: ExtensionAPI, ctx: ExtensionContext, request: StageRequest, options: StageOptions): Promise<StageOutcome>;
