import {
  captureActivation,
  discardActivationFragment,
  takeActivation
} from '@/features/auth/ActivationCredential';
import { clearLoginLocation } from '@/features/auth/LoginLocation';
import { captureReset, discardResetFragment, takeReset } from '@/features/auth/ResetCredential';
import { browserThemeEnvironment, prepaintTheme } from '@/features/theme/ThemePreference';

if (!captureReset(window, window.location, window.history))
  window.location.replace('/app/reset-password/complete');
window.addEventListener('pagehide', () => takeReset(window).clear(), { once: true });
if (!clearLoginLocation(window.location, window.history)) window.location.replace('/app/login');
if (
  !captureActivation(window, window.location, window.history) &&
  (window.location.hash !== '' || window.location.search !== '')
)
  window.location.replace('/app/activate');
window.addEventListener('pagehide', () => takeActivation(window).clear(), { once: true });
window.addEventListener('hashchange', () => {
  if (discardResetFragment(window, window.location, window.history))
    window.dispatchEvent(new Event('fight:reset-retired'));
  if (window.location.pathname === '/app/reset-password/complete' && window.location.hash !== '')
    window.location.replace('/app/reset-password/complete');
  if (!clearLoginLocation(window.location, window.history)) window.location.replace('/app/login');
  if (discardActivationFragment(window, window.location, window.history))
    window.dispatchEvent(new Event('fight:activation-retired'));
  if (window.location.pathname === '/app/activate' && window.location.hash !== '')
    window.location.replace('/app/activate');
});
prepaintTheme(browserThemeEnvironment());
