import type { RuntimeConfiguration } from '../src/runtimeConfiguration';

export const configuration: RuntimeConfiguration = Object.freeze({
  schemaVersion: 1,
  apiBasePath: '/api/v1',
});
export const correlationId = crypto.randomUUID().replaceAll('-', '');
export const expiresAt = 2000000000;
export const csrfData = {
  proof: `${expiresAt}.${'a'.repeat(64)}`,
  expires_at: expiresAt,
};

export function jsonResponse(
  body: unknown = { status: 'success', data: csrfData },
  status = 200,
  headers: Record<string, string> = {},
): Response {
  return new Response(JSON.stringify(body), {
    status,
    headers: {
      'Content-Type': 'application/json',
      'Cache-Control': 'no-store',
      'X-Correlation-ID': correlationId,
      ...headers,
    },
  });
}

export function deferred<T>() {
  let resolve!: (value: T) => void;
  let reject!: (reason: unknown) => void;
  const promise = new Promise<T>((yes, no) => {
    resolve = yes;
    reject = no;
  });
  return { promise, resolve, reject };
}
