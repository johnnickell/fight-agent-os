export type TableOrder = 'asc' | 'desc';
export type FilterValue = string | number | boolean | readonly (string | number | boolean)[];
export type TableFilters = Readonly<Record<string, FilterValue>>;

export type TableQuery<S extends string, F extends TableFilters> = Readonly<{
  page: number;
  perPage: number;
  sort: S;
  order: TableOrder;
  search: string;
  filters: F;
}>;

export type TablePolicy<S extends string, F extends TableFilters> = Readonly<{
  sorts: readonly S[];
  defaultSort: S;
  search?: boolean;
  emptyFilters: F;
  /** Return a typed allowlisted object, or null for unsupported fields or values. */
  decodeFilters?: (filters: TableFilters) => F | null;
}>;

const keys = ['page', 'per_page', 'sort', 'order', 'search', 'filters'] as const;
const forbidden = new Set(['__proto__', 'constructor', 'prototype']);
const encoder = new TextEncoder();
export const maximumTablePage = 1000;
const maxFilterBytes = 1024;
const maxSearchLength = 120;
const wellFormed = (value: string) => !/[\uD800-\uDFFF]/u.test(value);

function scalar(value: unknown): value is string | number | boolean {
  return (
    (typeof value === 'string' && value.length <= 120 && wellFormed(value)) ||
    (typeof value === 'number' && Number.isFinite(value)) ||
    typeof value === 'boolean'
  );
}

function safeFilters(value: unknown): value is TableFilters {
  if (typeof value !== 'object' || value === null || Array.isArray(value)) return false;
  const entries: [string, unknown][] = Object.entries(value);
  return (
    entries.length <= 16 &&
    entries.every(
      ([key, field]) =>
        /^[A-Za-z][A-Za-z0-9_]{0,31}$/.test(key) &&
        !forbidden.has(key) &&
        (scalar(field) || (Array.isArray(field) && field.length <= 16 && field.every(scalar)))
    )
  );
}

function json(filters: TableFilters): string {
  return JSON.stringify(
    Object.fromEntries(
      Object.keys(filters)
        .sort()
        .map((key) => [key, filters[key]])
    )
  );
}

function encode(value: string): string {
  return btoa(String.fromCharCode(...encoder.encode(value)))
    .replaceAll('+', '-')
    .replaceAll('/', '_')
    .replace(/=+$/, '');
}

function decode(value: string): TableFilters | null {
  if (value.length > Math.ceil((maxFilterBytes * 4) / 3) || !/^[A-Za-z0-9_-]+$/.test(value))
    return null;
  try {
    const bytes = Uint8Array.from(atob(value.replaceAll('-', '+').replaceAll('_', '/')), (c) =>
      c.charCodeAt(0)
    );
    const raw = new TextDecoder('utf-8', { fatal: true }).decode(bytes);
    if (bytes.length > maxFilterBytes || encode(raw) !== value) return null;
    const result: unknown = JSON.parse(raw);
    if (!safeFilters(result)) return null;
    // Detect duplicate keys even when whitespace, escape spelling or order is non-canonical.
    const members = [...raw.matchAll(/"(?:[^"\\]|\\.)*"/g)].filter((match) =>
      raw
        .slice(match.index + match[0].length)
        .trimStart()
        .startsWith(':')
    );
    return members.length === Object.keys(result).length ? result : null;
  } catch {
    return null;
  }
}

function bounded(value: number, max: number, fallback: number): number {
  return Number.isInteger(value) && value >= 1 && value <= max ? value : fallback;
}

export function normalizeTableQuery<S extends string, F extends TableFilters>(
  query: TableQuery<S, F>,
  policy: TablePolicy<S, F>
): TableQuery<S, F> {
  const filters =
    safeFilters(query.filters) && encoder.encode(json(query.filters)).length <= maxFilterBytes
      ? policy.decodeFilters?.(query.filters)
      : null;
  return {
    page: bounded(query.page, maximumTablePage, 1),
    perPage: bounded(query.perPage, 100, 25),
    sort: policy.sorts.includes(query.sort) ? query.sort : policy.defaultSort,
    order: query.order === 'desc' ? 'desc' : 'asc',
    search:
      policy.search && query.search.length <= maxSearchLength && wellFormed(query.search)
        ? query.search.trim()
        : '',
    filters: filters ?? policy.emptyFilters
  };
}

export function tableQueryKey(query: TableQuery<string, TableFilters>): string {
  return JSON.stringify([
    query.page,
    query.perPage,
    query.sort,
    query.order,
    query.search,
    json(query.filters)
  ]);
}

export function readTableQuery<S extends string, F extends TableFilters>(
  input: URLSearchParams,
  policy: TablePolicy<S, F>
): { query: TableQuery<S, F>; invalid: boolean } {
  let invalid = false;
  const oversized = input.toString().length > 8192;
  const get = (key: (typeof keys)[number]): string | undefined => {
    const values = input.getAll(key);
    if (
      values.length > 1 ||
      (values.length === 1 && (oversized || values[0]?.includes('\uFFFD')))
    ) {
      invalid = true;
      return undefined;
    }
    return values[0];
  };
  const number = (key: 'page' | 'per_page', max: number, fallback: number) => {
    const value = get(key);
    if (value === undefined) return fallback;
    if (!/^[0-9]{1,4}$/.test(value) || Number(value) < 1 || Number(value) > max) {
      invalid = true;
      return fallback;
    }
    return Number(value);
  };
  const page = number('page', maximumTablePage, 1);
  const perPage = number('per_page', 100, 25);
  const rawSort = get('sort');
  const sort = policy.sorts.find((field) => field === rawSort);
  const order = get('order');
  const search = get('search');
  const encoded = get('filters');
  const decoded = encoded === undefined ? null : decode(encoded);
  const filters = decoded === null ? null : policy.decodeFilters?.(decoded);
  invalid ||=
    (rawSort !== undefined && sort === undefined) ||
    (order !== undefined && order !== 'asc' && order !== 'desc') ||
    (search !== undefined && (!policy.search || search.length > maxSearchLength)) ||
    (encoded !== undefined && !filters);
  return {
    invalid,
    query: normalizeTableQuery(
      {
        page,
        perPage,
        sort: sort ?? policy.defaultSort,
        order: order === 'desc' ? 'desc' : 'asc',
        search: search ?? '',
        filters: filters ?? policy.emptyFilters
      },
      policy
    )
  };
}

/** Replace only table-owned parameters; unrelated host values stay in the URL. */
export function writeTableQuery(
  input: URLSearchParams,
  query: TableQuery<string, TableFilters>
): URLSearchParams {
  const output = new URLSearchParams(input);
  for (const key of keys) output.delete(key);
  output.set('page', String(query.page));
  output.set('per_page', String(query.perPage));
  output.set('sort', query.sort);
  output.set('order', query.order);
  if (query.search) output.set('search', query.search);
  if (Object.keys(query.filters).length > 0) output.set('filters', encode(json(query.filters)));
  return output;
}
