import { useEffect, useRef, useState } from 'react';

import { Button } from '@/components/Button';
import { ContentState } from '@/components/ContentState';
import { TextField } from '@/components/forms/TextField';
import { Notice } from '@/components/Notice';
import { usePublicForm } from '@/features/validation/usePublicForm';

import type { ApiFailure } from '@/api/ApiResult';
import type { ResetCompletionService } from '@/features/auth/ResetCompletionService';
import type { PublicSchema } from '@/features/validation/PublicSchema';
import type { PublicSchemaService } from '@/features/validation/PublicSchemaService';

function failureMessage(failure: ApiFailure): string | null {
  if (failure.error.kind === 'rate-limited')
    return 'Too many attempts. Wait 15 minutes before trying again.';
  if (failure.error.kind === 'authorization')
    return 'The secure request could not be verified. Try again to renew request protection.';
  return null;
}
function LinkRecovery() {
  const recovery = useRef<HTMLDivElement>(null);
  useEffect(() => recovery.current?.focus(), []);
  return (
    <div ref={recovery} tabIndex={-1} role="region" aria-label="Reset link recovery">
      <ContentState
        kind="error"
        title="This link cannot be used"
        message="It may be expired, replaced or already used. Reload and browser history cannot restore a reset link."
      />
      <a href="/app/reset-password">Request a new link</a>
    </div>
  );
}

export function ResetCompletionPage({
  service,
  schemas
}: {
  service: ResetCompletionService;
  schemas: PublicSchemaService;
}) {
  const [schema, setSchema] = useState<PublicSchema | null>(null);
  const [state, setState] = useState<'loading' | 'unavailable' | 'ready'>('loading');
  const [attempt, setAttempt] = useState(0);
  const [complete, setComplete] = useState(false);
  const resultHeading = useRef<HTMLHeadingElement>(null);
  useEffect(() => {
    document.title = 'Reset password — Fight Agent OS';
    const controller = new AbortController();
    if (service.usable()) {
      void schemas.load('password_reset_completion', controller.signal).then((result) => {
        if (controller.signal.aborted) return;
        if (result.ok) {
          setSchema(result.schema);
          setState('ready');
        } else setState('unavailable');
      });
    }
    return () => controller.abort();
  }, [service, schemas, attempt]);
  useEffect(() => {
    if (complete) resultHeading.current?.focus();
  }, [complete]);
  return (
    <section className="activation-page" aria-labelledby="reset-heading">
      <p className="activation-eyebrow">FIGHT / AGENT OS</p>
      <h1 id="reset-heading">A fresh start.</h1>
      <p className="activation-introduction">One reset link. A password only you know.</p>
      <div className="activation-panel">
        {complete ? (
          <>
            <h2 ref={resultHeading} tabIndex={-1}>
              Password reset complete
            </h2>
            <Notice tone="success" title="Sign in with your new password">
              All sessions for this account have ended. Your password and reset link have been
              cleared. No new session has been created.
            </Notice>
            <a href="/app/login" data-reload-application>
              Sign in
            </a>
          </>
        ) : !service.usable() ? (
          <LinkRecovery />
        ) : state === 'loading' ? (
          <ContentState
            kind="loading"
            title="Preparing secure reset"
            message="Loading the public password validation schema."
          />
        ) : state === 'unavailable' || !schema ? (
          <ContentState
            kind="error"
            title="Validation is unavailable"
            message="No password can be submitted until validation is available."
            onRetry={() => {
              setState('loading');
              setAttempt((value) => value + 1);
            }}
          />
        ) : (
          <ResetForm schema={schema} service={service} onSuccess={() => setComplete(true)} />
        )}
      </div>
      <p className="activation-footnote">
        Reset links expire after one hour. Need help?{' '}
        <a href="/app/reset-password">Request a new link</a>.
      </p>
    </section>
  );
}

function ResetForm({
  schema,
  service,
  onSuccess
}: {
  schema: PublicSchema;
  service: ResetCompletionService;
  onSuccess: () => void;
}) {
  const [show, setShow] = useState(false);
  const form = usePublicForm({
    schema,
    initialValues: { password: '', passwordConfirmation: '' },
    submit: (values) => service.submit(values),
    onSuccess,
    failureMessage
  });
  if (!service.usable()) return <LinkRecovery />;
  const guidance = schema.fields.password?.rules.find((rule) => rule.type === 'MinLength')?.message;
  return (
    <form noValidate onSubmit={form.handleSubmit} aria-busy={form.busy}>
      <h2>Choose your new password</h2>
      <p>This ends every session for the reset account. You will need to sign in again.</p>
      <TextField
        label="New password"
        type={show ? 'text' : 'password'}
        autoComplete="new-password"
        required
        description={`${guidance ?? 'Password guidance is unavailable.'} Spaces and Unicode are welcome. Common passwords are not accepted.`}
        {...form.field('password')}
      />
      <TextField
        label="Confirm new password"
        type={show ? 'text' : 'password'}
        autoComplete="new-password"
        required
        description="Enter the same password again. Paste and password managers are welcome."
        {...form.field('passwordConfirmation')}
      />
      <Button aria-pressed={show} onClick={() => setShow((value) => !value)}>
        {show ? 'Hide passwords' : 'Show passwords'}
      </Button>
      <div aria-live="polite" aria-atomic="true">
        {Object.keys(form.errors).length > 0 && <p>Check the highlighted password fields.</p>}
        {form.formError && (
          <Notice tone="danger" title="Reset not confirmed" announce="assertive">
            {form.formError} A system or network failure may still have completed the reset. Try
            signing in before requesting another link.
          </Notice>
        )}
      </div>
      <Button type="submit" busy={form.busy} busyLabel="Resetting password…">
        Reset password
      </Button>
    </form>
  );
}
