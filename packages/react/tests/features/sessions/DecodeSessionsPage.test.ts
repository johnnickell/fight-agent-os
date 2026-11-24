import { expect, it } from 'vitest';

import { decodeSessionsPage } from '../../../src/features/sessions/OwnedSessionsService.js';

const sessionWire = (overrides: Record<string, unknown> = {}) => ({
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
const sessionPage = (records = [sessionWire()], total = records.length) => ({
  page: 1,
  per_page: 25,
  total_pages: 1,
  total_records: total,
  records
});

it('allows one current session only in the signed-in user’s own page', () => {
  const wire = sessionPage();
  const self = decodeSessionsPage(1, 25, 'self');
  expect(self(wire)?.records).toMatchObject([{ current: true }]);
  expect(self(sessionPage([sessionWire(), sessionWire()]))).toBeNull();
  expect(decodeSessionsPage(1, 25, 'other')(wire)).toBeNull();
});

it('rejects invalid session counts and reversed rows in the shared decoder', () => {
  const decode = decodeSessionsPage(1, 25, 'other');
  const older = sessionWire({ current: false });
  const newer = sessionWire({ current: false, created_at: '2026-10-25T10:30:00+00:00' });
  expect(decode(sessionPage([older, newer]))?.records).toHaveLength(2);
  expect(decode(sessionPage([newer, older]))).toBeNull();
  expect(decode(sessionPage([older], 2))).toBeNull();
});
