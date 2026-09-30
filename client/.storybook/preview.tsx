import '../styles/app.scss';
import './preview.scss';

import type { Preview } from '@storybook/react-vite';

const preview: Preview = {
  parameters: {
    layout: 'fullscreen',
    a11y: { test: 'error' },
    viewport: {
      options: {
        narrow: {
          name: 'Narrow (320px)',
          styles: { width: '320px', height: '900px' }
        },
        wide: {
          name: 'Wide (1280px)',
          styles: { width: '1280px', height: '900px' }
        }
      }
    }
  },
  decorators: [
    (Story, { parameters }) => (
      <main
        className="catalog-preview"
        data-bs-theme={parameters.mode === 'dark' ? 'dark' : 'light'}
      >
        <h1>Neutral component foundation</h1>
        <p>Invented local examples. Not final product design or authorization.</p>
        <Story />
      </main>
    )
  ]
};

export default preview;
