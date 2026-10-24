import type { RuntimeConfiguration } from '@/runtimeConfiguration';

/** Emits only a closed vocabulary, never caller objects, values or exception prose. */
export function safeDiagnostics(configuration: RuntimeConfiguration) {
  return (
    event: 'documentation-ready' | 'documentation-unavailable' | 'documentation-operation'
  ) => {
    if (
      configuration.appDebug &&
      ['documentation-ready', 'documentation-unavailable', 'documentation-operation'].includes(
        event
      )
    )
      console.debug(`[Fight] ${event}`);
  };
}
