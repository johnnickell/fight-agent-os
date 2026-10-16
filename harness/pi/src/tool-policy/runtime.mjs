/** Stable Pi 1.x from the verified API baseline; future releases still require every boundary capability */
const boundaryHooks = ['session_start', 'tool_call', 'user_bash', 'session_before_switch',
  'session_before_fork', 'session_before_tree', 'session_shutdown'];
const boundaryMethods = ['on', 'registerTool', 'getAllTools', 'getActiveTools', 'setActiveTools', 'getFlag'];

/** Require a compatible stable version, the supported platform and successful hook registration */
export function supportsRuntime(runtime) {
  if (runtime === null || typeof runtime !== 'object' || typeof runtime.version !== 'string' ||
      runtime.platform !== 'darwin' || runtime.hooks !== true) return false;
  const version = /^1\.(0|[1-9]\d*)\.(0|[1-9]\d*)$/.exec(runtime.version);
  return version !== null && version[0] === runtime.version && Number.isSafeInteger(Number(version[1])) && Number(version[1]) >= 2 &&
    Number.isSafeInteger(Number(version[2]));
}

/** Track actual registrations; API presence alone does not establish installed hooks */
export function trackRuntimeHooks(pi, { screening = false } = {}) {
  const required = [...boundaryHooks, ...(screening ? ['before_agent_start', 'input'] : [])];
  const methods = [...boundaryMethods, ...(screening ? ['getSettings', 'appendEntry'] : [])];
  const registered = new Map();
  return {
    on(event, handler) {
      const unsubscribe = pi.on(event, handler);
      // Both qualified Pi releases return an unsubscribe function for each registration.
      registered.set(event, typeof unsubscribe === 'function');
      if (typeof unsubscribe !== 'function') return unsubscribe;
      return () => { registered.set(event, false); unsubscribe(); };
    },
    available() {
      return methods.every(name => typeof pi[name] === 'function') &&
        required.every(name => registered.get(name) === true);
    },
  };
}
