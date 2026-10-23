export type RuntimeConfiguration = Readonly<{
  schemaVersion: 2;
  apiBasePath: '/api/v1';
  appDebug: boolean;
  swaggerAvailable: boolean;
}>;

/**
 * Decodes only ADR 0004's public fields without exposing rejected input
 */
export function decodeRuntimeConfiguration(source: string | null): RuntimeConfiguration | null {
  if (source === null) return null;
  try {
    const value: unknown = JSON.parse(source);
    if (
      typeof value !== 'object' ||
      value === null ||
      Array.isArray(value) ||
      Object.keys(value).length !== 4 ||
      !('schema_version' in value) ||
      value.schema_version !== 2 ||
      !('api_base_path' in value) ||
      value.api_base_path !== '/api/v1' ||
      !('app_debug' in value) ||
      typeof value.app_debug !== 'boolean' ||
      !('swagger_available' in value) ||
      typeof value.swagger_available !== 'boolean'
    ) {
      return null;
    }
    return Object.freeze({
      schemaVersion: 2,
      apiBasePath: '/api/v1',
      appDebug: value.app_debug,
      swaggerAvailable: value.swagger_available
    });
  } catch {
    return null;
  }
}
