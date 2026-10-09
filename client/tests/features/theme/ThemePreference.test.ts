import { describe, expect, it, vi } from 'vitest';

import {
  prepaintTheme,
  readTheme,
  THEME_KEY,
  ThemePreferenceStore
} from '@/features/theme/ThemePreference';

import type { ThemeEnvironment, ThemeMedia } from '@/features/theme/ThemePreference';

function fixture(initial: string | null = null, dark = false) {
  const values = new Map<string, string>();
  if (initial !== null) values.set(THEME_KEY, initial);
  const mediaListeners = new Set<() => void>();
  const storageListeners = new Set<(event: StorageEvent) => void>();
  const media: ThemeMedia & { matches: boolean } = {
    matches: dark,
    addEventListener: (_type, listener) => mediaListeners.add(listener),
    removeEventListener: (_type, listener) => mediaListeners.delete(listener)
  };
  const storage = {
    getItem: (key: string) => values.get(key) ?? null,
    setItem: (key: string, value: string) => {
      values.set(key, value);
    },
    removeItem: (key: string) => {
      values.delete(key);
    }
  };
  const root = document.createElement('html');
  const environment: ThemeEnvironment = {
    root,
    storage,
    media: () => media,
    subscribeStorage: (listener) => {
      storageListeners.add(listener);
      return () => storageListeners.delete(listener);
    }
  };
  return {
    environment,
    root,
    media,
    values,
    mediaListeners,
    storageListeners,
    changeOS(value: boolean) {
      media.matches = value;
      for (const listener of mediaListeners) listener();
    },
    changeTab(key: string | null) {
      for (const listener of storageListeners) listener({ key } as StorageEvent);
    }
  };
}

describe('presentation preference', () => {
  it.each([null, '', 'SYSTEM', '"dark"', 'false', 'dark '.repeat(1000)])(
    'treats missing or invalid stored value %# as system',
    (value) => {
      const state = fixture(value, true);
      prepaintTheme(state.environment);
      expect(readTheme(state.environment.storage)).toBe('system');
      expect(state.root.getAttribute('data-bs-theme')).toBe('dark');
      expect(state.root.style.colorScheme).toBe('dark');
      expect(state.values.get(THEME_KEY)).toBe(value ?? undefined);
    }
  );

  it.each(['light', 'dark'] as const)('applies explicit %s before rendering', (value) => {
    const state = fixture(value, value === 'light');
    prepaintTheme(state.environment);
    expect(state.root.getAttribute('data-bs-theme')).toBe(value);
    expect(state.root.style.colorScheme).toBe(value);
  });

  it('follows OS in system mode, ignores it in explicit mode and cleans up remount listeners', () => {
    const state = fixture();
    const theme = new ThemePreferenceStore(state.environment);
    const notified = vi.fn();
    const unsubscribe = theme.subscribe(notified);
    expect(state.mediaListeners.size).toBe(1);
    expect(state.storageListeners.size).toBe(1);
    state.changeOS(true);
    expect(theme.getSnapshot()).toEqual({ preference: 'system', effective: 'dark' });
    expect(state.root.style.colorScheme).toBe('dark');
    theme.select('light');
    expect(state.values.get(THEME_KEY)).toBe('light');
    expect(state.mediaListeners.size).toBe(0);
    state.changeOS(false);
    expect(state.root.style.colorScheme).toBe('light');
    theme.select('dark');
    expect(state.values.get(THEME_KEY)).toBe('dark');
    theme.select('system');
    expect(state.values.has(THEME_KEY)).toBe(false);
    expect(state.mediaListeners.size).toBe(1);
    unsubscribe();
    expect(state.mediaListeners.size).toBe(0);
    expect(state.storageListeners.size).toBe(0);
    const unsubscribeAgain = theme.subscribe(notified);
    expect(state.mediaListeners.size).toBe(1);
    unsubscribeAgain();
    expect(state.mediaListeners.size).toBe(0);
    expect(notified).toHaveBeenCalled();
  });

  it('accepts a valid cross-tab update and clears to system on key removal or clear', () => {
    const state = fixture('light', true);
    const theme = new ThemePreferenceStore(state.environment);
    const unsubscribe = theme.subscribe(() => {});
    state.values.set(THEME_KEY, 'dark');
    state.changeTab(THEME_KEY);
    expect(theme.getSnapshot().preference).toBe('dark');
    state.values.delete(THEME_KEY);
    state.changeTab(null);
    expect(theme.getSnapshot()).toEqual({ preference: 'system', effective: 'dark' });
    unsubscribe();
  });

  it('ignores unrelated storage events and keeps one listener for multiple consumers', () => {
    const state = fixture('light');
    const theme = new ThemePreferenceStore(state.environment);
    const first = vi.fn();
    const second = vi.fn();
    const stopFirst = theme.subscribe(first);
    const stopSecond = theme.subscribe(second);
    expect(state.storageListeners.size).toBe(1);
    state.values.set(THEME_KEY, 'dark');
    state.changeTab('unrelated-key');
    expect(theme.getSnapshot().preference).toBe('light');
    state.changeTab(THEME_KEY);
    expect(theme.getSnapshot().preference).toBe('dark');
    expect(first).toHaveBeenCalledOnce();
    expect(second).toHaveBeenCalledOnce();
    stopFirst();
    expect(state.storageListeners.size).toBe(1);
    stopSecond();
    expect(state.storageListeners.size).toBe(0);
  });

  it('continues when media observation throws or has no listener API', () => {
    const state = fixture();
    const denied: ThemeEnvironment = {
      ...state.environment,
      media: () => {
        throw new Error('denied');
      }
    };
    prepaintTheme(denied);
    expect(state.root.style.colorScheme).toBe('light');
    const theme = new ThemePreferenceStore(denied);
    const stop = theme.subscribe(() => {});
    expect(theme.getSnapshot()).toEqual({ preference: 'system', effective: 'light' });
    stop();
    const withoutListeners = new ThemePreferenceStore({
      ...state.environment,
      media: () => ({ matches: true })
    });
    const stopWithoutListeners = withoutListeners.subscribe(() => {});
    expect(withoutListeners.getSnapshot().effective).toBe('dark');
    stopWithoutListeners();
  });

  it('continues under denied storage, unavailable media and legacy subscriptions', () => {
    const state = fixture();
    const badStorage = {
      getItem: () => {
        throw new Error('denied');
      },
      setItem: () => {
        throw new Error('denied');
      },
      removeItem: () => {
        throw new Error('denied');
      }
    };
    const legacy = new Set<() => void>();
    const environment: ThemeEnvironment = {
      ...state.environment,
      storage: badStorage,
      media: () => ({
        matches: true,
        addListener: (listener) => {
          legacy.add(listener);
        },
        removeListener: (listener) => {
          legacy.delete(listener);
        }
      })
    };
    prepaintTheme(environment);
    expect(state.root.style.colorScheme).toBe('dark');
    const theme = new ThemePreferenceStore(environment);
    const unsubscribe = theme.subscribe(() => {});
    expect(legacy.size).toBe(1);
    theme.select('light');
    expect(state.root.style.colorScheme).toBe('light');
    expect(legacy.size).toBe(0);
    unsubscribe();
    prepaintTheme({ ...environment, media: () => null });
    expect(state.root.style.colorScheme).toBe('light');
    expect(readTheme(badStorage)).toBe('system');
  });
});
