import { PageHeading } from '@/components/PageHeading';

import type { ApplicationRoute } from '@/routes/applicationRoutes';

/** Truthful registered destinations, replaced by the later owning journeys */
export default function LandingPage({ route }: { route: ApplicationRoute }) {
  return (
    <section>
      <PageHeading>{route.title}</PageHeading>
      <p>{route.availability}</p>
      <p>No records or operations are provided on this destination yet.</p>
    </section>
  );
}
