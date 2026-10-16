export declare class WorkflowAccess {
  role: string;
  revision: number;
  constructor(phase?: string);
  select(value: string): void;
  input(text: string, source: string): void;
  assertWrite(path: string, root: string): void;
}
