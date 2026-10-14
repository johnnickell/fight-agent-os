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
export type ScreenResult = {
  status: string; threshold: 'positive' | 'not-positive' | 'unresolved'; policyVersion: string;
  judgment: Answer | null; source: { id: string; digest: string; revision: string; bytes: number } | null;
  coverage: { complete: boolean; evaluatedBytes: number; totalBytes: number | null }; receipt: Receipt | null;
  question?: { type: FileQuestion['type']; digest: string; relevantChoices: string[] | null };
};
export declare const filePolicyVersion: 'file-screen-v1';
export declare const sourceByteLimit: number;
export declare function validateFilePolicy(value: unknown): FilePolicy;
export declare class FileSource { snapshot(path: string, policy: FilePolicy): Snapshot; }
export declare class FileScreening {
  constructor(dependencies: { settings: { load(): unknown }; configuration(): OperatorConfiguration | null;
    connect(authorize: ConsumerConnection['authorize']): Connection | { state: string } | undefined;
    sessionId: string; cwd: string; source?: FileSource; now?: () => number; platform?: string });
  availability(): string;
  screen(input: { path: string; need: string; question: FileQuestion }, options?: { signal?: AbortSignal }): Promise<ScreenResult>;
}
