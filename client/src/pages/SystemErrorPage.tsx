import { PageHeading } from '@/components/PageHeading';

export function SystemErrorPage() {
  return (
    <>
      <PageHeading>Application unavailable</PageHeading>
      <p role="alert">
        The application could not start. Try reloading. If the problem continues, contact the
        installation operator.
      </p>
      <a className="btn btn-outline-primary" href="/app">
        Reload application
      </a>
    </>
  );
}
