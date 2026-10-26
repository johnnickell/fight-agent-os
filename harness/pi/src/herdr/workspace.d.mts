export class HerdrWorkspace {
  constructor(options: { cwd: string; sessionId: string; sessionFile?: string | null; pid?: number; env?: NodeJS.ProcessEnv; currentContext?: () => { cwd: string; sessionId: string; sessionFile: string | null } });
  invalidate(): void;
  act(action: 'open' | 'close'): Promise<string>;
  shutdown(): Promise<void>;
}
