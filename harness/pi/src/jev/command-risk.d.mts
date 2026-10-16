import type { ScreeningProfiles } from './screening-profile.mjs';
import type { Connection, ConsumerConnection, OperatorConfiguration } from './judgments.mjs';
export type CommandDecision = { outcome: string; reason: string; command: string; cwd: string };
export declare function secretBearingCommand(command: string): boolean;
export declare function commandRiskRecovery(reason: string): string;
export declare class CommandRisk {
  constructor(input: { profiles: ScreeningProfiles; configuration(): OperatorConfiguration | null;
    connect(authorize: ConsumerConnection['authorize']): Connection | { state: string } | undefined;
    sessionId: string; cwd: string;
    context?(): { phase?: string; shellPath?: string; commandPrefix?: string };
    observe?(event: Record<string, unknown>): void });
  assess(command: string, cwd: string, options?: { signal?: AbortSignal; validate?(): void;
    confirm?(decision: CommandDecision): Promise<boolean> }): Promise<{ outcome: string; reason: string; operationId: string }>;
}
