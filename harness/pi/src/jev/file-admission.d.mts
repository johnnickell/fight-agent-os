import type { ScreeningProfiles, TrustedIntake } from './screening-profile.mjs';
import type { FileSource, ScreenResult } from './file-screening.mjs';
import type { OperatorConfiguration } from './judgments.mjs';
export type AdmissionRead = { path: string; need?: string; offset?: number; limit?: number; receipt?: string; questionDigest?: string;
  evidence?: string; exception?: 'instruction' | 'required-evidence' };
export declare class FileAdmission {
  constructor(options: { profiles: ScreeningProfiles; configuration(): OperatorConfiguration | null; sessionId: string; cwd: string;
    source?: FileSource; observe?: (event: Record<string, unknown>) => void; intake?: TrustedIntake | null });
  close(): void;
  ingestNativeInstructions(files: { path: string; content: string }[]): void;
  accept(input: { need: string }, result: ScreenResult): void;
  locate(input: { path: string; symbol: string; need: string }): { evidence: string; pathId: string; offset: number; maxLines: number; kind: string };
  read(input: AdmissionRead): { text: string; admission: { kind: string; pathId: string; digest: string; offset: number; limit: number } };
}
