const handoff = Symbol.for('fight-agent-os.activation-handoff');

type Material = Readonly<{ userId: string; credential: string }>;
type BootstrapHost = { [handoff]?: () => Material | null };
declare global {
  interface Window {
    [handoff]?: () => Material | null;
  }
}

/** Owns a single invitation only in volatile memory; never stringify this owner. */
export class ActivationCredential {
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

/** Runs in the blocking head script, before theme/storage access and React boot. */
export function captureActivation(
  host: BootstrapHost,
  location: Pick<Location, 'pathname' | 'hash'>,
  history: Pick<History, 'replaceState'>
): boolean {
  if (location.pathname !== '/app/activate') return true;
  let material: Material | null = null;
  let replaced = true;
  const hash = location.hash;
  try {
    // Canonical generated links use exactly these bounded fields, never arbitrary route data.
    if (hash.length <= 160) {
      const fields = new URLSearchParams(hash.slice(1));
      const userId = fields.get('user_id');
      const credential = fields.get('credential');
      if (
        [...fields].length === 2 &&
        fields.getAll('user_id').length === 1 &&
        fields.getAll('credential').length === 1 &&
        userId !== null &&
        /^[a-f0-9]{8}-[a-f0-9]{4}-[1-8][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/.test(userId) &&
        credential !== null &&
        /^[a-f0-9]{64}$/.test(credential)
      ) {
        material = { userId, credential };
      }
    }
    history.replaceState(null, '', '/app/activate');
  } catch {
    material = null;
    replaced = false;
  }
  // Closure rather than HTML/config/history/storage; consumed and deleted once by the root.
  host[handoff] = () => {
    const selected = material;
    material = null;
    delete host[handoff];
    return selected;
  };
  return replaced;
}

/** Later same-document fragments are removed, never adopted as replacement authority. */
export function discardActivationFragment(
  host: BootstrapHost,
  location: Pick<Location, 'pathname' | 'hash'>,
  history: Pick<History, 'replaceState'>
): boolean {
  if (location.pathname !== '/app/activate' || location.hash === '') return false;
  const retire = location.hash !== '#main-content';
  if (retire) takeActivation(host).clear();
  try {
    history.replaceState(null, '', '/app/activate');
  } catch {
    /* Intake remains denied. */
  }
  return retire;
}

export function takeActivation(host: BootstrapHost): ActivationCredential {
  const take = host[handoff];
  delete host[handoff];
  return new ActivationCredential(take?.() ?? null);
}
