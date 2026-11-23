import { useEffect, useState } from 'react';

import { evaluateAccess } from '@fight/react/account';
import { useAuthority, useAuthorityPresentation } from '@fight/react/account';
import { AuthorityStatus } from '@fight/react/controls';

import type { IntendedRoute } from '@/routes/IntendedRoute';
import type { AuthorityCache, AuthorityScope } from '@fight/react/account';
import type { AccessRequirement } from '@fight/react/account';
import type { ComponentType, ReactNode } from 'react';

export type RegisteredRoute = Readonly<{
  pathname: string;
  /**
   * Includes ordered ancestor requirements followed by the leaf; no implicit public default
   */
  requirements: readonly AccessRequirement[];
  /**
   * Opts in static paths only. Enumerate safe values or canonicalize bounded, non-sensitive options.
   */
  intendedRoute?:
    | Readonly<Record<string, readonly string[]>>
    | ((search: string) => Readonly<{ search: string; invalid: boolean }>);
}>;

/**
 * Admits new outlets only after fresh access succeeds; retains an admitted same-context display during checks
 * Loaders belong inside the outlet and must check scope before dispatch and before adopting any result
 */
export function GuardedRoute({
  cache,
  route,
  outlet: Outlet,
  children,
  intent,
  location
}: {
  cache: AuthorityCache;
  route: RegisteredRoute;
  outlet?: ComponentType<{ scope: AuthorityScope }>;
  children?: ReactNode;
  intent?: IntendedRoute;
  location?: string;
}) {
  const state = useAuthority(cache);
  const presentation = useAuthorityPresentation(cache);
  const decision = evaluateAccess(state, route.requirements);
  const [admitted, setAdmitted] = useState<string | null>(null);
  const identity = presentation.status === 'authenticated' ? presentation.principal.userId : null;
  const key = identity === null ? null : `${identity}:${route.pathname}`;
  const retain =
    key !== null &&
    admitted === key &&
    evaluateAccess(presentation, route.requirements) === 'allowed';
  // Remember admission, not a principal. A newly mounted/changed route cannot borrow stale display.
  const nextAdmission = decision === 'allowed' || retain ? key : null;
  if (nextAdmission !== admitted) setAdmitted(nextAdmission);
  useEffect(() => {
    if (decision === 'unavailable' && state.status === 'anonymous' && state.reason === 'absent') {
      intent?.capture(location ?? route.pathname);
    }
  }, [decision, state, intent, location, route.pathname]);
  if (decision !== 'allowed' && !retain) {
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
  return Outlet ? <Outlet scope={cache.captureScope()} /> : children;
}
