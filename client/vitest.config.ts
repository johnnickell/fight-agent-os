import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vitest/config';

import { applicationResolve } from './vite.resolve.ts';

export default defineConfig({
  cacheDir: '../.runs/cache/client/vite',
  resolve: {
    ...applicationResolve,
    dedupe: [
      'react',
      'react-dom',
      'formik',
      'react-bootstrap',
      'vitest',
      '@testing-library/react',
      '@testing-library/user-event'
    ],
    alias: {
      ...applicationResolve.alias,
      '@fixtures/transport': fileURLToPath(
        new URL('../packages/react/src/api/ApiClient.ts', import.meta.url)
      ),
      '@fight/react/account': fileURLToPath(
        new URL('../packages/react/src/account.ts', import.meta.url)
      ),
      '@fight/react/controls': fileURLToPath(
        new URL('../packages/react/src/controls.ts', import.meta.url)
      )
    }
  },
  test: {
    environment: 'jsdom',
    setupFiles: ['./tests/setup.ts'],
    include: ['tests/**/*.test.{ts,tsx}', 'node_modules/@fight/react/tests/**/*.test.{ts,tsx}'],
    exclude: [],
    restoreMocks: true,
    coverage: {
      provider: 'v8',
      allowExternal: true,
      include: ['src/**/*.{ts,tsx}', '../packages/react/src/**/*.{ts,tsx}'],
      exclude: ['src/**/*.d.ts', 'src/main.tsx'],
      reportsDirectory: '../.runs/build/reports/client/coverage',
      reporter: ['text', 'json-summary', 'html']
    }
  }
});
