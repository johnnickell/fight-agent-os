import type { Connection, ConsumerConnection, OperatorConfiguration, Receipt, Answer } from './judgments.mjs';
export type FilePolicy = {
  version: 'file-screen-v1'; revision: string; configuration: string;
  allowedRoots: string[]; excludedPaths: string[]; excludedNames: string[];
  sessions: Record<string, { requestMicroUsd: number; sessionMicroUsd: number; expiresAt: number; providerDisclosure: boolean }>;
};
export type Snapshot = { canonical: string; text: string; bytes: number; digest: string; revision: string; id: string };
export type FileQuestion = { type: 'yes-no'; instructions: string } | {
  type: 'choice'; instructions: string; choices: Record<string, string>; unknownChoice: string; otherChoice: string; relevantChoices: string[];
};
export type ScreenInput = { need: string; question: FileQuestion } & ({ path: string } | { paths: string[] } | { glob: string });
export type ScreenResult = {
  status: string; threshold: 'positive' | 'not-positive' | 'unresolved'; policyVersion: string;
  judgment: Answer | null; source: { id: string; digest: string; revision: string; bytes: number } | null;
  coverage: { complete: boolean; evaluatedBytes?: number; totalBytes?: number | null; [key: string]: unknown }; receipt: Receipt | null;
  authority?: { policyDigest: string; revision: string; configuration: string; sessionDigest: string };
  operationId?: string;
  admission?: { receipt: string; questionDigest: string };
  cache?: { hit: boolean; upstreamCalls: number | null };
  accounting?: { sourceChecks?: number; sourceCheckBytes?: number; measuredUsage?: { inputTokens: number; outputTokens: number; costUsd: number } | null; [key: string]: unknown };
  results?: (ScreenResult & { index: number; path?: string })[];
  question?: { type: FileQuestion['type']; digest: string; relevantChoices: string[] | null };
};
export declare const filePolicyVersion: 'file-screen-v1';
export declare const sourceByteLimit: number;
export declare const evidenceByteLimit: number;
export declare function validateFilePolicy(value: unknown): FilePolicy;
export declare class FileSource {
  authorize(path: string, policy: FilePolicy): string;
  openDirectory(path: string, policy: FilePolicy): number;
  snapshot(path: string, policy: FilePolicy, options?: { sourceLimit?: number; maxBytes?: number; onRead?: (bytes: number) => void }): Snapshot;
}
export declare class FileScreening {
  constructor(dependencies: { settings: { load(): unknown }; configuration(): OperatorConfiguration | null;
    connect(authorize: ConsumerConnection['authorize']): Connection | { state: string } | undefined;
    sessionId: string; cwd: string; source?: FileSource; now?: () => number; platform?: string; observe?: (event: Record<string, unknown>) => void });
  availability(): string;
  close(): void;
  cacheInfo(): { entries: number; bytes: number };
  screen(input: unknown, options?: { signal?: AbortSignal }): Promise<ScreenResult>;
}
