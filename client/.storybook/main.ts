import type { StorybookConfig } from '@storybook/react-vite';

const config: StorybookConfig = {
  stories: ['../stories/**/*.stories.tsx'],
  addons: ['@storybook/addon-a11y'],
  framework: { name: '@storybook/react-vite', options: {} },
  core: { disableTelemetry: true, enableCrashReports: false },
  async viteFinal(config) {
    config.cacheDir = '../.runs/client/storybook-vite';
    return config;
  },
};

export default config;
