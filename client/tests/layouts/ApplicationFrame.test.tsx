import { act, fireEvent, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vitest';

import { ApplicationFrame } from '@/layouts/ApplicationFrame';

import { applicationSession } from '../applicationFixtures';

const owners: ReturnType<typeof applicationSession>[] = [];
afterEach(() => {
  owners.splice(0).forEach((owner) => owner.dispose());
  vi.unstubAllGlobals();
});
async function frame(narrow: boolean) {
  const listeners = new Set<() => void>();
  const media = {
    matches: narrow,
    addListener: () => {},
    removeListener: () => {},
    addEventListener: (_: string, callback: () => void) => listeners.add(callback),
    removeEventListener: (_: string, callback: () => void) => listeners.delete(callback)
  };
  vi.stubGlobal('matchMedia', () => media);
  const owner = applicationSession();
  owners.push(owner);
  await owner.coordinator.restore();
  const logout = vi.fn();
  render(
    <ApplicationFrame
      cache={owner.authority}
      pathname="/app/dashboard"
      title="Dashboard"
      onLogout={logout}
    >
      <h1>Owned content</h1>
    </ApplicationFrame>
  );
  return {
    owner,
    logout,
    resize: (matches: boolean) => {
      media.matches = matches;
      act(() => listeners.forEach((listener) => listener()));
    }
  };
}

describe('responsive application navigation', () => {
  it('has exactly one wide Close or collapsed Open, with Escape focus return and skip semantics', async () => {
    await frame(false);
    const user = userEvent.setup();
    expect(screen.getByRole('button', { name: 'Close navigation' })).toBeVisible();
    expect(screen.queryByRole('button', { name: 'Open navigation' })).not.toBeInTheDocument();
    await user.tab();
    expect(screen.getByRole('link', { name: 'Skip to content' })).toHaveFocus();
    expect(screen.getByRole('link', { name: 'Dashboard' })).toHaveAttribute('aria-current', 'page');
    await user.keyboard('{Escape}');
    expect(screen.getByRole('button', { name: 'Open navigation' })).toHaveFocus();
    expect(screen.queryByRole('button', { name: 'Close navigation' })).not.toBeInTheDocument();
    await user.keyboard('{Enter}');
    expect(screen.getByRole('button', { name: 'Close navigation' })).toBeVisible();
    fireEvent.click(screen.getByRole('button', { name: 'Close navigation' }));
    expect(screen.getByRole('button', { name: 'Open navigation' })).toHaveFocus();
  });
  it('starts narrow closed, traps a named modal drawer, inerts background and returns focus', async () => {
    await frame(true);
    const user = userEvent.setup();
    const open = screen.getByRole('button', { name: 'Open navigation' });
    await user.click(open);
    expect(await screen.findByRole('dialog', { name: 'Application navigation' })).toBeVisible();
    expect(screen.getByRole('main', { hidden: true }).closest('[inert]')).not.toBeNull();
    expect(screen.queryByRole('button', { name: 'Open navigation' })).not.toBeInTheDocument();
    const dialog = screen.getByRole('dialog', { name: 'Application navigation' });
    const first = screen.getByRole('link', { name: 'FIGHT AGENT OS' });
    const last = screen.getByRole('link', { name: 'Active sessions' });
    const geometry = vi
      .spyOn(HTMLElement.prototype, 'getClientRects')
      .mockReturnValue([new DOMRect(0, 0, 44, 44)] as unknown as DOMRectList);
    last.focus();
    fireEvent.keyDown(dialog, { key: 'Tab' });
    expect(first).toHaveFocus();
    fireEvent.keyDown(dialog, { key: 'Tab', shiftKey: true });
    expect(last).toHaveFocus();
    geometry.mockRestore();
    await user.keyboard('{Escape}');
    expect(await screen.findByRole('button', { name: 'Open navigation' })).toHaveFocus();
  });
  it('resets rail ownership at breakpoint and removes all controls immediately with authority', async () => {
    const { owner, resize, logout } = await frame(false);
    resize(true);
    expect(screen.getByRole('button', { name: 'Open navigation' })).toBeVisible();
    resize(false);
    expect(screen.getByRole('button', { name: 'Close navigation' })).toBeVisible();
    fireEvent.click(screen.getByRole('button', { name: 'Sign out' }));
    expect(logout).toHaveBeenCalledOnce();
    act(() => {
      void owner.authority.signal('terminal');
    });
    expect(screen.queryByText('Owned content')).not.toBeInTheDocument();
    expect(screen.queryByRole('navigation', { name: 'Primary' })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Close navigation' })).not.toBeInTheDocument();
  });
});
