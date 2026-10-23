import { useSyncExternalStore } from 'react';

import type { AuthorityCache } from '@/features/authority/AuthorityCache';

/**
 * Subscribes to the sole cache owner without mirroring its state in React
 */
export function useAuthority(cache: AuthorityCache) {
  return useSyncExternalStore(cache.subscribe, cache.getSnapshot);
}

/** Subscribes to same-context display, never use this value to admit protected work */
export function useAuthorityPresentation(cache: AuthorityCache) {
  return useSyncExternalStore(cache.subscribe, cache.getPresentationSnapshot);
}
