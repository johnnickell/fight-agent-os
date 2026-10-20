import { useEffect, useState } from 'react';

import { Button } from '@/components/Button';
import { ContentState } from '@/components/ContentState';
import { TextField } from '@/components/forms/TextField';
import { Notice } from '@/components/Notice';
import { usePublicForm } from '@/features/validation/usePublicForm';

import type { ApiFailure } from '@/api/ApiResult';
import type { ActivationService } from '@/features/auth/ActivationService';
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
  return (
    <ContentState
      kind="error"
      title="This link cannot be used"
      message="It may be expired, replaced or already used. Ask your administrator for a new invitation. For your security, reload and browser history do not restore an invitation."
    />
  );
}

export function ActivationPage({
  service,
  schemas
}: {
  service: ActivationService;
  schemas: PublicSchemaService;
}) {
  const [schema, setSchema] = useState<PublicSchema | null>(null);
  const [state, setState] = useState<'loading' | 'unavailable' | 'ready'>('loading');
  const [attempt, setAttempt] = useState(0);
  const [complete, setComplete] = useState(false);
  useEffect(() => {
    document.title = 'Activate invitation — Fight Agent OS';
    const controller = new AbortController();
    if (service.usable()) {
      void schemas.load('invitation_activation', controller.signal).then((result) => {
        if (controller.signal.aborted) return;
        if (result.ok) {
          setSchema(result.schema);
          setState('ready');
        } else setState('unavailable');
      });
    }
    return () => controller.abort();
  }, [service, schemas, attempt]);
  return (
    <section className="activation-page" aria-labelledby="activation-heading">
      <p className="activation-eyebrow">FIGHT / AGENT OS</p>
      <h1 id="activation-heading">Make it yours.</h1>
      <p className="activation-introduction">One invitation. Your own secure account.</p>
      <div className="activation-panel">
        {complete ? (
          <>
            <ContentState
              kind="success"
              title="Account activated"
              message="Your ordinary session is ready. Your password and invitation have been cleared from this page. The dashboard and account controls are not available yet."
            />
            <a href="/app">Continue to the application foundation</a>
          </>
        ) : !service.usable() ? (
          <LinkRecovery />
        ) : state === 'loading' ? (
          <ContentState
            kind="loading"
            title="Preparing secure activation"
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
          <ActivationForm schema={schema} service={service} onSuccess={() => setComplete(true)} />
        )}
      </div>
      <p className="activation-footnote">Invite-only access. No public registration.</p>
    </section>
  );
}

function ActivationForm({
  schema,
  service,
  onSuccess
}: {
  schema: PublicSchema;
  service: ActivationService;
  onSuccess: () => void;
}) {
  const form = usePublicForm({
    schema,
    initialValues: { password: '', passwordConfirmation: '' },
    submit: (values) => service.submit(values),
    onSuccess,
    failureMessage
  });
  if (!service.usable()) return <LinkRecovery />;
  const rules = schema.fields.password?.rules;
  const guidance = rules?.find((rule) => rule.type === 'MinLength')?.message;
  return (
    <form noValidate onSubmit={form.handleSubmit} aria-busy={form.busy}>
      <h2>Activate your invitation</h2>
      <p>Choose a password only you know. Your administrator never chooses it for you.</p>
      <TextField
        label="New password"
        type="password"
        autoComplete="new-password"
        required
        description={`${guidance ?? 'Password guidance is unavailable.'} Spaces and Unicode are welcome.`}
        {...form.field('password')}
      />
      <TextField
        label="Confirm new password"
        type="password"
        autoComplete="new-password"
        required
        description="Enter the same password again. You can paste or use a password manager."
        {...form.field('passwordConfirmation')}
      />
      <div aria-live="polite" aria-atomic="true">
        {Object.keys(form.errors).length > 0 && <p>Check the highlighted password fields.</p>}
        {form.formError && (
          <Notice tone="danger" title="Activation not completed" announce="assertive">
            {form.formError}
          </Notice>
        )}
      </div>
      <Button type="submit" busy={form.busy} busyLabel="Activating account…">
        Activate account
      </Button>
      <p className="activation-session-note">
        Creates an ordinary session on this browser. We won’t keep you signed in after the session
        ends.
      </p>
    </form>
  );
}
