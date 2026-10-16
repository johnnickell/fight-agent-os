/** Give concurrent shell decisions exclusive ownership of Pi's single native dialog */
export class CommandApprovals {
  #tail = Promise.resolve();
  #pending = new Set();
  constructor(active = () => {}) { this.active = active; }

  async run(open, { signal, validate = () => {} } = {}) {
    if (signal?.aborted) return false;
    const controller = new AbortController();
    const abort = () => controller.abort();
    const cancelled = new Promise(resolve => controller.signal.addEventListener('abort', () => resolve(false), { once: true }));
    signal?.addEventListener('abort', abort, { once: true });
    this.#pending.add(controller);
    const operation = this.#tail.then(async () => {
      if (controller.signal.aborted) return false;
      validate();
      this.active(true);
      try { return await Promise.race([open(controller.signal), cancelled]); }
      finally { this.active(false); }
    });
    // A cancelled waiter may return immediately, but its place cannot bypass the current dialog.
    this.#tail = operation.then(() => {}, () => {});
    try { return await Promise.race([operation, cancelled]); }
    finally { signal?.removeEventListener('abort', abort); this.#pending.delete(controller); }
  }

  cancel() { for (const controller of this.#pending) controller.abort(); }
}
