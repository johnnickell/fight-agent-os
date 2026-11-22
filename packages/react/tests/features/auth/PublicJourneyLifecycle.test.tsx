import {
  ActivationCredential,
  ActivationJourney,
  apiFailure,
  LoginJourney,
  PublicSchemaService,
  ResetRequestJourney,
  ResetRequestService,
  SessionService
} from '@fight/react/account';
import { act, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

const runtime = {
  schemaVersion: 2,
  apiBasePath: '/api/v1',
  appDebug: false,
  swaggerAvailable: false
} as const;

describe('public account document lifecycle', () => {
  it('keeps standalone sign-in locked during restore and retires it before page history can resume it', async () => {
    const refresh = vi
      .spyOn(SessionService.prototype, 'refresh')
      .mockResolvedValue(apiFailure('authentication'));
    vi.spyOn(PublicSchemaService.prototype, 'load').mockImplementation(() => new Promise(() => {}));
    const authenticated = vi.fn();
    render(<LoginJourney runtime={runtime} onAuthenticated={authenticated} />);
    expect(screen.getByText('Checking this browser’s session')).toBeVisible();
    expect(await screen.findByText('Preparing secure sign-in')).toBeVisible();

    void act(() => window.dispatchEvent(new Event('pagehide')));

    expect(screen.getByText('Sign-in cleared')).toBeVisible();
    expect(screen.queryByText('Preparing secure sign-in')).not.toBeInTheDocument();
    expect(refresh).toHaveBeenCalledOnce();
    expect(authenticated).not.toHaveBeenCalled();
  });
  it.each(['pagehide', 'fight:activation-retired'])('clears a held invitation on %s', (type) => {
    const credential = new ActivationCredential({
      userId: crypto.randomUUID(),
      credential: 'c'.repeat(64)
    });
    vi.spyOn(PublicSchemaService.prototype, 'load').mockImplementation(() => new Promise(() => {}));
    render(<ActivationJourney runtime={runtime} credential={credential} />);
    expect(screen.getByText('Preparing secure activation')).toBeVisible();

    void act(() => window.dispatchEvent(new Event(type)));

    expect(credential.current()).toBeNull();
    expect(screen.getByRole('heading', { name: 'Invitation cleared' })).toBeVisible();
    expect(screen.queryByText('Preparing secure activation')).not.toBeInTheDocument();
  });

  it('does not request a password schema when no invitation was supplied', () => {
    const load = vi.spyOn(PublicSchemaService.prototype, 'load');
    render(<ActivationJourney runtime={runtime} />);
    expect(screen.getByText('This link cannot be used')).toBeVisible();
    expect(load).not.toHaveBeenCalled();
  });

  it('retires the reset request service and aborts pending validation on unmount', () => {
    const load = vi
      .spyOn(PublicSchemaService.prototype, 'load')
      .mockImplementation(() => new Promise(() => {}));
    const dispose = vi.spyOn(ResetRequestService.prototype, 'dispose');
    const { unmount } = render(<ResetRequestJourney runtime={runtime} />);
    expect(load).toHaveBeenCalledExactlyOnceWith('password_reset_request', expect.any(AbortSignal));
    const signal = load.mock.calls[0]?.[1];
    expect(signal?.aborted).toBe(false);

    unmount();

    expect(signal?.aborted).toBe(true);
    expect(dispose).toHaveBeenCalledOnce();
  });
});
