export type Json = null | boolean | number | string | Json[] | { [key: string]: Json };
export type QuestionIdentity = { id: string; version: string; rubricVersion: string };
type QuestionBase = QuestionIdentity & { instructions: string };
export type Question =
  | (QuestionBase & { type: 'yes-no' })
  | (QuestionBase & { type: 'choice'; choices: Record<string, string>; unknownChoice: string })
  | (QuestionBase & { type: 'score'; min: number; max: number; levels: string[] });
export type EvaluationRequest = {
  version: 'fight-judgments-v1';
  configuration: string;
  authorityRevision: string;
  policyVersion: string;
  source: { id: string; revision: string };
  state: string | Json[] | { [key: string]: Json };
  questions: Question[];
  requestMicroUsd: number;
  sessionMicroUsd: number;
};
export type Answer = QuestionIdentity & (
  | { type: 'yes-no'; status: 'answered'; yesProbability: number }
  | { type: 'choice'; status: 'answered' | 'unknown'; choice: string; confidence: number; probabilities: Record<string, number> }
  | { type: 'score'; status: 'answered'; score: number; min: number; max: number; providerIndex: number;
      confidence: number; probabilities: Record<string, number> }
);
export type EvaluationState = 'complete' | 'incomplete' | 'invalid-input' | 'payload-limit' | 'question-limit' | 'choice-limit'
  | 'stale-configuration' | 'disclosure-denied' | 'invalid-setup' | 'price-unqualified' | 'request-budget' | 'session-budget'
  | 'budget-unavailable' | 'credential-unavailable' | 'provider-denied' | 'billing' | 'timeout' | 'refusal' | 'provider-rejected'
  | 'provider-unavailable' | 'invalid-result' | 'absent-result' | 'cancelled' | 'price-bound-exceeded' | 'unsupported-host'
  | 'closed' | 'concurrency-limit';
export type Usage = { inputTokens: number; outputTokens: number; costUsd: number | null };
export type Provenance = { id: string; revision: string; digest: string };
export type Receipt = {
  evaluationId: string;
  callerId: string;
  state: EvaluationState;
  schema: string;
  configuration: string | null;
  configurationRevision: string | null;
  evaluator: string | null;
  endpoint: string;
  authorityRevision: string | null;
  policyVersion: string | null;
  source: Provenance | null;
  sessionDigest: string | null;
  requestDigest: string | null;
  questionDigest: string | null;
  questions: (QuestionIdentity & { type: Question['type'] })[];
  elapsedMs: number;
  usage: Usage | null;
  reservationRetained: boolean;
};
export type Evaluation = { state: EvaluationState; answers: Answer[] | null; receipt: Receipt };
export type DisclosureContext = Readonly<{
  callerId: string;
  sessionId: string | null;
  authorityRevision: string;
  configuration: string;
  source: Readonly<Provenance>;
  policyVersion: string;
  requestMicroUsd: number;
  sessionMicroUsd: number;
  requestDigest: string;
  questionDigest: string;
}>;
export type Consumer = Readonly<{
  evaluate(request: EvaluationRequest, options?: { signal?: AbortSignal }): Promise<Evaluation>;
  inspect(count?: number): Receipt[];
}>;
export type OperatorConfiguration = {
  endpoint: string; model: string; evaluator: string; price: string; revision: string; priceConfirmedAt: number;
  disclosure: 'fixed-synthetic-only' | 'authorized-supplied-state'; requestMicroUsd: number; sessionMicroUsd: number;
};
export type Ceilings = { bytes: number; questions: number; choices: number; timeoutMs: number };
export type Dependencies = {
  credentials: { read(signal: AbortSignal): Promise<Buffer> };
  transport: { submit(endpoint: string, payload: unknown, credential: Buffer, signal: AbortSignal): Promise<{ status: number; body: unknown }> };
  budget: { reserve(amount: number, ceiling: number): string; settle(ticket: string, amount: number): void };
  configuration(): OperatorConfiguration | null;
  scope?: { acquire(controller: AbortController): void; release(controller: AbortController): void };
  now?: () => number;
  platform?: string;
  ceilings?: Partial<Ceilings>;
  sessionId?: string | null;
};
export declare class Judgments {
  constructor(dependencies: Dependencies);
  consumer(callerId: string, authorize: (context: DisclosureContext) => boolean): Consumer;
  inspect(count?: number): Receipt[];
  invalidate(): void;
  close(): void;
}
export type Connection = { state: 'available'; configuration: {
  identity: string; revision: string; requestMicroUsd: number; sessionMicroUsd: number;
  endpoint: string; model: string; evaluator: string; disclosure: string;
} } & Consumer;
export type ConsumerConnection = {
  callerId: string;
  authorize(context: DisclosureContext): boolean;
  connected(capability: Connection | { state: 'missing-session' | 'missing-setup' | 'invalid-setup' | 'invalid-consumer' }): void;
};
export declare function prepareEvaluation(input: unknown, config: OperatorConfiguration, ceilings?: Ceilings): {
  request: EvaluationRequest; payload: Record<string, unknown>; sourceDigest: string; requestDigest: string; questionDigest: string;
};
export declare function evaluationUsage(body: unknown, config: OperatorConfiguration): Usage;
export declare function parseEvaluation(body: unknown, request: EvaluationRequest, config: OperatorConfiguration): { state: 'complete' | 'incomplete'; answers: Answer[] };
