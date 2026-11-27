import { useCallback, useEffect, useLayoutEffect, useRef, useState } from 'react';

import { matchApplicationRoute } from '@/routes/applicationRoutes';

export type ApplicationNavigation = (target: string, replace?: boolean, invalid?: boolean) => void;

/** Native links remain usable; same-document navigation preserves the one volatile session owner */
export function useApplicationNavigation(initialPathname: string) {
  const [pathname, setPathname] = useState(initialPathname);
  const [tableRestoration, restoreTable] = useState<Readonly<{ invalid: boolean }>>();
  const previousPath = useRef(initialPathname);
  useLayoutEffect(() => {
    if (previousPath.current !== pathname)
      document.getElementById('main-content')?.focus({ preventScroll: true });
    previousPath.current = pathname;
  }, [pathname]);
  const navigate = useCallback<ApplicationNavigation>(
    (target, replace = false, invalid = false) => {
      const separator = target.indexOf('?');
      const path = separator < 0 ? target : target.slice(0, separator);
      const route = matchApplicationRoute(path);
      if (!route || target.includes('#')) return;
      const samePath = window.location.pathname === path;
      if (route.normalizeSearch && samePath && separator < 0) return;
      let address = path;
      if (separator >= 0) {
        if (!route.normalizeSearch) return;
        const normalized = route.normalizeSearch(target.slice(separator));
        address += `?${normalized.search}`;
        const notice = invalid || normalized.invalid;
        if (samePath && address === window.location.pathname + window.location.search) {
          if (notice) restoreTable({ invalid: true });
          return;
        }
        restoreTable({ invalid: notice });
      } else restoreTable(undefined);
      if (replace) window.history.replaceState(null, '', address);
      else window.history.pushState(null, '', address);
      setPathname(path);
    },
    []
  );
  useEffect(() => {
    const pop = () => {
      restoreTable(undefined);
      setPathname(window.location.pathname);
    };
    const click = (event: MouseEvent) => {
      if (
        event.defaultPrevented ||
        event.button !== 0 ||
        event.ctrlKey ||
        event.metaKey ||
        event.shiftKey ||
        event.altKey
      )
        return;
      // Embedded controls (including Swagger operation summaries) may contain href-less anchors.
      // Only real native links belong to application navigation.
      const anchor = event.target instanceof Element ? event.target.closest('a[href]') : null;
      if (
        !(anchor instanceof HTMLAnchorElement) ||
        anchor.hasAttribute('data-reload-application') ||
        anchor.hasAttribute('download') ||
        anchor.target ||
        anchor.getAttribute('href')?.startsWith('#')
      )
        return;
      const target = new URL(anchor.href, window.location.origin);
      if (
        target.origin !== window.location.origin ||
        target.search ||
        target.hash ||
        !matchApplicationRoute(target.pathname)
      )
        return;
      event.preventDefault();
      navigate(target.pathname);
    };
    window.addEventListener('popstate', pop);
    document.addEventListener('click', click);
    return () => {
      window.removeEventListener('popstate', pop);
      document.removeEventListener('click', click);
    };
  }, [navigate]);
  return { pathname, navigate, tableRestoration };
}
