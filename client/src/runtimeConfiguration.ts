export type RuntimeConfiguration = Readonly<{
  schemaVersion: 1;
  apiBasePath: '/api/v1';
}>;

/**
 * Decodes only ADR 0004's public fields without exposing rejected input
 */
export function decodeRuntimeConfiguration(
  source: string | null,
): RuntimeConfiguration | null {
  if (source === null) return null;
  try {
    const value: unknown = JSON.parse(source);
    if (
      typeof value !== 'object' ||
      value === null ||
      Array.isArray(value) ||
      Object.keys(value).length !== 2 ||
      !('schema_version' in value) ||
      value.schema_version !== 1 ||
      !('api_base_path' in value) ||
      value.api_base_path !== '/api/v1'
    ) {
      return null;
    }
    return Object.freeze({ schemaVersion: 1, apiBasePath: '/api/v1' });
  } catch {
    return null;
  }
}
