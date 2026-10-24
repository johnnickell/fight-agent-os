import { useCallback, useEffect, useLayoutEffect, useRef, useState } from 'react';

import { matchApplicationRoute } from '@/routes/applicationRoutes';

/** Native links remain usable; same-document navigation preserves the one volatile session owner */
export function useApplicationNavigation(initialPathname: string) {
  const [pathname, setPathname] = useState(initialPathname);
  const previousPath = useRef(initialPathname);
  useLayoutEffect(() => {
    if (previousPath.current !== pathname) document.getElementById('main-content')?.focus();
    previousPath.current = pathname;
  }, [pathname]);
  const navigate = useCallback((target: string, replace = false) => {
    // All registered destinations currently admit no query or fragment state.
    if (!matchApplicationRoute(target)) return;
    if (replace) window.history.replaceState(null, '', target);
    else window.history.pushState(null, '', target);
    setPathname(target);
  }, []);
  useEffect(() => {
    const pop = () => setPathname(window.location.pathname);
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
  return { pathname, navigate };
}
