export declare class WorkflowAccess {
  role: string;
  revision: number;
  readonly launcher: string | null;
  constructor(phase?: string);
  static restore(options: { phase?: string; sessionId: string; entries: { type: string; customType?: string; data?: unknown }[]; parentSession?: string }): WorkflowAccess;
  checkpoint(sessionId: string, role?: string): { sessionId: string; role: string; launcher: string | null };
  select(value: string, persist?: (role: string) => void): void;
  input(text: string, source: string, persist?: (role: string) => void): void;
  assertWrite(path: string, root: string): void;
}
