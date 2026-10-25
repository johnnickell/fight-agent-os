import { useEffect, useRef, useState } from 'react';

import { Button } from '@/components/Button';
import { ContentState } from '@/components/ContentState';
import { TextField } from '@/components/forms/TextField';
import { Notice } from '@/components/Notice';
import { usePublicForm } from '@/features/validation/usePublicForm';

import type { ApiFailure } from '@/api/ApiResult';
import type { PasswordChangeService } from '@/features/auth/PasswordChangeService';
import type { PublicSchema } from '@/features/validation/PublicSchema';
import type { PublicSchemaService } from '@/features/validation/PublicSchemaService';

function failureMessage(failure: ApiFailure): string {
  if (failure.error.kind === 'conflict')
    return 'Password change was not accepted. Check your current password and try again.';
  if (failure.error.kind === 'rate-limited')
    return 'Too many attempts. Wait 15 minutes before trying again.';
  return 'Password change is not confirmed. A system or network failure may still have completed it. Try signing in before submitting again.';
}

export default function PasswordChangePage({
  service,
  schemas
}: {
  service: PasswordChangeService;
  schemas: PublicSchemaService;
}) {
  const [schema, setSchema] = useState<PublicSchema | null>(null);
  const [state, setState] = useState<'loading' | 'ready' | 'unavailable'>('loading');
  const [attempt, setAttempt] = useState(0);
  const [failure, setFailure] = useState<string | null>(null);
  const failureRegion = useRef<HTMLDivElement>(null);
  useEffect(() => {
    if (failure) failureRegion.current?.focus();
  }, [failure]);
  const [formGeneration, setFormGeneration] = useState(0);
  useEffect(() => {
    const controller = new AbortController();
    void schemas.load('password_change', controller.signal).then((result) => {
      if (controller.signal.aborted) return;
      if (result.ok) {
        setSchema(result.schema);
        setState('ready');
      } else setState('unavailable');
    });
    return () => controller.abort();
  }, [schemas, attempt]);
  return (
    <section className="activation-page" aria-labelledby="password-change-heading">
      <h1 id="password-change-heading">Change Password</h1>
      <p>
        Confirm your current password, then choose a new one. This ends every session, including
        this one.
      </p>
      <div className="activation-panel">
        {failure && (
          <div ref={failureRegion} tabIndex={-1} role="region" aria-label="Password change result">
            <Notice tone="danger" title="Password change not confirmed" announce="assertive">
              {failure} Password fields have been cleared.
            </Notice>
          </div>
        )}
        {state === 'loading' ? (
          <ContentState
            kind="loading"
            title="Preparing secure change"
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
          <PasswordChangeForm
            key={formGeneration}
            schema={schema}
            service={service}
            onFailure={(result) => {
              setFailure(failureMessage(result));
              setFormGeneration((value) => value + 1);
            }}
            onEdit={() => setFailure(null)}
          />
        )}
      </div>
      <p>
        <a href="/app/account">Back to Account &amp; Security</a>
      </p>
    </section>
  );
}

function PasswordChangeForm({
  schema,
  service,
  onFailure,
  onEdit
}: {
  schema: PublicSchema;
  service: PasswordChangeService;
  onFailure: (failure: ApiFailure) => void;
  onEdit: () => void;
}) {
  const [show, setShow] = useState(false);
  const form = usePublicForm({
    schema,
    initialValues: { currentPassword: '', password: '', passwordConfirmation: '' },
    submit: (values) => service.submit(values),
    onSuccess: () => {
      /* The service terminal barrier removes the entire protected form. */
    },
    failureMessage: (failure) => {
      if (failure.error.kind !== 'validation') onFailure(failure);
      return failureMessage(failure);
    }
  });
  const guidance = schema.fields.password?.rules.find((rule) => rule.type === 'MinLength')?.message;
  return (
    <form noValidate onSubmit={form.handleSubmit} onChange={onEdit} aria-busy={form.busy}>
      <TextField
        label="Current password"
        type={show ? 'text' : 'password'}
        autoComplete="current-password"
        required
        description="Enter your existing password unchanged. Paste and password managers are welcome."
        {...form.field('currentPassword')}
      />
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
        description="Enter the same new password again."
        {...form.field('passwordConfirmation')}
      />
      <Button aria-pressed={show} onClick={() => setShow((value) => !value)}>
        {show ? 'Hide passwords' : 'Show passwords'}
      </Button>
      <div aria-live="polite" aria-atomic="true">
        {Object.keys(form.errors).length > 0 && <p>Check the highlighted password fields.</p>}
        {form.formError && (
          <Notice tone="danger" title="Password change not confirmed" announce="assertive">
            {form.formError}
          </Notice>
        )}
      </div>
      <Button type="submit" busy={form.busy} busyLabel="Changing password…">
        Change password and sign out
      </Button>
      <Button onClick={form.reset}>Clear password fields</Button>
    </form>
  );
}
