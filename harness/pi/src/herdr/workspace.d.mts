export class HerdrWorkspace {
  constructor(options: { cwd: string; sessionId: string; sessionFile?: string; pid?: number; env?: NodeJS.ProcessEnv });
  act(action: 'open' | 'close'): Promise<string>;
  shutdown(): Promise<void>;
}
