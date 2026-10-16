import type { ScreeningProfiles } from './screening-profile.mjs';
import type { FileSource } from './file-screening.mjs';

export type ImageRead = { path: string; need?: string; exception?: string; receipt?: string; evidence?: string;
  questionDigest?: string; offset?: number; limit?: number };
export declare function isImagePath(value: unknown, cwd: string): boolean;
export declare class ImageAdmission {
  constructor(options: { profiles: ScreeningProfiles; sessionId: string; cwd: string; resources?: string[]; source?: FileSource });
  close(): void;
  read(input: ImageRead): { data: Buffer; path: string; mimeType: string;
    admission: { kind: 'image'; pathId: string; digest: string; bytes: number } };
}
