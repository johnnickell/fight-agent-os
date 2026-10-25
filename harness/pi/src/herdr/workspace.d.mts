export class HerdrWorkspace {
  constructor(options: { cwd: string; sessionId: string; sessionFile?: string | null; pid?: number; env?: NodeJS.ProcessEnv; selectLogs?: () => Promise<{ tests: { main: string; context: string; project: string; service: string }[]; shared: { main: string; context: string; project: string; service: string } | null } | null>; currentContext?: () => { cwd: string; sessionId: string; sessionFile: string | null } });
  invalidate(): void;
  act(action: 'open' | 'close'): Promise<string>;
  shutdown(): Promise<void>;
}
