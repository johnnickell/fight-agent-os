import { StrictMode } from 'react';

import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

import { ApiClient } from '@/api/ApiClient';
import { CsrfProofService } from '@/features/auth/CsrfProofService';
import { CurrentSessionControls } from '@/features/auth/CurrentSessionControls';
import { MemoryAccessToken } from '@/features/auth/MemoryAccessToken';
import { SessionService } from '@/features/auth/SessionService';

import { jsonResponse } from '../../activationFixtures';
import { deferred } from '../../apiFixtures';

describe('current-session logout presentation', () => {
  it.each([true, false])(
    'clears local authority, suppresses repeats and focuses the honest terminal outcome (%s)',
    async (ok) => {
      const pending = deferred<Response>();
      const fetch = vi.fn().mockImplementation((url: string) => {
        const expiry = Math.floor(Date.now() / 1000) + 900;
        return url.endsWith('/csrf')
          ? Promise.resolve(
              jsonResponse({ proof: `${expiry}.${'a'.repeat(64)}`, expires_at: expiry })
            )
          : pending.promise;
      });
      const api = new ApiClient({ schemaVersion: 1, apiBasePath: '/api/v1' }, { fetch });
      const tokens = new MemoryAccessToken();
      tokens.accept('synthetic.access.token', performance.now());
      const service = new SessionService(api, new CsrfProofService(api), tokens);
      render(
        <StrictMode>
          <CurrentSessionControls service={service}>
            <h2>You’re signed in</h2>
          </CurrentSessionControls>
        </StrictMode>
      );
      const button = screen.getByRole('button', { name: 'Sign out of this session' });
      button.focus();
      fireEvent.click(button);
      expect(tokens.getAccessToken()).toBeNull();
      expect(screen.getByRole('button', { name: 'Signing out' })).toHaveAttribute(
        'aria-busy',
        'true'
      );
      fireEvent.click(button);
      await waitFor(() => expect(fetch).toHaveBeenCalledTimes(2));
      pending.resolve(
        ok
          ? jsonResponse(null)
          : jsonResponse({ status: 'error', message: 'Internal server error.' }, 500, false)
      );
      const heading = await screen.findByRole('heading', { name: 'Signed out of this page' });
      expect(heading).toHaveFocus();
      expect(screen.queryByRole('heading', { name: 'You’re signed in' })).not.toBeInTheDocument();
      expect(
        screen.getByText(ok ? 'Current session ended' : 'Server sign-out unconfirmed')
      ).toBeVisible();
      expect(screen.getByRole('link', { name: 'Return to sign in' })).toHaveAttribute(
        'href',
        '/app/login'
      );
      expect(tokens.getAccessToken()).toBeNull();
      expect(fetch).toHaveBeenCalledTimes(2);
    }
  );
});
