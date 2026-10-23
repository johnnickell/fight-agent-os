import { useEffect, useRef, useState } from 'react';

import { ContentState } from '@/components/ContentState';
import { useAuthority } from '@/features/authority/useAuthority';
import { mountSwagger } from '@/features/swagger/mountSwagger';

import type { AuthorityCache } from '@/features/authority/AuthorityCache';
import type { SwaggerService } from '@/features/swagger/SwaggerService';

export default function SwaggerPage({
  service,
  cache
}: {
  service: SwaggerService;
  cache: AuthorityCache;
}) {
  // The frame may retain this Page during routine checks, but private library state belongs
  // only to the current dispatch scope, never the cache's retained presentation projection.
  const authority = useAuthority(cache);
  const authorityScope = cache.captureScope();
  const host = useRef<HTMLDivElement>(null);
  const [attempt, setAttempt] = useState(0);
  const [status, setStatus] = useState<'loading' | 'ready' | 'unavailable'>('loading');
  useEffect(() => {
    if (!host.current || !service.available || authority.status !== 'authenticated') return;
    setStatus('loading');
    const element = host.current;
    const local = new AbortController();
    const abort = () => local.abort();
    authorityScope.signal.addEventListener('abort', abort, { once: true });
    if (!authorityScope.isCurrent()) abort();
    const scope = {
      signal: local.signal,
      isCurrent: () => !local.signal.aborted && authorityScope.isCurrent()
    };
    let mounted = true;
    let stop: (() => void) | undefined;
    void service.load(scope).then((result) => {
      if (!mounted || !scope.isCurrent()) return;
      if (!result.ok) {
        setStatus('unavailable');
        return;
      }
      try {
        element.hidden = false;
        stop = mountSwagger(element, result.value, service, scope);
        setStatus('ready');
      } catch {
        element.hidden = true;
        element.replaceChildren();
        setStatus('unavailable');
      }
    });
    return () => {
      mounted = false;
      authorityScope.signal.removeEventListener('abort', abort);
      local.abort();
      stop?.();
    };
  }, [service, authority, authorityScope, attempt]);
  return (
    <section aria-labelledby="swagger-heading">
      <h1 id="swagger-heading">API documentation</h1>
      <p>
        Private documentation. Execute sends a real request with your current session’s authority.
        It is not a preview.
      </p>
      <details>
        <summary>Supported operations and safety limits</summary>
        <p>
          Live execution: current identity; Role and Permission list/detail reads; custom Role
          creation (POST /roles). Creation retires this viewer and refetches current access even if
          its outcome is uncertain. Check the catalog before explicitly retrying.
        </p>
        <p>
          All other operations are documentation-only, including login, activation, CSRF, refresh,
          logout, invitation delivery, Role rename/removal and Repository operations. They need
          their own qualified secret, confirmation or input handling. Refresh and CSRF remain owned
          by the shared application session; this viewer never issues or persists another
          credential.
        </p>
        <p>
          No automatic execution or replay. Cancellation and sign-out cannot undo an already
          dispatched server effect. Request headers/bodies are excluded from curl and clipboard
          exports. Responses show only existing strict safe feature codecs.
        </p>
      </details>
      {!service.available ? (
        <ContentState
          kind="empty"
          title="Documentation disabled"
          message="This installation has not enabled documentation in this environment."
        />
      ) : authority.status !== 'authenticated' ? (
        <ContentState
          kind="loading"
          title="Checking documentation access"
          message="Private explorer state is retired while current access is unavailable. No operation is replayed."
        />
      ) : status === 'unavailable' ? (
        <ContentState
          kind="error"
          title="Documentation unavailable"
          message="Access or the private artifact is unavailable. No operation was sent."
          onRetry={() => {
            setStatus('loading');
            setAttempt((value) => value + 1);
          }}
        />
      ) : status === 'loading' ? (
        <ContentState
          kind="loading"
          title="Loading private documentation"
          message="Checking server access and the complete private contract."
        />
      ) : null}
      <div className="swagger-viewer" ref={host} role="region" aria-label="Private API explorer" />
    </section>
  );
}
