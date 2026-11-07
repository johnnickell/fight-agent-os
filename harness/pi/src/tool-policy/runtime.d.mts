import type { ExtensionAPI } from '@earendil-works/pi-coding-agent';

export function supportsRuntime(runtime: unknown): boolean;
export function trackRuntimeHooks(pi: ExtensionAPI): {
  on: ExtensionAPI['on'];
  available(): boolean;
};
