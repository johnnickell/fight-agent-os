const handoff = Symbol.for('fight-agent-os.reset-handoff');
type Material = Readonly<{ userId: string; credential: string }>;
type BootstrapHost = { [handoff]?: () => Material | null };
declare global {
  interface Window {
    [handoff]?: () => Material | null;
  }
}

/** Holds one reset link only in volatile private memory; never serialize this owner. */
export class ResetCredential {
  #material: Material | null;
  constructor(material: Material | null) {
    this.#material = material;
  }
  current(): Material | null {
    return this.#material;
  }
  clear(): void {
    this.#material = null;
  }
}

/** Executes before storage, resources and ordinary rendering in the blocking head script. */
export function captureReset(
  host: BootstrapHost,
  location: Pick<Location, 'pathname' | 'hash'>,
  history: Pick<History, 'replaceState'>
): boolean {
  if (location.pathname !== '/app/reset-password/complete') return true;
  let material: Material | null = null;
  let replaced = true;
  try {
    // RFC3986 encoding only: no form '+' decoding, duplicate/unknown keys or malformed escapes.
    const hash = location.hash;
    if (hash.length <= 12_400) {
      const match =
        /^#user_id=([a-f0-9]{8}-[a-f0-9]{4}-[1-8][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12})&credential=((?:[A-Za-z0-9_.~-]|%[a-fA-F0-9]{2})+)$/.exec(
          hash
        );
      if (match?.[1] && match[2]) {
        const credential = decodeURIComponent(match[2]);
        const length = new TextEncoder().encode(credential).byteLength;
        if (length >= 1 && length <= 4096) material = { userId: match[1], credential };
      }
    }
  } catch {
    material = null;
  }
  try {
    history.replaceState(null, '', '/app/reset-password/complete');
  } catch {
    material = null;
    replaced = false;
  }
  host[handoff] = () => {
    const selected = material;
    material = null;
    delete host[handoff];
    return selected;
  };
  return replaced;
}

export function takeReset(host: BootstrapHost): ResetCredential {
  const take = host[handoff];
  delete host[handoff];
  return new ResetCredential(take?.() ?? null);
}

/** Never adopts later fragment navigation as replacement authority. */
export function discardResetFragment(
  host: BootstrapHost,
  location: Pick<Location, 'pathname' | 'hash'>,
  history: Pick<History, 'replaceState'>
): boolean {
  if (location.pathname !== '/app/reset-password/complete' || location.hash === '') return false;
  const retire = location.hash !== '#main-content';
  if (retire) takeReset(host).clear();
  try {
    history.replaceState(null, '', '/app/reset-password/complete');
  } catch {
    // Bootstrap/root retirement denies further intake even when history is unavailable.
  }
  return retire;
}
