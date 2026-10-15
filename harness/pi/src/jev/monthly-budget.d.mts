export type LegacySnapshot = { digest: string; files: { file: string; digest: string; settled: number; pending: number }[]; exposureMicroUsd: number };
export type LegacyState = { held: boolean; snapshotDigest: string; exposureMicroUsd: number; chargedMicroUsd: number; unimportedExposureMicroUsd: number; pendingMicroUsd: number };
export type MonthlyStatus = { month: string; resetsAt: string; timeZone: string; ceilingMicroUsd: number; settledMicroUsd: number;
  reservedMicroUsd: number; newRequestReservedMicroUsd: number; availableMicroUsd: number; outstandingAllMonths: { count: number; reserved: number };
  pending: { id: string; session: string; month: string; reserved: number; phase: string; created: number }[]; halted: boolean;
  legacy: LegacyState; session: { used: number; reserved: number; inherited: number } | null };
export declare const monthlyCeilingMicroUsd: 10000000;
export declare function budgetPeriod(time: number): { month: string; resetsAt: string };
export declare class MonthlyLedger {
  constructor(directory?: string, now?: () => number);
  directory: string;
  path: string;
  legacySnapshot(): LegacySnapshot;
  importLegacy(snapshotDigest: string, evidence: string): LegacyState;
  legacyPending(): { digest: string; file: string; pending: number; month: string }[];
  reconcileLegacy(digest: string, amount: number, month: string, evidence: string): void;
  reserve(sessionId: string, amount: number, sessionCeiling: number, inheritedExposure?: number): string;
  dispatch(id: string): void;
  settle(id: string, amount: number, evidence?: string | null): void;
  inspect(sessionId?: string | null): MonthlyStatus;
}
export declare class ProfileBudget {
  constructor(sessionId: string, directory?: string, ledger?: MonthlyLedger);
  inheritedExposure(): number;
  inspect(ceiling: number): Omit<MonthlyStatus, 'session'> & { session: { ceilingMicroUsd: number; usedMicroUsd: number;
    inheritedMicroUsd: number; reservedMicroUsd: number; availableMicroUsd: number } };
  reserve(amount: number, ceiling: number): string;
  dispatch(id: string): void;
  settle(id: string, amount: number): void;
}
