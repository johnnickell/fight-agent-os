import { useEffect } from 'react';

import { AuthorityStatus } from '@/components/AuthorityStatus';
import { evaluateAccess } from '@/features/authority/evaluateAccess';
import { useAuthority } from '@/features/authority/useAuthority';

import type { AuthorityCache, AuthorityScope } from '@/features/authority/AuthorityCache';
import type { AccessRequirement } from '@/features/authority/evaluateAccess';
import type { IntendedRoute } from '@/routes/IntendedRoute';
import type { ComponentType } from 'react';

export type RegisteredRoute = Readonly<{
  pathname: string;
  /**
   * Includes ordered ancestor requirements followed by the leaf; no implicit public default
   */
  requirements: readonly AccessRequirement[];
  /**
   * Opts in static paths only; each query key enumerates its non-sensitive values
   */
  intendedRoute?: Readonly<Record<string, readonly string[]>>;
}>;

/**
 * Mounts the outlet (including lazy imports and its protected loaders) only after access succeeds
 * Loaders belong inside the outlet, never above this guard; fence their results with scope
 */
export function GuardedRoute({
  cache,
  route,
  outlet: Outlet,
  intent,
  location
}: {
  cache: AuthorityCache;
  route: RegisteredRoute;
  outlet: ComponentType<{ scope: AuthorityScope }>;
  intent?: IntendedRoute;
  location?: string;
}) {
  const state = useAuthority(cache);
  const decision = evaluateAccess(state, route.requirements);
  useEffect(() => {
    if (decision === 'unavailable' && state.status === 'anonymous' && state.reason === 'absent') {
      intent?.capture(location ?? route.pathname);
    }
  }, [decision, state, intent, location, route.pathname]);
  if (decision !== 'allowed') {
    return (
      <AuthorityStatus
        state={state}
        decision={decision}
        {...(cache.canRetry()
          ? {
              onRetry: () => {
                void cache.load();
              }
            }
          : {})}
      />
    );
  }
  return <Outlet scope={cache.captureScope()} />;
}
