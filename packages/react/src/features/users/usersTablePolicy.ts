import type { CatalogOrdering } from '../../api/ApiClient.js';
import type { TablePolicy } from '../../components/tableQuery.js';

/** Allowed directory query fields before and after admission. No search or filters. */
export const usersTablePolicy: TablePolicy<CatalogOrdering['sort'], Record<string, never>> = {
  sorts: ['ID', 'Created'],
  defaultSort: 'Created',
  emptyFilters: {}
};
