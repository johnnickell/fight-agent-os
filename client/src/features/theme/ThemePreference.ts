export type ThemePreference = 'system' | 'light' | 'dark';
export type EffectiveTheme = 'light' | 'dark';

export const THEME_KEY = 'fight-agent-os.theme.v1';
const DARK_QUERY = '(prefers-color-scheme: dark)';

export interface ThemeMedia {
  readonly matches: boolean;
  addEventListener?: (type: 'change', listener: () => void) => void;
  removeEventListener?: (type: 'change', listener: () => void) => void;
  addListener?: (listener: () => void) => void;
  removeListener?: (listener: () => void) => void;
}

export interface ThemeEnvironment {
  readonly root: HTMLElement;
  readonly storage: Pick<Storage, 'getItem' | 'setItem' | 'removeItem'> | null;
  media(): ThemeMedia | null;
  subscribeStorage(listener: (event: StorageEvent) => void): () => void;
}

export function browserThemeEnvironment(): ThemeEnvironment {
  return {
    root: document.documentElement,
    get storage() {
      try {
        return window.localStorage;
      } catch {
        return null;
      }
    },
    media() {
      try {
        return typeof window.matchMedia === 'function' ? window.matchMedia(DARK_QUERY) : null;
      } catch {
        return null;
      }
    },
    subscribeStorage(listener) {
      window.addEventListener('storage', listener);
      return () => window.removeEventListener('storage', listener);
    }
  };
}

export function readTheme(storage: ThemeEnvironment['storage']): ThemePreference {
  try {
    const value = storage?.getItem(THEME_KEY);
    return value === 'light' || value === 'dark' ? value : 'system';
  } catch {
    return 'system';
  }
}

export function resolveTheme(
  preference: ThemePreference,
  media: ThemeMedia | null
): EffectiveTheme {
  if (preference !== 'system') return preference;
  try {
    return media?.matches ? 'dark' : 'light';
  } catch {
    return 'light';
  }
}

function readMedia(environment: ThemeEnvironment): ThemeMedia | null {
  try {
    return environment.media();
  } catch {
    return null;
  }
}

export function applyTheme(root: HTMLElement, mode: EffectiveTheme): void {
  root.setAttribute('data-bs-theme', mode);
  root.style.colorScheme = mode;
}

// Shared by the blocking, self-hosted prepaint entry and the React runtime.
export function prepaintTheme(environment: ThemeEnvironment): void {
  applyTheme(
    environment.root,
    resolveTheme(readTheme(environment.storage), readMedia(environment))
  );
}

export class ThemePreferenceStore {
  private preference: ThemePreference;
  private readonly listeners = new Set<() => void>();
  private media: ThemeMedia | null = null;
  private snapshot: Readonly<{ preference: ThemePreference; effective: EffectiveTheme }>;
  private stopMedia: (() => void) | null = null;
  private stopStorage: (() => void) | null = null;

  constructor(private readonly environment: ThemeEnvironment) {
    this.preference = readTheme(environment.storage);
    this.snapshot = { preference: this.preference, effective: this.effective() };
    this.apply();
  }

  readonly getSnapshot = () => this.snapshot;

  readonly subscribe = (listener: () => void): (() => void) => {
    this.listeners.add(listener);
    if (this.listeners.size === 1) {
      this.stopStorage = this.environment.subscribeStorage((event) => {
        if (event.key === THEME_KEY || event.key === null) {
          this.update(readTheme(this.environment.storage));
        }
      });
      this.listenToMedia();
      this.apply();
    }
    return () => {
      this.listeners.delete(listener);
      if (this.listeners.size === 0) {
        this.stopMedia?.();
        this.stopMedia = null;
        this.stopStorage?.();
        this.stopStorage = null;
      }
    };
  };

  select(preference: ThemePreference): void {
    if (preference !== 'system' && preference !== 'light' && preference !== 'dark') return;
    try {
      if (preference === 'system') this.environment.storage?.removeItem(THEME_KEY);
      else this.environment.storage?.setItem(THEME_KEY, preference);
    } catch {
      // Presentation remains usable without storage; never store the resolved OS mode.
    }
    this.update(preference);
  }

  effective(): EffectiveTheme {
    return resolveTheme(this.preference, this.media ?? readMedia(this.environment));
  }

  private update(preference: ThemePreference): void {
    if (this.preference !== preference) {
      this.preference = preference;
      this.listenToMedia();
    }
    this.apply();
  }

  private apply(): void {
    const effective = this.effective();
    applyTheme(this.environment.root, effective);
    if (this.snapshot.preference !== this.preference || this.snapshot.effective !== effective) {
      this.snapshot = { preference: this.preference, effective };
      for (const listener of this.listeners) listener();
    }
  }

  private listenToMedia(): void {
    this.stopMedia?.();
    this.stopMedia = null;
    this.media = readMedia(this.environment);
    if (this.preference !== 'system' || this.listeners.size === 0 || !this.media) return;
    const media = this.media;
    const onChange = () => this.apply();
    try {
      if (media.addEventListener && media.removeEventListener) {
        media.addEventListener('change', onChange);
        this.stopMedia = () => media.removeEventListener?.('change', onChange);
      } else if (media.addListener && media.removeListener) {
        media.addListener(onChange);
        this.stopMedia = () => media.removeListener?.(onChange);
      }
    } catch {
      // Older or restricted media APIs may not support subscriptions.
    }
  }
}
