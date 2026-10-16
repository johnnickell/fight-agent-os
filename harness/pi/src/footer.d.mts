import type { SessionEntry } from '@earendil-works/pi-coding-agent';

export function footerStatusRows(statuses: ReadonlyMap<string, string>): string[];
export function collectFooterUsage(entries: readonly SessionEntry[]): {
  input: number; output: number; cacheRead: number; cacheWrite: number; cost: number; cacheHitRate: number | undefined;
};
export function footerTokens(count: number): string;
