import type { AuthorityState } from '@/features/authority/AuthorityCache';

export type AccessRequirement =
  | Readonly<{ kind: 'public' | 'authenticated' }>
  | Readonly<{ kind: 'permissions'; all: readonly string[] }>;

export type AccessDecision = 'allowed' | 'forbidden' | 'unavailable' | 'invalid';

/**
 * Evaluates every ancestor and leaf requirement; only explicit public metadata bypasses authority
 * Client presentation is not server authorization
 */
export function evaluateAccess(state: AuthorityState, requirements: unknown): AccessDecision {
  if (!Array.isArray(requirements) || requirements.length === 0) return 'invalid';
  let authenticated = false;
  const permissions: string[] = [];
  for (const requirement of requirements as unknown[]) {
    if (typeof requirement !== 'object' || requirement === null || !('kind' in requirement))
      return 'invalid';
    switch (requirement.kind) {
      case 'public':
      case 'authenticated':
        if (Object.keys(requirement).length !== 1) return 'invalid';
        if (requirement.kind === 'authenticated') authenticated = true;
        break;
      case 'permissions':
        if (
          Object.keys(requirement).length !== 2 ||
          !('all' in requirement) ||
          !Array.isArray(requirement.all) ||
          requirement.all.length === 0 ||
          ![...(requirement.all as unknown[])].every(
            (name: unknown) =>
              typeof name === 'string' && /^[A-Za-z][A-Za-z0-9_-]{0,127}(?![\s\S])/.test(name)
          )
        )
          return 'invalid';
        authenticated = true;
        permissions.push(...(requirement.all as string[]));
        break;
      default:
        return 'invalid';
    }
  }
  if (!authenticated) return 'allowed';
  if (state.status !== 'authenticated') return 'unavailable';
  return permissions.every((permission) => state.principal.permissions.includes(permission))
    ? 'allowed'
    : 'forbidden';
}
