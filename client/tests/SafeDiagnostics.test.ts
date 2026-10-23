import { afterEach, expect, it, vi } from 'vitest';

import { safeDiagnostics } from '@/SafeDiagnostics';

afterEach(() => vi.restoreAllMocks());
it('suppresses optional diagnostics when disabled and never logs unknown values', () => {
  const debug = vi.spyOn(console, 'debug').mockImplementation(() => {});
  const configuration = {
    schemaVersion: 2,
    apiBasePath: '/api/v1',
    appDebug: false,
    swaggerAvailable: true
  } as const;
  safeDiagnostics(configuration)('documentation-ready');
  expect(debug).not.toHaveBeenCalled();
  const enabled = safeDiagnostics({ ...configuration, appDebug: true });
  enabled('documentation-unavailable');
  expect(debug).toHaveBeenCalledExactlyOnceWith('[Fight] documentation-unavailable');
  enabled('private-marker' as 'documentation-ready');
  expect(debug).toHaveBeenCalledTimes(1);
});
