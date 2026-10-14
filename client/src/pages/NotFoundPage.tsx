import { PageHeading } from '@/components/PageHeading';

export function NotFoundPage() {
  return (
    <>
      <PageHeading>Page not found</PageHeading>
      <p>This page is not part of the application foundation.</p>
      <a className="btn btn-outline-primary" href="/app">
        Return to foundation
      </a>
    </>
  );
}
