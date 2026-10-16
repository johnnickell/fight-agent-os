import type { RepositoryScope } from './screening-profile.mjs';
export declare const skillEffects: Readonly<Record<string, string>>;
export type WorkflowScope = {taskId?: string | null; checkout?: string | null; mode?: string};
export declare class WorkflowAccess {
  role: string;
  revision: number;
  skill: string;
  taskId: string | null;
  checkout: string | null;
  mode: string;
  readonly launcher: string | null;
  constructor(phase?: string, scope?: WorkflowScope);
  static restore(options: { phase?: string; sessionId: string; entries: { type: string; customType?: string; data?: unknown }[]; parentSession?: string; taskId?: string; checkout?: string; mode?: string }): WorkflowAccess;
  checkpoint(sessionId: string, role?: string): { sessionId: string; role: string; launcher: string | null; skill?: string; taskId?: string | null; checkout?: string | null; mode?: string };
  select(value: string, persist?: (role: string) => void): void;
  input(text: string, source: string, persist?: (role: string) => void): true | undefined;
  assertWrite(path: string, root: string | (RepositoryScope & {checkout: string; taskId: string | null})): void;
}
