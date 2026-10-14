import { connection, Unavailable } from './readiness.mjs';

/** Resolve only Pi's configured OpenRouter source; the supported API deliberately hides resolution errors */
export class PiCredentials {
  constructor(modelRegistry) { this.modelRegistry = modelRegistry; }
  async read(signal) {
    if (signal.aborted) throw new Unavailable('cancelled');
    let key;
    try { key = await this.modelRegistry.getApiKeyForProvider('openrouter'); }
    catch { throw new Unavailable('credential-unavailable'); }
    // Pi has no cancellation parameter. A late resolution must never dispatch after cancellation.
    if (signal.aborted) throw new Unavailable('cancelled');
    if (typeof key !== 'string' || key.length < 16 || key.length > 4096 || !/^[!-~]+$/.test(key)) {
      throw new Unavailable('credential-unavailable');
    }
    // This copy can be cleared; Pi's immutable/cached string cannot be zeroized by this adapter.
    return Buffer.from(key, 'ascii');
  }
}

/** One direct HTTPS request, no redirects, retries, ambient auth, response logging or provider substitution */
export class DecisionsTransport {
  constructor(fetchRequest = fetch) { this.fetchRequest = fetchRequest; }
  async submit(endpoint, payload, credential, signal) {
    try {
      if (endpoint !== connection.endpoint || !Buffer.isBuffer(credential)) throw new Unavailable('invalid-setup');
      const response = await this.fetchRequest(endpoint, { method: 'POST', redirect: 'error', signal,
        headers: { 'Content-Type': 'application/json', Authorization: `Bearer ${credential.toString('ascii')}` },
        body: JSON.stringify(payload) });
      if (response.status !== 200) {
        await response.body?.cancel();
        return { status: response.status, body: null };
      }
      const reader = response.body?.getReader();
      if (!reader) throw new Unavailable('invalid-result');
      const chunks = []; let bytes = 0;
      try {
        while (true) {
          const { value, done } = await reader.read();
          if (done) break;
          bytes += value.length;
          if (bytes > 65536) throw new Unavailable('invalid-result');
          chunks.push(value);
        }
        let body;
        try { body = JSON.parse(Buffer.concat(chunks).toString('utf8')); }
        catch { throw new Unavailable('invalid-result'); }
        return { status: 200, body };
      } finally { await reader.cancel(); reader.releaseLock(); }
    } finally { if (Buffer.isBuffer(credential)) credential.fill(0); }
  }
}
