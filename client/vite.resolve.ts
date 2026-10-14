import { fileURLToPath } from 'node:url';

// Vite serves both the production test scope and the separate catalog TS project.
// Match tsconfig.json's single application root without per-directory aliases.
export const applicationResolve = {
  alias: { '@': fileURLToPath(new URL('./src', import.meta.url)) }
};
