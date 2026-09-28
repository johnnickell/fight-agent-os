import { defineConfig } from 'vitest/config';

export default defineConfig({
  cacheDir: '../.runs/client/vite',
  test: {
    environment: 'jsdom',
    setupFiles: ['./tests/setup.ts'],
    include: ['src/**/*.test.{ts,tsx}'],
    restoreMocks: true,
    coverage: {
      provider: 'v8',
      include: ['src/**/*.{ts,tsx}'],
      exclude: ['src/**/*.test.{ts,tsx}', 'src/**/*.d.ts', 'src/main.tsx'],
      reportsDirectory: '../.runs/client/coverage',
      reporter: ['text', 'json-summary', 'html'],
    },
  },
});
