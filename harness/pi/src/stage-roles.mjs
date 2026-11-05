import { readFileSync } from 'node:fs';

// Shared with the Python process owner. Build is coordination, not a QA stage alias.
const roles = JSON.parse(readFileSync(new URL('./stage-roles.json', import.meta.url), 'utf8'));
export const normalizeRole = value => typeof value === 'string' ? value.trim().toLowerCase().replace(/\s+/g, '-') : '';
export function stageRole(value) {
  const key = normalizeRole(value);
  return Object.entries(roles).find(([, aliases]) => aliases.includes(key))?.[0] ?? null;
}
