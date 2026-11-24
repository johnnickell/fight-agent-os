import { useEffect, useState } from 'react';

import {
  DataTable,
  readTableQuery,
  TablePageSize,
  TablePageSummary,
  TablePagination,
  TableSearch,
  useTableLocation,
  writeTableQuery
} from '@fight/react/controls';
import { act, render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import type { TableChange, TableData, TablePolicy, TableQuery } from '@fight/react/controls';

type Filters = Readonly<{ cities?: readonly string[] }>;
type Query = TableQuery<'Title', Filters>;
const policy: TablePolicy<'Title', Filters> = {
  sorts: ['Title'],
  defaultSort: 'Title',
  search: true,
  emptyFilters: {},
  decodeFilters(input) {
    if (Object.keys(input).length === 0) return {};
    const cities = input.cities;
    if (
      Object.keys(input).length !== 1 ||
      !Array.isArray(cities) ||
      !cities.every((city): city is string => typeof city === 'string')
    )
      return null;
    return cities.length ? { cities } : {};
  }
};

type Book = { title: string; city: string };
const books = [
  { title: 'Rain', city: 'São Paulo' },
  { title: 'Sun', city: 'Paris' },
  { title: 'Snow', city: 'Montréal' }
];

/** A receiving application owns the searchable dataset and matching count before slicing. */
function findBooks(query: Query, signal: AbortSignal): Promise<TableData<Book>> {
  signal.throwIfAborted();
  const matches = books.filter(
    (book) =>
      book.title.toLowerCase().includes(query.search.toLowerCase()) &&
      (!query.filters.cities || query.filters.cities.includes(book.city))
  );
  matches.sort(
    (left, right) => left.title.localeCompare(right.title) * (query.order === 'asc' ? 1 : -1)
  );
  const start = (query.page - 1) * query.perPage;
  return Promise.resolve({
    kind: 'ready',
    rows: matches.slice(start, start + query.perPage),
    total: matches.length
  });
}

function CityFilter({
  filters,
  onChange
}: {
  filters: Filters;
  onChange: (filters: Filters) => void;
}) {
  return (
    <button type="button" onClick={() => onChange({ cities: ['Montréal', 'São Paulo'] })}>
      {filters.cities?.join(', ') ?? 'Filter cities'}
    </button>
  );
}

function Books({
  read,
  changed
}: {
  read: typeof findBooks;
  changed: (query: Query, reason: TableChange) => void;
}) {
  const location = useTableLocation(policy);
  const { query, current } = location;
  const [page, setPage] = useState<{ query: Query; data: TableData<Book> }>();
  useEffect(() => {
    const controller = new AbortController();
    void read(query, controller.signal).then((data) => {
      if (!controller.signal.aborted && current.current === query) setPage({ query, data });
    });
    return () => controller.abort();
  }, [query, current, read]);
  return (
    <DataTable
      policy={policy}
      initial={query}
      restore={location.restore}
      onChange={(next, reason) => {
        changed(next, reason);
        location.change(next, reason);
      }}
      data={query === page?.query ? page.data : { kind: 'loading' }}
      rowKey={(book) => book.title}
      caption="Books"
      filterControls={CityFilter}
      columns={[{ key: 'title', sort: 'Title', label: 'Title', cell: (book) => book.title }]}
    >
      {location.notice && <p role="status">Invalid table options were reset.</p>}
      <TableSearch />
      <TablePagination />
      <TablePageSize />
      <TablePageSummary />
    </DataTable>
  );
}

beforeEach(() => window.history.replaceState(null, '', '/books'));

describe('stateful tables', () => {
  it('submits one search, resets the page, replaces history and restores without committing a new search', async () => {
    window.history.replaceState(null, '', '/books?page=2&per_page=1&search=s');
    const changed = vi.fn();
    const read = vi.fn(findBooks);
    const view = render(<Books read={read} changed={changed} />);
    expect(await screen.findByText('Sun')).toBeVisible();
    const entries = window.history.length;
    const user = userEvent.setup();
    await user.clear(screen.getByRole('searchbox'));
    await user.type(screen.getByRole('searchbox'), 'rai');
    expect(read).toHaveBeenCalledTimes(1);
    await user.keyboard('{Enter}');
    expect(await screen.findByText('Rain')).toBeVisible();
    expect(changed).toHaveBeenCalledExactlyOnceWith(
      { page: 1, perPage: 1, sort: 'Title', order: 'asc', search: 'rai', filters: {} },
      'search'
    );
    expect(window.history.length).toBe(entries);
    expect(new URLSearchParams(window.location.search).get('search')).toBe('rai');
    await user.click(screen.getByRole('button', { name: 'Search' }));
    expect(changed).toHaveBeenCalledTimes(1);
    await user.type(screen.getByRole('searchbox'), ' draft');
    await act(async () => {
      window.dispatchEvent(new PopStateEvent('popstate'));
      await Promise.resolve();
    });
    expect(screen.getByRole('searchbox')).toHaveValue('rai');
    expect(read).toHaveBeenCalledTimes(2);
    view.unmount();
    render(<Books read={read} changed={changed} />);
    expect(screen.getByRole('searchbox')).toHaveValue('rai');
    await waitFor(() => expect(read).toHaveBeenCalledTimes(3));
    expect(changed).toHaveBeenCalledTimes(1);
  });

  it.each([
    ['%GG', '', true],
    ['%ED%A0%80', '', true],
    ['%EF%BF%BD', '\uFFFD', false],
    ['%E9%9B%AA+%2B', '雪 +', false]
  ])('restores or rejects raw search %s before the query runs', (encoded, value, invalid) => {
    window.history.replaceState(null, '', `/books?%73earch=${encoded}`);
    const read = vi.fn(findBooks);
    render(<Books read={read} changed={vi.fn()} />);
    expect(read).toHaveBeenCalledExactlyOnceWith(
      expect.objectContaining({ search: value }),
      expect.anything()
    );
    expect(screen.queryByText('Invalid table options were reset.') !== null).toBe(invalid);
    expect(new URLSearchParams(window.location.search).get('search')).toBe(value || null);
  });

  it('round-trips page and search with a large unrelated host value', () => {
    const input = new URLSearchParams({ host: 'x'.repeat(8193) });
    const expected: Query = {
      page: 2,
      perPage: 1,
      sort: 'Title',
      order: 'desc',
      search: '\uFFFD',
      filters: {}
    };
    const written = writeTableQuery(input, expected);
    expect(written.get('host')).toBe(input.get('host'));
    expect(readTableQuery(written.toString(), policy)).toEqual({ query: expected, invalid: false });
  });

  it('commits typed filters and page size as normalized transitions', async () => {
    window.history.replaceState(null, '', '/books?page=2&per_page=1');
    const changed = vi.fn<(query: Query, reason: TableChange) => void>();
    render(<Books read={findBooks} changed={changed} />);
    expect(await screen.findByText('Snow')).toBeVisible();
    await userEvent.click(screen.getByRole('button', { name: 'Filter cities' }));
    expect(await screen.findByText('Rain')).toBeVisible();
    const { query } = readTableQuery(window.location.search, policy);
    expect(query.filters).toEqual({ cities: ['Montréal', 'São Paulo'] });
    expect(query.page).toBe(1);
    await userEvent.selectOptions(screen.getByLabelText('Rows per page'), '10');
    expect(await screen.findByText('Snow')).toBeVisible();
    expect(changed.mock.calls.map(([, reason]) => reason)).toEqual(['filters', 'perPage']);
  });

  it('drops malformed filter JSON and oversized owned values without losing usable options', () => {
    const { invalid, query } = readTableQuery(
      `?pa%ge=3&page=2&filters=ew&search=${'x'.repeat(8193)}`,
      policy
    );
    expect(invalid).toBe(true);
    expect(query).toEqual({
      page: 2,
      perPage: 25,
      sort: 'Title',
      order: 'asc',
      search: '',
      filters: {}
    });
  });

  it('normalizes malformed known parameters while preserving host values', () => {
    const input =
      'page=2&page=3&per_page=101&sort=Other&order=down&search=%FF&filters=bad=&host=other&host=also';
    const { invalid, query } = readTableQuery(input, policy);
    expect(invalid).toBe(true);
    expect(writeTableQuery(new URLSearchParams(input), query).toString()).toBe(
      'host=other&host=also&page=1&per_page=25&sort=Title&order=asc'
    );
  });
});
