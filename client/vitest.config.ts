import { defineConfig } from 'vitest/config';

import { applicationResolve } from './vite.resolve.ts';

export default defineConfig({
  cacheDir: '../.runs/client/vite',
  resolve: applicationResolve,
  test: {
    environment: 'jsdom',
    setupFiles: ['./tests/setup.ts'],
    include: ['tests/**/*.test.{ts,tsx}'],
    restoreMocks: true,
    coverage: {
      provider: 'v8',
      include: ['src/**/*.{ts,tsx}'],
      exclude: ['src/**/*.d.ts', 'src/main.tsx'],
      reportsDirectory: '../.runs/client/coverage',
      reporter: ['text', 'json-summary', 'html']
    }
  }
});
