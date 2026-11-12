import { PageHeading } from '@/components/PageHeading';

export function NotFoundPage() {
  return (
    <>
      <PageHeading>Page not found</PageHeading>
      <p>This page is not available.</p>
      <a className="btn application-recovery-link" href="/">
        Return to application
      </a>
    </>
  );
}
