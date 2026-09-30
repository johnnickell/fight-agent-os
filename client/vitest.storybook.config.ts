import { defineConfig } from 'vitest/config';
import { storybookTest } from '@storybook/addon-vitest/vitest-plugin';
import { playwright } from '@vitest/browser-playwright';

export default defineConfig({
  cacheDir: '../.runs/client/storybook-test-vite',
  plugins: [storybookTest({ configDir: './.storybook' })],
  test: {
    name: 'storybook',
    browser: {
      enabled: true,
      headless: true,
      screenshotDirectory: '../.runs/client/storybook-test-screenshots',
      provider: playwright(),
      instances: [{ browser: 'chromium' }],
    },
  },
});
