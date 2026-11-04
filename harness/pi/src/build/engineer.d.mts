import type { ExtensionAPI, ExtensionContext } from '@earendil-works/pi-coding-agent';
export type EngineerRequest = {
  task: string; role: 'engineer'; checkout: string; head: string; attempt: string;
  provider: string; model: string; thinking: string; timeout: number;
};
export type EngineerOptions = {
  selected: boolean; registry: unknown; profile: unknown; signal?: AbortSignal;
  dispatched?: () => void; onShutdown?: (cancel: () => void) => () => void; progress?: (value: unknown) => void;
};
export type EngineerOutcome = {
  route: string; id: string | null; parent: string; task: string; attempt: string;
  state: string; reason: string | null; acceptance: false; admitted?: boolean; executionSettled?: boolean;
};
export declare function prepareEngineer(ctx: ExtensionContext, request: EngineerRequest, options: EngineerOptions): unknown;
export declare function runEngineer(pi: ExtensionAPI, ctx: ExtensionContext, request: EngineerRequest, options: EngineerOptions): Promise<EngineerOutcome>;
