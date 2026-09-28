import { lazy, Suspense } from 'react';
import { LoadingPage } from '../pages/LoadingPage';
import { NotFoundPage } from '../pages/NotFoundPage';

const HomePage = lazy(() => import('../pages/HomePage'));

export function matchShellRoute(pathname: string) {
  // Native same-origin links own navigation/history. No product routes or guards yet.
  return pathname === '/app' || pathname === '/app/'
    ? ({ name: 'home', access: 'public' } as const)
    : ({ name: 'not-found', access: 'public' } as const);
}

export function ShellRoutes({ pathname }: { pathname: string }) {
  const route = matchShellRoute(pathname);
  return (
    <Suspense fallback={<LoadingPage />}>
      {route.name === 'home' ? <HomePage /> : <NotFoundPage />}
    </Suspense>
  );
}
