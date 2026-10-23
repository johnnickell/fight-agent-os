import { describe, expect, it } from 'vitest';

import { decodeRuntimeConfiguration } from '@/runtimeConfiguration';

const valid = {
  schema_version: 2,
  api_base_path: '/api/v1',
  app_debug: false,
  swagger_available: false
};

describe('public runtime configuration', () => {
  it.each([false, true])('decodes independent safe flags with debug %s', (debug) => {
    for (const available of [false, true]) {
      const config = decodeRuntimeConfiguration(
        JSON.stringify({ ...valid, app_debug: debug, swagger_available: available })
      );
      expect(config).toEqual({
        schemaVersion: 2,
        apiBasePath: '/api/v1',
        appDebug: debug,
        swaggerAvailable: available
      });
      expect(Object.isFrozen(config)).toBe(true);
    }
  });
  it.each([
    null,
    '',
    '{',
    'null',
    '[]',
    'true',
    '{}',
    JSON.stringify({ schema_version: 1, api_base_path: '/api/v1' }),
    JSON.stringify({ ...valid, schema_version: 1 }),
    JSON.stringify({ ...valid, schema_version: 3 }),
    JSON.stringify({ ...valid, schema_version: '2' }),
    JSON.stringify({ ...valid, api_base_path: 'https://example.invalid/api/v1' }),
    JSON.stringify({ ...valid, api_base_path: '//example.invalid/api/v1' }),
    JSON.stringify({ ...valid, api_base_path: '/api/v1/' }),
    JSON.stringify({ ...valid, app_debug: 'false' }),
    JSON.stringify({ ...valid, swagger_available: 1 }),
    JSON.stringify({ schema_version: 2, api_base_path: '/api/v1', app_debug: false }),
    JSON.stringify({ schema_version: 2, api_base_path: '/api/v1', swagger_available: false }),
    JSON.stringify({ ...valid, token: 'private-marker' }),
    JSON.stringify({ ...valid, roles: [] })
  ])('fails closed without salvaging %s', (source) => {
    expect(decodeRuntimeConfiguration(source)).toBeNull();
  });
});
