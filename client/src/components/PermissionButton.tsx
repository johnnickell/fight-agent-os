import { Button } from '@/components/Button';
import { evaluateAccess } from '@/features/authority/evaluateAccess';
import { useAuthority } from '@/features/authority/useAuthority';

import type { AuthorityCache, AuthorityScope } from '@/features/authority/AuthorityCache';
import type { ReactNode } from 'react';

/**
 * Applies explicit hide/disable policy and rechecks authority at activation, not just render
 * The server must authorize the operation independently
 */
export function PermissionButton({
  cache,
  permissions,
  denied,
  onAction,
  children
}: {
  cache: AuthorityCache;
  permissions: readonly string[];
  denied: 'hide' | 'disable';
  onAction: (scope: AuthorityScope) => void;
  children: ReactNode;
}) {
  const state = useAuthority(cache);
  const requirements = [{ kind: 'permissions', all: permissions }] as const;
  const allowed = evaluateAccess(state, requirements) === 'allowed';
  if (!allowed && denied === 'hide') return null;
  return (
    <Button
      disabled={!allowed}
      onClick={() => {
        if (evaluateAccess(cache.getSnapshot(), requirements) === 'allowed') {
          onAction(cache.captureScope());
        }
      }}
    >
      {children}
    </Button>
  );
}
