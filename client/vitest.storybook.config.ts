import { storybookTest } from '@storybook/addon-vitest/vitest-plugin';
import { playwright } from '@vitest/browser-playwright';
import { defineConfig } from 'vitest/config';

import { applicationResolve } from './vite.resolve.ts';

export default defineConfig({
  cacheDir: '../.runs/cache/client/storybook-test-vite',
  resolve: applicationResolve,
  // Prebundle before browser tests, including when source advances over a warm cache.
  // Modal joins the page graph through session revocation; late discovery reloads React mid-test.
  optimizeDeps: { include: ['react-bootstrap/Dropdown', 'react-bootstrap/Modal'] },
  plugins: [storybookTest({ configDir: './.storybook' })],
  test: {
    name: 'storybook',
    browser: {
      enabled: true,
      headless: true,
      screenshotDirectory: '../.runs/build/reports/client/storybook-test-screenshots',
      provider: playwright(),
      instances: [{ browser: 'chromium' }]
    }
  }
});
