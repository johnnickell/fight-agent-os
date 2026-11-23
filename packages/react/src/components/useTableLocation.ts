import { useCallback, useEffect, useRef, useState } from 'react';

import {
  normalizeTableQuery,
  readTableQuery,
  tableQueryKey,
  writeTableQuery
} from './tableQuery.js';

import type { TableFilters, TablePolicy, TableQuery } from './tableQuery.js';

export type TableChange = 'page' | 'perPage' | 'sort' | 'search' | 'filters';

/** Optional browser adapter; the table itself has no browser or router dependency. */
export function useTableLocation<S extends string, F extends TableFilters>(
  policy: TablePolicy<S, F>,
  navigation?: Readonly<{ invalid: boolean }>
) {
  const [initial] = useState(() => readTableQuery(window.location.search, policy));
  const [query, setQuery] = useState(initial.query);
  const current = useRef(initial.query);
  const [pathname] = useState(() => window.location.pathname);
  const [notice, setNotice] = useState(initial.invalid || navigation?.invalid === true);
  const previousNavigation = useRef(navigation);
  const [restore, setRestore] = useState({ query: initial.query, revision: 0 });

  const store = useCallback((next: TableQuery<S, F>) => {
    if (tableQueryKey(current.current) === tableQueryKey(next)) return;
    current.current = next;
    setQuery(next);
  }, []);

  const write = useCallback(
    (next: TableQuery<S, F>, method: 'replaceState' | 'pushState') => {
      const url = new URL(window.location.href);
      if (url.pathname !== pathname) return;
      url.search = writeTableQuery(url.searchParams, next).toString();
      if (url.href !== window.location.href) window.history[method](window.history.state, '', url);
    },
    [pathname]
  );

  const sync = useCallback(
    (invalid = false) => {
      if (window.location.pathname !== pathname) return;
      const next = readTableQuery(window.location.search, policy);
      write(next.query, 'replaceState');
      store(next.query);
      setNotice(next.invalid || invalid);
      setRestore((previous) => ({ query: next.query, revision: previous.revision + 1 }));
    },
    [pathname, policy, store, write]
  );

  useEffect(() => {
    write(readTableQuery(window.location.search, policy).query, 'replaceState');
    const pop = () => sync();
    window.addEventListener('popstate', pop);
    return () => window.removeEventListener('popstate', pop);
  }, [policy, sync, write]);

  useEffect(() => {
    const changed = previousNavigation.current !== navigation;
    previousNavigation.current = navigation;
    if (changed && navigation) sync(navigation.invalid);
  }, [navigation, sync]);

  const restoreQuery = useCallback(
    (input: TableQuery<S, F>) => {
      if (window.location.pathname !== pathname) return;
      const next = normalizeTableQuery(input, policy);
      store(next);
      setRestore((previous) => ({ query: next, revision: previous.revision + 1 }));
      write(next, 'replaceState');
    },
    [pathname, policy, store, write]
  );

  return {
    query,
    current,
    restore,
    notice,
    change(next: TableQuery<S, F>, reason: TableChange) {
      if (window.location.pathname !== pathname) return;
      store(next);
      setNotice(false);
      write(next, reason === 'search' ? 'replaceState' : 'pushState');
    },
    restoreQuery
  };
}
