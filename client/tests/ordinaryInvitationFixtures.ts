import { ApiClient } from '@/api/ApiClient';
import { InvitationIssuanceService } from '@/features/invitations/InvitationIssuanceService';

import { configuration, jsonResponse } from './apiFixtures';
import { authenticatedCache, principal } from './authorityFixtures';

export function issuanceData() {
  const userId = crypto.randomUUID();
  return {
    user_id: userId,
    assignment: 'confirmed',
    audit: 'confirmed',
    delivery: {
      user_id: userId,
      activation_delivery_id: crypto.randomUUID(),
      status: 'retry_pending',
      expires_at: '2027-01-01T00:00:00+00:00'
    }
  };
}
export function issuanceResponse(data: unknown = issuanceData(), status = 201) {
  return jsonResponse({ status: 'success', data }, status);
}
export function ordinaryInvitationDependencies(
  fetch: (url: string, init: RequestInit) => Promise<Response>,
  permissions = ['INVITE_USERS', 'READ_USERS']
) {
  const cache = authenticatedCache({ ...principal, permissions });
  const api = new ApiClient(configuration, {
    fetch,
    accessTokens: { getAccessToken: () => 'synthetic.memory.only' }
  });
  return {
    cache,
    api,
    create: (lifecycle: ConstructorParameters<typeof InvitationIssuanceService>[2]) =>
      new InvitationIssuanceService(api, cache, lifecycle)
  };
}
