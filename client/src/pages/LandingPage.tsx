import { PageHeading } from '@/components/PageHeading';

import type { ApplicationRoute } from '@/routes/applicationRoutes';

/** Truthful registered destinations, replaced by the later owning journeys */
export default function LandingPage({ route }: { route: ApplicationRoute }) {
  return (
    <section>
      <PageHeading>{route.title}</PageHeading>
      <p>{route.availability}</p>
      {route.pathname === '/app/account' ? (
        <p>
          <a href="/app/account/password">Change password</a> — confirm your current password and
          end all sessions.
        </p>
      ) : (
        <p>No records or operations are provided on this destination yet.</p>
      )}
    </section>
  );
}
