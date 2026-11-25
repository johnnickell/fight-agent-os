import { useEffect, useState } from 'react';

import { apiFailure } from '../../api/ApiResult.js';

import type { CatalogOrdering } from '../../api/ApiClient.js';
import type { ApiResult } from '../../api/ApiResult.js';
import type { TablePolicy } from '../../components/tableQuery.js';
import type { AuthorityScope } from './AuthorityCache.js';
import type { CatalogPage, CatalogPagination } from './CatalogService.js';

export const authorityCatalogTablePolicy: TablePolicy<
  CatalogOrdering['sort'],
  Record<string, never>
> = {
  sorts: ['ID', 'Created'],
  defaultSort: 'Created',
  emptyFilters: {}
};

export type CatalogReader<Row> = (
  page: CatalogPagination,
  signal: AbortSignal
) => Promise<ApiResult<CatalogPage<Row>>>;

export function useCatalogRead<Row, Query>(
  load: CatalogReader<Row>,
  scope: AuthorityScope,
  allowed: boolean,
  query: CatalogPagination,
  currentQuery: { current: Query },
  expectedQuery: Query,
  attempt = 0
) {
  const [snapshot, setSnapshot] = useState<{
    scope: AuthorityScope;
    load: CatalogReader<Row>;
    query: CatalogPagination;
    attempt: number;
    result: ApiResult<CatalogPage<Row>>;
  }>();
  const current =
    allowed &&
    snapshot?.scope === scope &&
    snapshot.load === load &&
    snapshot.query === query &&
    snapshot.attempt === attempt;
  useEffect(() => {
    if (!allowed || !scope.isCurrent()) return;
    const controller = new AbortController();
    const retire = () => {
      controller.abort();
      queueMicrotask(() => setSnapshot((old) => (old?.scope === scope ? undefined : old)));
    };
    scope.signal.addEventListener('abort', retire, { once: true });
    const publish = (result: ApiResult<CatalogPage<Row>>) => {
      if (!controller.signal.aborted && scope.isCurrent() && currentQuery.current === expectedQuery)
        setSnapshot({ scope, load, query, attempt, result });
    };
    void load(query, controller.signal).then(publish, () => publish(apiFailure('system')));
    return () => {
      scope.signal.removeEventListener('abort', retire);
      controller.abort();
    };
  }, [load, scope, allowed, query, currentQuery, expectedQuery, attempt]);
  return current ? snapshot.result : undefined;
}
