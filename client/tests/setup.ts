import * as matchers from '@testing-library/jest-dom/matchers';
import { cleanup } from '@testing-library/react';
import { afterEach, expect } from 'vitest';

import type { TestingLibraryMatchers } from '@testing-library/jest-dom/matchers';

// The Vitest adapter's asymmetric matcher augmentation conflicts with Vitest's
// browser types. Extend only the assertions used by this jsdom suite.
declare module 'vitest' {
  // eslint-disable-next-line @typescript-eslint/no-explicit-any, @typescript-eslint/no-empty-object-type
  interface Assertion<T = any> extends TestingLibraryMatchers<void, T> {}
}
// jsdom has no media implementation; browser catalog/smoke separately exercises real breakpoints.
Object.defineProperty(window, 'matchMedia', {
  writable: true,
  value: (media: string): MediaQueryList => ({
    media,
    matches: false,
    onchange: null,
    addListener: () => {},
    removeListener: () => {},
    addEventListener: () => {},
    removeEventListener: () => {},
    dispatchEvent: () => true
  })
});
expect.extend(matchers);
afterEach(cleanup);
