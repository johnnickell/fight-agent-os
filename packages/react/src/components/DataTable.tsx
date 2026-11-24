import { createContext, useContext, useLayoutEffect, useRef, useState } from 'react';
import Table from 'react-bootstrap/Table';

import { Button } from './Button.js';
import { ContentState } from './ContentState.js';
import { maximumTablePage, normalizeTableQuery, tableQueryKey } from './tableQuery.js';

import type { TableFilters, TablePolicy, TableQuery } from './tableQuery.js';
import type { TableChange } from './useTableLocation.js';
import type { ComponentType, ReactNode } from 'react';

export type TableColumn<Row, Sort extends string> = Readonly<{
  key: string;
  label: string;
  sort?: Sort;
  rowHeader?: (row: Row) => string;
  cell: (row: Row) => ReactNode;
}>;

export type TableData<Row> =
  | { kind: 'loading' }
  | { kind: 'error'; title: string; message: string }
  | { kind: 'ready'; rows: readonly Row[]; total: number };

type Controls = {
  page: number;
  perPage: number;
  pages: number;
  total: number | null;
  search: string | null;
  revision: number;
  pageTo: (page: number) => void;
  resize: (perPage: number) => void;
  searchFor: (search: string) => void;
};

const TableContext = createContext<Controls | null>(null);

function useControls() {
  const controls = useContext(TableContext);
  if (!controls) throw new Error('Table controls must be nested in a DataTable.');
  return controls;
}

/** Presents one authorized result page and owns all committed table interactions. */
export function DataTable<Row, Sort extends string, Filters extends TableFilters>({
  policy,
  initial,
  restore,
  onChange,
  data,
  columns,
  rowKey,
  caption,
  children,
  filterControls: FilterControls,
  className
}: {
  policy: TablePolicy<Sort, Filters>;
  initial: TableQuery<Sort, Filters>;
  restore: { revision: number; query: TableQuery<Sort, Filters> };
  onChange: (query: TableQuery<Sort, Filters>, reason: TableChange) => void;
  data: TableData<Row>;
  columns: readonly TableColumn<Row, Sort>[];
  rowKey: (row: Row) => string;
  caption: string;
  children?: ReactNode;
  filterControls?: ComponentType<{ filters: Filters; onChange: (filters: Filters) => void }>;
  className?: string;
}) {
  const [local, setLocal] = useState(() => ({
    revision: restore.revision,
    query: normalizeTableQuery(initial, policy)
  }));
  const restored = local.revision !== restore.revision;
  const query = restored ? normalizeTableQuery(restore.query, policy) : local.query;
  const current = useRef(query);
  useLayoutEffect(() => {
    current.current = query;
  }, [query]);

  function commit(patch: Partial<TableQuery<Sort, Filters>>, reason: TableChange) {
    const previous = current.current;
    const normalized = normalizeTableQuery({ ...previous, ...patch }, policy);
    if (tableQueryKey(previous) === tableQueryKey(normalized)) return;
    const next = reason === 'page' ? normalized : { ...normalized, page: 1 };
    current.current = next;
    setLocal({ revision: restore.revision, query: next });
    onChange(next, reason);
  }

  const total = data.kind === 'ready' ? data.total : null;
  const pages = total === null ? 0 : Math.ceil(total / query.perPage);
  const controls: Controls = {
    page: query.page,
    perPage: query.perPage,
    pages,
    total,
    search: policy.search ? query.search : null,
    revision: restore.revision,
    pageTo: (page) => commit({ page }, 'page'),
    resize: (perPage) => commit({ perPage }, 'perPage'),
    searchFor: (search) => commit({ search }, 'search')
  };
  return (
    <TableContext.Provider value={controls}>
      {children}
      {FilterControls && (
        <FilterControls
          filters={query.filters}
          onChange={(filters) => commit({ filters }, 'filters')}
        />
      )}
      {data.kind === 'loading' ? (
        <ContentState
          kind="loading"
          title="Loading results"
          message="Reading the requested page."
        />
      ) : data.kind === 'error' ? (
        <ContentState kind="error" title={data.title} message={data.message} />
      ) : null}
      {data.kind !== 'error' && (
        <div
          className="table-responsive"
          role="region"
          aria-label={caption}
          // eslint-disable-next-line jsx-a11y/no-noninteractive-tabindex -- lets keyboard users scroll a wide table.
          tabIndex={0}
          aria-busy={data.kind === 'loading'}
        >
          <Table className={className ?? ''} role="table">
            <caption>{caption}</caption>
            <thead>
              <tr role="row">
                {columns.map(({ key, label, sort }) => (
                  <th
                    key={key}
                    scope="col"
                    aria-sort={
                      sort === query.sort
                        ? query.order === 'asc'
                          ? 'ascending'
                          : 'descending'
                        : undefined
                    }
                  >
                    {sort === undefined ? (
                      label
                    ) : (
                      <button
                        type="button"
                        className="btn btn-link p-0"
                        onClick={() =>
                          commit(
                            {
                              sort,
                              order: query.sort === sort && query.order === 'asc' ? 'desc' : 'asc'
                            },
                            'sort'
                          )
                        }
                      >
                        {label}{' '}
                        {sort === query.sort && (
                          <span aria-hidden="true">{query.order === 'asc' ? '↑' : '↓'}</span>
                        )}
                      </button>
                    )}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {data.kind === 'ready' &&
                data.rows.map((row) => (
                  <tr key={rowKey(row)} role="row">
                    {columns.map((column) =>
                      column.rowHeader ? (
                        <th
                          key={column.key}
                          scope="row"
                          role="rowheader"
                          aria-label={column.rowHeader(row)}
                        >
                          {column.cell(row)}
                        </th>
                      ) : (
                        <td key={column.key}>{column.cell(row)}</td>
                      )
                    )}
                  </tr>
                ))}
            </tbody>
          </Table>
        </div>
      )}
      {data.kind === 'ready' && data.rows.length === 0 && (
        <ContentState
          kind="empty"
          title="No results"
          message={
            total === 0 ? 'There are no results for this query.' : 'There are no rows on this page.'
          }
        />
      )}
    </TableContext.Provider>
  );
}

export function TablePagination({ label = 'Result pages' }: { label?: string }) {
  const { page, pages, pageTo } = useControls();
  return (
    <nav aria-label={label} className="d-flex flex-wrap gap-2 my-2">
      <Button disabled={pages === 0 || page === 1} onClick={() => pageTo(page - 1)}>
        Previous page
      </Button>
      <Button disabled={page >= Math.min(pages, maximumTablePage)} onClick={() => pageTo(page + 1)}>
        Next page
      </Button>
      {page > 1 && (
        <Button disabled={pages === 0} onClick={() => pageTo(1)}>
          First page
        </Button>
      )}
    </nav>
  );
}

export function TablePageSize() {
  const { perPage, resize } = useControls();
  return (
    <label className="d-inline-flex align-items-center gap-2 my-2">
      Rows per page
      <select
        className="form-select w-auto"
        value={perPage}
        onChange={(event) => resize(Number(event.currentTarget.value))}
      >
        {[...new Set([10, 25, 50, 100, perPage])]
          .sort((a, b) => a - b)
          .map((size) => (
            <option value={size} key={size}>
              {size}
            </option>
          ))}
      </select>
    </label>
  );
}

export function TablePageSummary() {
  const { total, page, pages } = useControls();
  return (
    <p role="status" aria-atomic="true">
      {total === null
        ? ''
        : total === 0
          ? '0 results.'
          : `Page ${page} of ${pages}. ${total} ${total === 1 ? 'result' : 'results'}.`}
      {pages > maximumTablePage && (
        <> Only the first {maximumTablePage.toLocaleString()} pages are available.</>
      )}
    </p>
  );
}

/** Holds an unsaved draft until the user submits; restoration cancels the draft. */
export function TableSearch() {
  const { search, searchFor, revision } = useControls();
  return search === null ? null : <SearchDraft key={revision} value={search} commit={searchFor} />;
}

function SearchDraft({ value, commit }: { value: string; commit: (search: string) => void }) {
  const [draft, setDraft] = useState(value);
  return (
    <form
      role="search"
      className="d-flex align-items-end flex-wrap gap-2 my-2"
      onSubmit={(event) => {
        event.preventDefault();
        const next = draft.trim();
        setDraft(next);
        commit(next);
      }}
    >
      <label>
        Search results
        <input
          className="form-control"
          type="search"
          value={draft}
          maxLength={120}
          onChange={(event) => setDraft(event.currentTarget.value)}
        />
      </label>
      <Button type="submit">Search</Button>
    </form>
  );
}
