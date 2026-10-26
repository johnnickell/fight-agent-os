import { ContentPanel } from '@/components/ContentPanel';
import { PageHeading } from '@/components/PageHeading';
import { evaluateAccess } from '@/features/authority/evaluateAccess';
import { useAuthority, useAuthorityPresentation } from '@/features/authority/useAuthority';
import { applicationRoutes } from '@/routes/applicationRoutes';

import type { AuthorityCache } from '@/features/authority/AuthorityCache';
import type { ApplicationRoute } from '@/routes/applicationRoutes';

function DestinationLinks({
  routes,
  label,
  cache
}: {
  routes: readonly ApplicationRoute[];
  label: string;
  cache: AuthorityCache;
}) {
  const state = useAuthority(cache);
  return (
    <nav aria-label={label}>
      <ul className="dashboard-destinations">
        {routes.map((route) => (
          <li key={route.pathname}>
            <a
              href={route.pathname}
              aria-disabled={state.status !== 'authenticated' || undefined}
              onClick={(event) => {
                if (cache.getSnapshot().status !== 'authenticated') event.preventDefault();
              }}
            >
              {route.title}
            </a>
            <p>{route.availability}</p>
          </li>
        ))}
      </ul>
    </nav>
  );
}

/** Presents only safe current authority and honest registered destinations, never product data */
export default function DashboardPage({ cache }: { cache: AuthorityCache }) {
  const state = useAuthorityPresentation(cache);
  const dashboard = applicationRoutes.find((route) => route.destination === 'dashboard');
  if (
    state.status !== 'authenticated' ||
    evaluateAccess(state, dashboard?.requirements) !== 'allowed'
  )
    return null;
  const allowed = applicationRoutes.filter(
    (route) => evaluateAccess(state, route.requirements) === 'allowed'
  );
  const security = allowed.filter(
    (route) => route.pathname === '/app/account' || route.pathname === '/app/account/sessions'
  );
  const administration = allowed.filter(
    (route) =>
      route.pathname === '/app/admin/users' ||
      route.pathname === '/app/admin/invite' ||
      route.pathname === '/app/admin/invitations'
  );
  return (
    <div className="dashboard-page">
      <header>
        <PageHeading>Dashboard</PageHeading>
        <p className="dashboard-introduction">Your secure starting point.</p>
        <p>
          Signed in as <strong>{state.principal.email}</strong>
        </p>
      </header>
      <ContentPanel title="Available today">
        <p>
          Sign in, session restoration, current-access checks, appearance preferences and
          current-session sign out are available. Account destinations provide password change and
          owned-session visibility. The Users directory is available with current read access;
          invitation shortcuts explain workflows still unavailable.
        </p>
      </ContentPanel>
      <div className="dashboard-grid">
        <ContentPanel title="Account and security">
          <p>Your account destinations, based on your current access.</p>
          <DestinationLinks
            cache={cache}
            routes={security}
            label="Dashboard account and security"
          />
        </ContentPanel>
        {administration.length > 0 && (
          <ContentPanel title="Administration">
            <p>
              Each shortcut is shown only for its own permission. Users opens the read-only
              directory; invitation destinations explain workflows that are not available yet.
            </p>
            <DestinationLinks
              cache={cache}
              routes={administration}
              label="Dashboard administration"
            />
          </ContentPanel>
        )}
      </div>
      <ContentPanel title="Not installed yet">
        <p>
          Planning, observability and agent sessions are not installed in this Dashboard. Charts,
          metrics and workspace switching are not installed either.
        </p>
        <p>
          There are no activity feeds, planning records, agents, session lists or workspace data to
          show here. This is an availability explanation, not an empty result from a product-data
          service.
        </p>
      </ContentPanel>
    </div>
  );
}
