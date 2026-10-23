import { useEffect, useRef, useState } from 'react';

import { Button } from '@/components/Button';
import { ContentState } from '@/components/ContentState';
import { TextField } from '@/components/forms/TextField';
import { Notice } from '@/components/Notice';
import { usePublicForm } from '@/features/validation/usePublicForm';

import type { ApiFailure } from '@/api/ApiResult';
import type { ResetRequestService } from '@/features/auth/ResetRequestService';
import type { PublicSchema } from '@/features/validation/PublicSchema';
import type { PublicSchemaService } from '@/features/validation/PublicSchemaService';

function failureMessage(failure: ApiFailure): string {
  if (failure.error.kind === 'rate-limited')
    return 'Too many requests. Wait 15 minutes before trying again.';
  if (failure.error.kind === 'authorization')
    return 'The secure request could not be verified. Try again to renew request protection.';
  return 'Password recovery is unavailable. Please try again. An earlier request may still have been processed.';
}

export function ResetRequestPage({
  service,
  schemas
}: {
  service: Pick<ResetRequestService, 'submit'>;
  schemas: PublicSchemaService;
}) {
  const [schema, setSchema] = useState<PublicSchema | null>(null);
  const [state, setState] = useState<'loading' | 'unavailable' | 'ready'>('loading');
  const [attempt, setAttempt] = useState(0);
  const [complete, setComplete] = useState(false);
  const success = useRef<HTMLHeadingElement>(null);
  useEffect(() => {
    document.title = 'Reset password — Fight Agent OS';
    const controller = new AbortController();
    void schemas.load('password_reset_request', controller.signal).then((result) => {
      if (controller.signal.aborted) return;
      if (result.ok) {
        setSchema(result.schema);
        setState('ready');
      } else setState('unavailable');
    });
    return () => controller.abort();
  }, [schemas, attempt]);
  useEffect(() => {
    if (complete) success.current?.focus();
  }, [complete]);
  return (
    <section className="activation-page" aria-labelledby="reset-request-heading">
      <h1
        id="reset-request-heading"
        ref={complete ? success : undefined}
        tabIndex={complete ? -1 : undefined}
      >
        {complete ? 'Check your inbox' : 'Reset your password'}
      </h1>
      <div className="activation-panel">
        {complete ? (
          <Notice tone="success" title="Request received">
            If this address is eligible, a reset email will be sent. Check your inbox. Links expire
            after one hour.
          </Notice>
        ) : state === 'loading' ? (
          <ContentState
            kind="loading"
            title="Preparing password recovery"
            message="Loading form validation."
          />
        ) : state === 'unavailable' || !schema ? (
          <ContentState
            kind="error"
            title="Validation is unavailable"
            message="The request cannot be submitted until validation is available."
            onRetry={() => {
              setState('loading');
              setAttempt((value) => value + 1);
            }}
          />
        ) : (
          <ResetRequestForm schema={schema} service={service} onSuccess={() => setComplete(true)} />
        )}
      </div>
      <p className="activation-footnote">
        <a href="/app/login">Return to sign in</a>
      </p>
    </section>
  );
}

function ResetRequestForm({
  schema,
  service,
  onSuccess
}: {
  schema: PublicSchema;
  service: Pick<ResetRequestService, 'submit'>;
  onSuccess: () => void;
}) {
  const form = usePublicForm({
    schema,
    initialValues: { email: '' },
    submit: (values) => service.submit(values),
    onSuccess,
    failureMessage
  });
  return (
    <form noValidate onSubmit={form.handleSubmit} aria-busy={form.busy}>
      <p>
        Enter your email address. We’ll provide the same response whether or not it is eligible.
      </p>
      <TextField
        label="Email"
        type="email"
        autoComplete="email"
        autoCapitalize="none"
        spellCheck={false}
        required
        description="Use the email address you sign in with."
        {...form.field('email')}
      />
      <div aria-live="polite" aria-atomic="true">
        {Object.keys(form.errors).length > 0 && <p>Check the highlighted field.</p>}
        {form.formError && (
          <Notice tone="danger" title="Request not confirmed" announce="assertive">
            {form.formError}
          </Notice>
        )}
      </div>
      <Button type="submit" busy={form.busy} busyLabel="Requesting reset…">
        Request reset link
      </Button>
    </form>
  );
}
