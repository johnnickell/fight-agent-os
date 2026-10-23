import { act, fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vitest';

import { THEME_KEY, ThemePreferenceStore } from '@/features/theme/ThemePreference';
import { ApplicationFrame } from '@/layouts/ApplicationFrame';

import { deferred } from '../apiFixtures';
import { applicationSession } from '../applicationFixtures';
import { principal } from '../authorityFixtures';

import type { PrincipalLoadResult } from '@/features/authority/AuthorityCache';

const owners: ReturnType<typeof applicationSession>[] = [];
afterEach(() => {
  owners.splice(0).forEach((owner) => owner.dispose());
  vi.unstubAllGlobals();
});
async function frame(narrow: boolean, permissions?: string[]) {
  const listeners = new Set<() => void>();
  const media = {
    matches: narrow,
    addListener: () => {},
    removeListener: () => {},
    addEventListener: (_: string, callback: () => void) => listeners.add(callback),
    removeEventListener: (_: string, callback: () => void) => listeners.delete(callback)
  };
  vi.stubGlobal('matchMedia', () => media);
  const owner = applicationSession(permissions);
  owners.push(owner);
  await owner.coordinator.restore();
  const logout = vi.fn();
  const values = new Map<string, string>();
  const schemeListeners = new Set<() => void>();
  const scheme = {
    matches: false,
    addEventListener: (_: string, listener: () => void) => schemeListeners.add(listener),
    removeEventListener: (_: string, listener: () => void) => schemeListeners.delete(listener)
  };
  const theme = new ThemePreferenceStore({
    root: document.documentElement,
    storage: {
      getItem: (key) => values.get(key) ?? null,
      setItem: (key, value) => {
        values.set(key, value);
      },
      removeItem: (key) => {
        values.delete(key);
      }
    },
    media: () => scheme,
    subscribeStorage: (listener) => {
      window.addEventListener('storage', listener);
      return () => window.removeEventListener('storage', listener);
    }
  });
  const content = (pathname: string) => (
    <ApplicationFrame
      cache={owner.authority}
      pathname={pathname}
      title="Dashboard"
      onLogout={logout}
      theme={theme}
    >
      <h1>Owned content</h1>
    </ApplicationFrame>
  );
  const view = render(content('/app/dashboard'));
  return {
    owner,
    logout,
    theme,
    values,
    navigate: (pathname: string) => view.rerender(content(pathname)),
    unmount: () => view.unmount(),
    changeOs: (dark: boolean) => {
      scheme.matches = dark;
      act(() => schemeListeners.forEach((listener) => listener()));
    },
    receivePreference: (preference: 'light' | 'dark' | null) => {
      if (preference === null) values.delete(THEME_KEY);
      else values.set(THEME_KEY, preference);
      act(() => {
        window.dispatchEvent(new StorageEvent('storage', { key: THEME_KEY }));
      });
    },
    resize: (matches: boolean) => {
      media.matches = matches;
      act(() => listeners.forEach((listener) => listener()));
    }
  };
}

describe('compact account controls', () => {
  it('follows System OS changes before User Menu is ever opened and releases listeners on unmount', async () => {
    const { changeOs, receivePreference, unmount } = await frame(false);
    expect(screen.getByRole('button', { name: 'User Menu' })).toHaveAttribute(
      'aria-expanded',
      'false'
    );
    expect(screen.queryByRole('group', { name: 'Appearance' })).not.toBeInTheDocument();
    changeOs(true);
    expect(document.documentElement).toHaveAttribute('data-bs-theme', 'dark');
    changeOs(false);
    expect(document.documentElement).toHaveAttribute('data-bs-theme', 'light');
    unmount();
    changeOs(true);
    receivePreference('dark');
    expect(document.documentElement).toHaveAttribute('data-bs-theme', 'light');
  });

  it('receives cross-tab manual and System preferences with User Menu unopened', async () => {
    const { receivePreference, changeOs } = await frame(false);
    receivePreference('dark');
    expect(document.documentElement).toHaveAttribute('data-bs-theme', 'dark');
    changeOs(true);
    receivePreference('light');
    expect(document.documentElement).toHaveAttribute('data-bs-theme', 'light');
    receivePreference(null);
    expect(document.documentElement).toHaveAttribute('data-bs-theme', 'dark');
    changeOs(false);
    expect(document.documentElement).toHaveAttribute('data-bs-theme', 'light');
    const toggle = screen.getByRole('button', { name: 'User Menu' });
    expect(toggle).toHaveAttribute('aria-expanded', 'false');
    await userEvent.click(toggle);
    expect(screen.getByRole('radio', { name: 'System' })).toBeChecked();
  });

  it('keeps OS and cross-tab appearance working after navigation resets the menu to unopened', async () => {
    const { navigate, changeOs, receivePreference } = await frame(false);
    await userEvent.click(screen.getByRole('button', { name: 'User Menu' }));
    changeOs(true);
    expect(document.documentElement).toHaveAttribute('data-bs-theme', 'dark');
    navigate('/app/account');
    expect(screen.getByRole('button', { name: 'User Menu' })).toHaveAttribute(
      'aria-expanded',
      'false'
    );
    changeOs(false);
    expect(document.documentElement).toHaveAttribute('data-bs-theme', 'light');
    receivePreference('dark');
    expect(document.documentElement).toHaveAttribute('data-bs-theme', 'dark');
    receivePreference(null);
    expect(document.documentElement).toHaveAttribute('data-bs-theme', 'light');
  });

  it('opens by keyboard, traverses destinations and returns Escape focus without collapsing the rail', async () => {
    await frame(false);
    const user = userEvent.setup();
    const toggle = screen.getByRole('button', { name: 'User Menu' });
    const banner = within(screen.getByRole('banner'));
    expect(banner.queryByText(principal.email)).not.toBeInTheDocument();
    toggle.focus();
    await user.keyboard('{ArrowDown}');
    expect(banner.getByText(principal.email)).toBeVisible();
    expect(banner.getByRole('link', { name: 'Account & Security' })).toHaveFocus();
    await user.keyboard('{ArrowDown}');
    expect(banner.getByRole('link', { name: 'Active Sessions' })).toHaveFocus();
    await user.keyboard('{Escape}');
    expect(toggle).toHaveFocus();
    expect(toggle).toHaveAttribute('aria-expanded', 'false');
    expect(screen.getByRole('button', { name: 'Close navigation' })).toBeVisible();
    await user.click(toggle);
    await user.click(screen.getByRole('heading', { name: 'Owned content' }));
    await waitFor(() => expect(toggle).toHaveAttribute('aria-expanded', 'false'));
  });

  it('keeps local appearance interactive inside the dropdown and identifies the preference, not effective mode', async () => {
    const { theme, values } = await frame(false);
    const user = userEvent.setup();
    await user.click(screen.getByRole('button', { name: 'User Menu' }));
    const system = screen.getByRole('radio', { name: 'System' });
    expect(system).toBeChecked();
    system.focus();
    await user.keyboard('{ArrowRight}');
    expect(screen.getByRole('radio', { name: 'Light' })).toBeChecked();
    expect(theme.getSnapshot().preference).toBe('light');
    await user.click(screen.getByRole('radio', { name: 'Dark' }));
    expect(document.documentElement).toHaveAttribute('data-bs-theme', 'dark');
    await user.click(system);
    expect(system).toBeChecked();
    expect(theme.getSnapshot()).toEqual({ preference: 'system', effective: 'light' });
    expect(values.size).toBe(0);
    expect(screen.getByRole('button', { name: 'User Menu' })).toHaveAttribute(
      'aria-expanded',
      'true'
    );
  });

  it.each([{ permissions: [] }, { permissions: ['READ_OWN_SESSIONS'] }])(
    'evaluates session navigation independently for %j',
    async ({ permissions }) => {
      await frame(false, permissions);
      await userEvent.click(screen.getByRole('button', { name: 'User Menu' }));
      const banner = within(screen.getByRole('banner'));
      expect(banner.getByRole('link', { name: 'Account & Security' })).toHaveAttribute(
        'href',
        '/app/account'
      );
      expect(Boolean(banner.queryByRole('link', { name: 'Active Sessions' }))).toBe(
        permissions.length > 0
      );
      expect(banner.queryByRole('button', { name: /edit|upload/i })).not.toBeInTheDocument();
    }
  );

  it('retains the open menu, selected radio, navigation disclosure and focus through a pending background check', async () => {
    const { owner } = await frame(false);
    fireEvent.click(screen.getByText('Security', { selector: 'summary' }));
    const disclosure = screen.getByText('Security', { selector: 'summary' }).parentElement;
    await userEvent.click(screen.getByRole('button', { name: 'User Menu' }));
    const radio = screen.getByRole('radio', { name: 'System' });
    radio.focus();
    const pending = deferred<PrincipalLoadResult>();
    owner.load.mockImplementationOnce(() => pending.promise);
    act(() => owner.authority.resume());
    expect(screen.getByRole('radio', { name: 'System' })).toBe(radio);
    expect(radio).toHaveFocus();
    expect(disclosure).not.toHaveAttribute('open');
    const account = within(screen.getByRole('banner')).getByRole('link', {
      name: 'Account & Security'
    });
    expect(account).toHaveAttribute('aria-disabled', 'true');
    expect(fireEvent.click(account)).toBe(false);
    await act(async () => {
      pending.resolve({ status: 'principal', principal });
      await pending.promise;
    });
    expect(screen.getByRole('radio', { name: 'System' })).toBe(radio);
    expect(radio).toHaveFocus();
  });
});

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
    const last = screen.getByRole('link', { name: 'Active Sessions' });
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
    await waitFor(() =>
      expect(screen.getByRole('button', { name: 'Open navigation' })).toHaveFocus()
    );
  });
  it('keeps a visible navigation focus position when the focused drawer or rail disappears', async () => {
    const { resize } = await frame(true);
    await userEvent.click(screen.getByRole('button', { name: 'Open navigation' }));
    const close = screen.getByRole('button', { name: 'Close navigation' });
    close.focus();
    expect(close).toHaveFocus();

    resize(false);
    expect(screen.getByRole('button', { name: 'Close navigation' })).toHaveFocus();
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    expect(screen.getByRole('main').closest('[inert]')).toBeNull();

    resize(true);
    expect(screen.getByRole('button', { name: 'Open navigation' })).toHaveFocus();
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    await userEvent.keyboard('{Enter}');
    expect(await screen.findByRole('dialog', { name: 'Application navigation' })).toBeVisible();
    await userEvent.keyboard('{Escape}');
    await waitFor(() =>
      expect(screen.getByRole('button', { name: 'Open navigation' })).toHaveFocus()
    );
  });
  it('preserves workspace focus and does not claim focus from outside navigation on resize', async () => {
    const { resize } = await frame(false);
    const account = within(screen.getByRole('banner')).getByRole('button', {
      name: 'User Menu'
    });
    account.focus();
    resize(true);
    expect(account).toHaveFocus();
    await userEvent.click(screen.getByRole('button', { name: 'Open navigation' }));
    await userEvent.keyboard('{Escape}');
    await waitFor(() =>
      expect(screen.getByRole('button', { name: 'Open navigation' })).toHaveFocus()
    );
    account.focus();
    resize(false);
    expect(account).toHaveFocus();
    account.blur();
    resize(true);
    expect(document.body).toHaveFocus();
  });
  it('resets rail ownership at breakpoint and removes all controls immediately with authority', async () => {
    const { owner, resize, logout } = await frame(false);
    resize(true);
    expect(screen.getByRole('button', { name: 'Open navigation' })).toBeVisible();
    resize(false);
    expect(screen.getByRole('button', { name: 'Close navigation' })).toBeVisible();
    fireEvent.click(screen.getByRole('button', { name: 'User Menu' }));
    fireEvent.click(screen.getByRole('button', { name: 'Sign Out' }));
    expect(logout).toHaveBeenCalledOnce();
    act(() => {
      void owner.authority.signal('terminal');
    });
    expect(screen.queryByText('Owned content')).not.toBeInTheDocument();
    expect(screen.queryByRole('navigation', { name: 'Primary' })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Close navigation' })).not.toBeInTheDocument();
    resize(true);
    resize(false);
    expect(screen.queryByRole('button', { name: 'Open navigation' })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Close navigation' })).not.toBeInTheDocument();
  });
});
