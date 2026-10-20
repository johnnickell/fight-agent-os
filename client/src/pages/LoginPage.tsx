import { useEffect, useRef, useState } from 'react';
import FormCheck from 'react-bootstrap/FormCheck';

import { Button } from '@/components/Button';
import { ContentState } from '@/components/ContentState';
import { TextField } from '@/components/forms/TextField';
import { Notice } from '@/components/Notice';
import { usePublicForm } from '@/features/validation/usePublicForm';

import type { ApiFailure } from '@/api/ApiResult';
import type { LoginService } from '@/features/auth/LoginService';
import type { PublicSchema } from '@/features/validation/PublicSchema';
import type { PublicSchemaService } from '@/features/validation/PublicSchemaService';

function failureMessage(failure: ApiFailure): string {
  if (failure.error.kind === 'authentication')
    return 'We couldn’t sign you in. Check your email and password, or try again later.';
  if (failure.error.kind === 'rate-limited')
    return 'Too many attempts. Wait 15 minutes before trying again.';
  if (failure.error.kind === 'authorization')
    return 'The secure request could not be verified. Try again to renew request protection.';
  return 'Sign-in is unavailable. Please try again.';
}

export function LoginPage({
  service,
  schemas,
  onAuthenticated
}: {
  service: LoginService;
  schemas: PublicSchemaService;
  onAuthenticated?: (() => void) | undefined;
}) {
  const [schema, setSchema] = useState<PublicSchema | null>(null);
  const [state, setState] = useState<'loading' | 'unavailable' | 'ready'>('loading');
  const [attempt, setAttempt] = useState(0);
  const [complete, setComplete] = useState(false);
  const success = useRef<HTMLHeadingElement>(null);
  useEffect(() => {
    document.title = 'Sign in — Fight Agent OS';
    const controller = new AbortController();
    void schemas.load('login', controller.signal).then((result) => {
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
    <section className="login-page" aria-labelledby="login-heading">
      <div className="login-introduction">
        <p className="login-eyebrow">FIGHT / AGENT OS</p>
        <h1 id="login-heading">Good work starts here.</h1>
        <p>Your workspace for building software. Focused. Deliberate. Yours.</p>
        <p className="login-invite-note">Invite-only access. No public registration.</p>
      </div>
      <div className="login-panel">
        {complete ? (
          <>
            <h2 ref={success} tabIndex={-1}>
              You’re signed in
            </h2>
            <Notice tone="success" title="Session established">
              Your password has been cleared. The authenticated dashboard and account controls are
              not available yet. Access stays only in this page’s memory; reloading currently
              requires sign-in.
            </Notice>
          </>
        ) : state === 'loading' ? (
          <ContentState
            kind="loading"
            title="Preparing secure sign-in"
            message="Loading form validation."
          />
        ) : state === 'unavailable' || !schema ? (
          <ContentState
            kind="error"
            title="Validation is unavailable"
            message="Sign-in cannot be submitted until validation is available."
            onRetry={() => {
              setState('loading');
              setAttempt((value) => value + 1);
            }}
          />
        ) : (
          <LoginForm
            schema={schema}
            service={service}
            onSuccess={() => {
              // Never consume navigation using authority cached before this login. The outer
              // owner receives only completion and must load a fresh principal before routing.
              setComplete(true);
              onAuthenticated?.();
            }}
          />
        )}
      </div>
    </section>
  );
}

function LoginForm({
  schema,
  service,
  onSuccess
}: {
  schema: PublicSchema;
  service: LoginService;
  onSuccess: () => void;
}) {
  const [remember, setRemember] = useState(false);
  const form = usePublicForm({
    schema,
    initialValues: { email: '', password: '' },
    submit: (values) => service.submit(values, remember),
    onSuccess,
    failureMessage
  });
  return (
    <form noValidate onSubmit={form.handleSubmit} aria-busy={form.busy}>
      <h2>Sign in</h2>
      <p>Welcome back. Use your invited email address.</p>
      <TextField
        label="Email"
        type="email"
        autoComplete="username"
        autoCapitalize="none"
        spellCheck={false}
        required
        {...form.field('email')}
      />
      <TextField
        label="Password"
        type="password"
        autoComplete="current-password"
        required
        description="Use your existing password. Paste and password managers are welcome."
        {...form.field('password')}
      />
      <div>
        <FormCheck
          id="login-remember"
          label="Keep me signed in on this device"
          checked={remember}
          onChange={(event) => setRemember(event.target.checked)}
          disabled={form.busy}
          aria-describedby="login-remember-help"
        />
        <p id="login-remember-help" className="login-help">
          Only on a device you trust. Remembered sessions end after 30 days idle or one year,
          whichever comes first. Otherwise, the browser-session cookie ends when your browser
          session ends; server limits still apply.
        </p>
      </div>
      <div aria-live="polite" aria-atomic="true">
        {Object.keys(form.errors).length > 0 && <p>Check the highlighted fields.</p>}
        {form.formError && (
          <Notice tone="danger" title="Sign-in not completed" announce="assertive">
            {form.formError}
          </Notice>
        )}
      </div>
      <Button type="submit" busy={form.busy} busyLabel="Signing in…">
        Sign in
      </Button>
      <a href="/app/login/recovery">Forgot your password?</a>
    </form>
  );
}

/** A working bounded destination, not a pretend reset request or broken future route. */
export function LoginRecoveryPage() {
  useEffect(() => {
    document.title = 'Sign-in help — Fight Agent OS';
  }, []);
  return (
    <section className="activation-page" aria-labelledby="recovery-heading">
      <h1 id="recovery-heading">Need help signing in?</h1>
      <p>
        Password recovery is not available here yet. Contact your installation administrator for
        help. Never send anyone your password.
      </p>
      <a href="/app/login">Return to sign in</a>
    </section>
  );
}
