import SwaggerUI from 'swagger-ui-dist/swagger-ui-bundle.js';

import { supportsSwaggerOperation } from '@/features/swagger/SwaggerDocument';

import type { AuthorityScope } from '@/features/authority/AuthorityCache';
import type { SwaggerDocument } from '@/features/swagger/SwaggerDocument';
import type { SwaggerService } from '@/features/swagger/SwaggerService';
import type { SwaggerRequest, SwaggerSystem } from '@/features/swagger/SwaggerSystem';

type Action = (...arguments_: unknown[]) => unknown;

/** Keeps credentials below Swagger state and removes child subscribers before clearing its store. */
export function mountSwagger(
  host: HTMLElement,
  document: SwaggerDocument,
  service: SwaggerService,
  scope: AuthorityScope
): () => void {
  let active = scope.isCurrent();
  let contract: SwaggerDocument | null = document;
  let unmountChildren: (() => Promise<void>) | null = null;
  const current = () => active && scope.isCurrent();
  const plugin = (system: SwaggerSystem) => ({
    wrapComponents: {
      HighlightCode: (Original: unknown) => (props: Readonly<Record<string, unknown>>) =>
        system.React.createElement(Original, { ...props, canCopy: false, downloadable: false }),
      TextArea: (Original: unknown) => (props: Readonly<Record<string, unknown>>) =>
        system.React.createElement(Original, { ...props, 'aria-label': 'Request JSON body' }),
      App: (Original: unknown) =>
        class extends system.React.Component {
          constructor(props: Readonly<Record<string, unknown>>) {
            super(props);
            this.state = { retired: !current() };
            unmountChildren = () =>
              new Promise((resolve) => this.setState({ retired: true }, resolve));
          }
          render() {
            return this.state.retired ? null : system.React.createElement(Original, this.props);
          }
        }
    },
    components: {
      // Supported custom layout embeds the existing operations/models inside the application's sole
      // main landmark. No nested vendor <main>, duplicate h1, unlabelled server picker or raw error pane.
      EmbeddedDocumentationLayout: () =>
        system.React.createElement(
          'div',
          { className: 'swagger-ui' },
          system.React.createElement(system.getComponent('SvgAssets'), null),
          system.React.createElement(
            'div',
            { className: 'info' },
            system.React.createElement(
              'h2',
              { className: 'title' },
              contract?.info.title ?? 'Private API'
            ),
            system.React.createElement(
              'p',
              null,
              `OpenAPI 3.0.3 · ${contract?.info.version ?? ''} · Same-origin API only`
            )
          ),
          system.React.createElement(system.getComponent('FilterContainer', true), null),
          system.React.createElement(system.getComponent('operations', true), null),
          system.React.createElement(system.getComponent('Models', true), null)
        ),
      // Plain text prevents description images, remote links and arbitrary HTML from becoming resources.
      Markdown: ({ source }: { source?: unknown }) =>
        typeof source === 'string' ? system.React.createElement('p', null, source) : null,
      curl: () =>
        system.React.createElement(
          'p',
          null,
          'Credentials, headers and request bodies are excluded from request snippets and clipboard exports.'
        ),
      CopyToClipboardBtn: () => null,
      authorizeBtn: () => null,
      authorizeOperationBtn: () => null,
      authorizationPopup: () => null,
      auths: () => null,
      // An execution response is codec-selected safe JSON; no browser/download/export control is mounted.
      downloadJson: () => null
    },
    fn: {
      // requestInterceptor results are retained by upstream Redux. Credentials must never enter that hook.
      fetch: (request: SwaggerRequest) =>
        current() && contract !== null
          ? service.execute(request, contract, scope)
          : Promise.resolve({
              url: '',
              status: 0,
              statusText: 'View retired',
              headers: {},
              text: 'View retired; no retry was sent.',
              data: null
            })
    },
    statePlugins: {
      auth: {
        wrapActions: {
          authorize: () => () => undefined,
          authorizeWithPersistOption: () => () => undefined
        }
      },
      spec: {
        selectors: {
          allowTryItOutFor: (_state: unknown, path: string, method: string) =>
            current() && supportsSwaggerOperation(path, method)
        },
        wrapActions: {
          setRequest: (original: Action) => (path: unknown, method: unknown) =>
            current() ? original(path, method, { url: '', method, headers: {} }) : undefined,
          setMutatedRequest: () => () => undefined,
          setResponse:
            (original: Action) =>
            (...args: unknown[]) =>
              current() ? original(...args) : undefined
        }
      }
    }
  });
  const instance = SwaggerUI({
    domNode: host,
    spec: displayProjection(document),
    plugins: [plugin],
    layout: 'EmbeddedDocumentationLayout',
    docExpansion: 'none',
    defaultModelsExpandDepth: 0,
    defaultModelExpandDepth: 0,
    filter: true,
    deepLinking: false,
    queryConfigEnabled: false,
    validatorUrl: null,
    persistAuthorization: false,
    showMutatedRequest: false,
    requestSnippetsEnabled: false,
    tryItOutEnabled: false,
    supportedSubmitMethods: ['get', 'post'],
    syntaxHighlight: { activated: false }
  });
  let retired = false;
  const retire = () => {
    if (retired) return;
    retired = true;
    active = false;
    contract = null;
    host.hidden = true;
    instance.getConfigs().spec = {};
    const clear = () => {
      instance.getStore().replaceReducer(() => instance.Im.Map());
      host.replaceChildren();
    };
    // Clearing Redux while children still subscribe causes upstream selector TypeErrors. Use its React
    // component lifecycle first, then erase every slice and reject late upstream dispatches. No vendor patch.
    if (unmountChildren) void unmountChildren().then(clear);
    else clear();
  };
  scope.signal.addEventListener('abort', retire, { once: true });
  if (!current()) retire();
  return () => {
    scope.signal.removeEventListener('abort', retire);
    retire();
  };
}

function displayProjection(value: unknown): unknown {
  if (Array.isArray(value)) return value.map(displayProjection);
  if (value !== null && typeof value === 'object')
    return Object.fromEntries(
      Object.entries(value)
        .filter(
          ([key]) =>
            !['externalDocs', 'contact', 'license', 'termsOfService', 'x-logo'].includes(key)
        )
        .map(([key, child]) => [key, displayProjection(child)])
    );
  return value;
}
