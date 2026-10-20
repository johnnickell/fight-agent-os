import { act, fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

import { LoginJourney } from '@/features/auth/LoginJourney';

import { jsonResponse } from '../../activationFixtures';
import { loginSchemaSource } from '../../loginFixtures';

const runtime = { schemaVersion: 1, apiBasePath: '/api/v1' } as const;

describe('document-scoped login retirement', () => {
  it('removes secret fields synchronously on pagehide rather than retaining a frozen form', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse(loginSchemaSource)));
    const mounted = render(<LoginJourney runtime={runtime} />);
    const password = await screen.findByLabelText('Password (required)');
    fireEvent.change(password, { target: { value: 'synthetic' } });
    act(() => {
      window.dispatchEvent(new Event('pagehide'));
    });
    expect(screen.queryByLabelText('Password (required)')).not.toBeInTheDocument();
    expect(screen.getByText('Sign-in cleared')).toBeVisible();
    mounted.unmount();
    vi.unstubAllGlobals();
  });
});
