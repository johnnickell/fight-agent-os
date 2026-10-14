/**
 * Contains only the approved display identity and exact authority names
 */
export type CurrentPrincipal = Readonly<{
  userId: string;
  email: string;
  roles: readonly string[];
  permissions: readonly string[];
}>;

function names(value: unknown): value is string[] {
  return (
    Array.isArray(value) &&
    value.length <= 1024 &&
    [...(value as unknown[])].every(
      (name: unknown) =>
        typeof name === 'string' && /^[A-Za-z][A-Za-z0-9_-]{0,127}(?![\s\S])/.test(name)
    ) &&
    new Set(value).size === value.length
  );
}

/**
 * Validates the injected model atomically, not a JWT or the future HTTP View
 */
export function decodeCurrentPrincipal(value: unknown): CurrentPrincipal | null {
  if (typeof value !== 'object' || value === null || Array.isArray(value)) return null;
  const keys = Object.keys(value);
  if (
    keys.length !== 4 ||
    !keys.every((key) => ['userId', 'email', 'roles', 'permissions'].includes(key)) ||
    !('userId' in value) ||
    !('email' in value) ||
    !('roles' in value) ||
    !('permissions' in value) ||
    typeof value.userId !== 'string' ||
    !/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}(?![\s\S])/.test(value.userId) ||
    typeof value.email !== 'string' ||
    value.email.length > 254 ||
    value.email !== value.email.toLowerCase() ||
    !/^[^\s@\p{Cc}]+@[^\s@\p{Cc}]+\.[^\s@\p{Cc}]+(?![\s\S])/u.test(value.email) ||
    !names(value.roles) ||
    !names(value.permissions)
  )
    return null;
  return Object.freeze({
    userId: value.userId,
    email: value.email,
    roles: Object.freeze([...value.roles]),
    permissions: Object.freeze([...value.permissions])
  });
}
