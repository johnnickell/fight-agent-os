import { describe, expect, it } from 'vitest';
import { decodeRuntimeConfiguration } from './runtimeConfiguration';

describe('public runtime configuration', () => {
  it('maps the exact same-origin schema to an immutable model', () => {
    const config = decodeRuntimeConfiguration(
      '{"schema_version":1,"api_base_path":"/api/v1"}',
    );
    expect(config).toEqual({ schemaVersion: 1, apiBasePath: '/api/v1' });
    expect(Object.isFrozen(config)).toBe(true);
  });

  it.each([
    null,
    '',
    '{',
    'null',
    'true',
    '1',
    '"/api/v1"',
    '[]',
    '{}',
    '{"schema_version":1}',
    '{"api_base_path":"/api/v1"}',
    '{"schema_version":"1","api_base_path":"/api/v1"}',
    '{"schema_version":2,"api_base_path":"/api/v1"}',
    '{"schema_version":1,"api_base_path":null}',
    '{"schema_version":1,"api_base_path":"https://example.invalid/api/v1"}',
    '{"schema_version":1,"api_base_path":"//example.invalid/api/v1"}',
    '{"schema_version":1,"api_base_path":"/api/v2"}',
    '{"schema_version":1,"api_base_path":"/api/v1/"}',
    '{"schema_version":1,"api_base_path":"/api/v1","roles":[]}',
    '{"schema_version":1,"api_base_path":"/api/v1","__proto__":{}}',
  ])('rejects missing, malformed, or unapproved input %#', (source) => {
    expect(decodeRuntimeConfiguration(source)).toBeNull();
  });
});
