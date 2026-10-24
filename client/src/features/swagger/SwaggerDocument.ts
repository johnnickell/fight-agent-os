export type SwaggerDocument = Readonly<{
  openapi: '3.0.3';
  info: Readonly<{ title: string; version: string }>;
  paths: Readonly<Record<string, Readonly<Record<string, unknown>>>>;
}>;

/** Validates the private document before any library resolver sees it. */
export function decodeSwaggerDocument(value: unknown): SwaggerDocument | null {
  if (
    !object(value) ||
    value.openapi !== '3.0.3' ||
    !object(value.info) ||
    typeof value.info.title !== 'string' ||
    typeof value.info.version !== 'string' ||
    !object(value.paths) ||
    typeof value['x-source-sha256'] !== 'string' ||
    !/^[a-f0-9]{64}$/.test(value['x-source-sha256'])
  )
    return null;
  const local = (node: unknown, depth = 0): boolean => {
    if (depth > 128) return false;
    if (Array.isArray(node)) return node.every((child: unknown) => local(child, depth + 1));
    if (!object(node)) return true;
    for (const [key, child] of Object.entries(node)) {
      if (key === '$ref' && (typeof child !== 'string' || !child.startsWith('#/'))) return false;
      if (
        key === 'servers' &&
        (!Array.isArray(child) ||
          !child.every((server: unknown) => object(server) && server.url === '/'))
      )
        return false;
      if (!local(child, depth + 1)) return false;
    }
    return true;
  };
  if (
    !local(value) ||
    !Object.entries(value.paths).every(
      ([path, operation]) => /^\/api\/v1\/[a-zA-Z0-9_/{}-]+$/.test(path) && object(operation)
    )
  )
    return null;
  // The original is not a cache or authorization snapshot. Each mounted viewer owns and retires it.
  return value as SwaggerDocument;
}

function object(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null && !Array.isArray(value);
}

/** Every other operation is documentation-only until its transport/display contract is qualified. */
export function supportsSwaggerOperation(path: string, method: string): boolean {
  return (
    (method === 'get' &&
      [
        '/api/v1/me',
        '/api/v1/roles',
        '/api/v1/roles/{roleId}',
        '/api/v1/permissions',
        '/api/v1/permissions/{permissionId}'
      ].includes(path)) ||
    (method === 'post' && path === '/api/v1/roles')
  );
}
