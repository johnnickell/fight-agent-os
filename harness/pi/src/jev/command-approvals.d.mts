export declare class CommandApprovals {
  constructor(active?: (active: boolean) => void);
  run(open: (signal: AbortSignal) => Promise<boolean>, options?: { signal?: AbortSignal; validate?(): void }): Promise<boolean>;
  cancel(): void;
}
