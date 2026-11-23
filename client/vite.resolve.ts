import { fileURLToPath } from 'node:url';

// Vite serves both the production test scope and the separate catalog TS project.
// Match tsconfig.json's single application root without per-directory aliases.
export const applicationResolve = {
  preserveSymlinks: true,
  alias: {
    '@': fileURLToPath(new URL('./src', import.meta.url)),
    '@fixtures/transport': fileURLToPath(
      new URL('./node_modules/@fight/react/dist/api/ApiClient.js', import.meta.url)
    ),
    '@fixtures': fileURLToPath(new URL('./tests', import.meta.url))
  }
};
