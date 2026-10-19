import {
  captureActivation,
  discardActivationFragment,
  takeActivation
} from '@/features/auth/ActivationCredential';
import { browserThemeEnvironment, prepaintTheme } from '@/features/theme/ThemePreference';

if (
  !captureActivation(window, window.location, window.history) &&
  (window.location.hash !== '' || window.location.search !== '')
)
  window.location.replace('/app/activate');
window.addEventListener('pagehide', () => takeActivation(window).clear(), { once: true });
window.addEventListener('hashchange', () => {
  if (discardActivationFragment(window, window.location, window.history))
    window.dispatchEvent(new Event('fight:activation-retired'));
  if (window.location.pathname === '/app/activate' && window.location.hash !== '')
    window.location.replace('/app/activate');
});
prepaintTheme(browserThemeEnvironment());
