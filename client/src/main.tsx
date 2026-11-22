import '@fight/react/styles.css';
import '../styles/app.scss';

import { createRoot } from 'react-dom/client';

import { takeActivation } from '@fight/react/account';
import { takeReset } from '@fight/react/account';
import { browserThemeEnvironment, ThemePreferenceStore } from '@fight/react/controls';

import { ShellApplication } from '@/ShellApplication';

const reset = takeReset(window);
window.addEventListener('pagehide', () => reset.clear(), { once: true });
window.addEventListener('fight:reset-retired', () => reset.clear());
const activation = takeActivation(window);
window.addEventListener('pagehide', () => activation.clear(), { once: true });
window.addEventListener('fight:activation-retired', () => activation.clear());
const theme = new ThemePreferenceStore(browserThemeEnvironment());

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
      theme={theme}
      activation={activation}
      reset={reset}
    />
  );
} else {
  document.title = 'Application unavailable — Fight Agent OS';
  document.body.textContent = 'Application unavailable. Reload to try again.';
}
