export type StageRole = 'engineer' | 'reviewer' | 'qa' | 'land';
export declare function normalizeRole(value: unknown): string;
export declare function stageRole(value: unknown): StageRole | null;
