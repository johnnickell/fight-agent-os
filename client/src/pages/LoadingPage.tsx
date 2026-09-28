import { PageHeading } from '../components/PageHeading';

export function LoadingPage() {
  return (
    <>
      <PageHeading>Loading application</PageHeading>
      <p role="status">Please wait while the page loads.</p>
    </>
  );
}
