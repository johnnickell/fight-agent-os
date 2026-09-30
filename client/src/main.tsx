import '../styles/app.scss';

import { createRoot } from 'react-dom/client';

import { ShellApplication } from '@/ShellApplication';

const element = document.getElementById('app');
if (element) {
  createRoot(element, {
    // Never forward component exceptions/props to diagnostics or browser logs.
    onCaughtError: () => {},
    onUncaughtError: () => {
      document.title = 'Application unavailable — Fight Agent OS';
      element.textContent = 'Application unavailable. Reload to try again.';
    }
  }).render(
    <ShellApplication
      configuration={document.getElementById('runtime-config')?.textContent ?? null}
      pathname={window.location.pathname}
    />
  );
} else {
  document.title = 'Application unavailable — Fight Agent OS';
  document.body.textContent = 'Application unavailable. Reload to try again.';
}
