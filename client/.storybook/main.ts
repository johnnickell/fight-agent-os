import { mergeConfig } from 'vite';

import { applicationResolve } from '../vite.resolve.ts';

import type { StorybookConfig } from '@storybook/react-vite';

const config: StorybookConfig = {
  stories: ['../stories/**/*.stories.tsx'],
  addons: ['@storybook/addon-a11y'],
  framework: { name: '@storybook/react-vite', options: {} },
  core: { disableTelemetry: true, enableCrashReports: false },
  viteFinal(config, { configType }) {
    return mergeConfig(config, {
      cacheDir: '../.runs/client/storybook-vite',
      resolve: applicationResolve,
      // Only Storybook's middleware server uses bin/client's published 16006 port.
      // Browser tests supply their own server, not this Docker port mapping.
      ...(configType === 'DEVELOPMENT' && config.server?.middlewareMode
        ? { server: { hmr: { clientPort: 16006 } } }
        : {})
    });
  }
};

export default config;
