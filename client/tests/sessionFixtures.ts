import { ApiClient } from '@/api/ApiClient';
import { AuthorityCache } from '@/features/authority/AuthorityCache';
import { OwnedSessionsService } from '@/features/sessions/OwnedSessionsService';

import { principal } from './authorityFixtures';

export const sessionWire = (overrides: Record<string, unknown> = {}) => ({
  session_id: crypto.randomUUID(),
  browser_family: 'Firefox',
  device_class: 'desktop',
  created_at: '2026-10-25T10:00:00+00:00',
  last_activity_at: '2026-10-25T11:00:00+00:00',
  idle_expires_at: '2026-10-26T11:00:00+00:00',
  absolute_expires_at: '2026-10-27T10:00:00+00:00',
  remembered: false,
  current: true,
  ...overrides
});
// R57-01: Genuine package/View projection protected by OwnedSessionsDataTest::testUsableNearExpiryProjection.
// Precise activity .100000 and expiry .900000 share a public second while the session is still usable.
export const nearExpirySessionWire = () =>
  sessionWire({
    browser_family: 'Unknown',
    device_class: 'unknown',
    created_at: '2026-10-23T12:00:00+00:00',
    last_activity_at: '2026-10-25T12:00:00+00:00',
    idle_expires_at: '2026-10-25T12:00:00+00:00',
    absolute_expires_at: '2026-10-25T12:00:00+00:00',
    current: false
  });
export const sessionPage = (
  records: unknown[] = [sessionWire()],
  overrides: Record<string, unknown> = {}
) => ({
  page: 1,
  per_page: 25,
  total_pages: 1,
  total_records: records.length,
  records,
  ...overrides
});
export const sessionsResponse = (data: unknown) =>
  new Response(JSON.stringify({ status: 'success', data }), {
    headers: { 'Content-Type': 'application/json', 'Cache-Control': 'no-store' }
  });
export function sessionDependencies(
  fetch: (url: string, init: RequestInit) => Promise<Response>,
  lifecycle: { accessRejected?: () => void; authorityRejected?: () => void } = {}
) {
  const cache = new AuthorityCache(() =>
    Promise.resolve({
      status: 'principal',
      principal: { ...principal, permissions: ['READ_OWN_SESSIONS'] }
    })
  );
  const service = new OwnedSessionsService(
    new ApiClient(
      { schemaVersion: 2, apiBasePath: '/api/v1', appDebug: false, swaggerAvailable: false },
      { fetch, accessTokens: { getAccessToken: () => 'synthetic.memory.only' } }
    ),
    lifecycle
  );
  return { service, cache };
}
