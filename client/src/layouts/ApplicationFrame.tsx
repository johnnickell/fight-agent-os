import { useCallback, useEffect, useLayoutEffect, useRef, useState } from 'react';
import Offcanvas from 'react-bootstrap/Offcanvas';

import { Button } from '@/components/Button';
import { evaluateAccess } from '@/features/authority/evaluateAccess';
import { useAuthority } from '@/features/authority/useAuthority';
import { ThemeSelector } from '@/features/theme/ThemeSelector';
import { applicationRoutes } from '@/routes/applicationRoutes';

import type { AuthorityCache } from '@/features/authority/AuthorityCache';
import type { ThemePreferenceStore } from '@/features/theme/ThemePreference';
import type { KeyboardEvent as ReactKeyboardEvent, ReactNode, RefObject } from 'react';

function useFrameNavigation(frame: RefObject<HTMLDivElement | null>) {
  const [{ narrow, expanded }, setNavigation] = useState(() => {
    const narrow = window.matchMedia?.('(max-width: 860px)').matches ?? true;
    return { narrow, expanded: !narrow };
  });
  const transferFocus = useRef(false);
  useEffect(() => {
    const media = window.matchMedia?.('(max-width: 860px)');
    if (!media) return;
    const update = () => {
      if (media.matches === narrow) return;
      const active = document.activeElement;
      // Capture ownership before responsive navigation is removed. Workspace focus
      // stays in place; only disappearing navigation needs an available successor.
      transferFocus.current =
        active instanceof HTMLElement &&
        Boolean(frame.current?.contains(active)) &&
        Boolean(active.closest('.application-rail, .application-drawer, .application-open'));
      setNavigation({ narrow: media.matches, expanded: !media.matches });
    };
    media.addEventListener('change', update);
    return () => media.removeEventListener('change', update);
  }, [frame, narrow]);
  useLayoutEffect(() => {
    if (!transferFocus.current) return;
    transferFocus.current = false;
    frame.current
      ?.querySelector<HTMLButtonElement>(
        narrow ? '[aria-label="Open navigation"]' : '[aria-label="Close navigation"]'
      )
      ?.focus();
  }, [frame, narrow]);
  const setExpanded = useCallback(
    (expanded: boolean) => setNavigation((state) => ({ ...state, expanded })),
    []
  );
  return { narrow, expanded, setExpanded };
}

/** Presents only current allowed destinations; the shared evaluator never substitutes for server checks */
type FrameProps = {
  cache: AuthorityCache;
  pathname: string;
  title: string;
  theme?: ThemePreferenceStore | undefined;
  onLogout: () => void;
  children: ReactNode;
};

export function ApplicationFrame(props: FrameProps) {
  return <FrameContent key={props.pathname} {...props} />;
}

function FrameContent({ cache, pathname, title, theme, onLogout, children }: FrameProps) {
  const state = useAuthority(cache);
  const opener = useRef<HTMLButtonElement>(null);
  const frame = useRef<HTMLDivElement>(null);
  const { narrow, expanded, setExpanded } = useFrameNavigation(frame);
  const returnFocus = useRef(false);
  const enterFocus = useRef(false);
  useEffect(() => {
    const escape = (event: KeyboardEvent) => {
      if (event.key === 'Escape' && expanded && !narrow) {
        returnFocus.current = true;
        setExpanded(false);
      }
    };
    window.addEventListener('keydown', escape);
    return () => window.removeEventListener('keydown', escape);
  }, [expanded, narrow, setExpanded]);
  useEffect(() => {
    if (expanded && !narrow && enterFocus.current) {
      frame.current?.querySelector<HTMLButtonElement>('[aria-label="Close navigation"]')?.focus();
      enterFocus.current = false;
    }
    if (!expanded && returnFocus.current) {
      opener.current?.focus();
      returnFocus.current = false;
    }
  }, [expanded, narrow]);
  const close = () => {
    returnFocus.current = true;
    setExpanded(false);
  };
  if (state.status !== 'authenticated') return null;
  const rail = (
    <>
      <div className="application-rail-heading">
        <a href="/app">
          FIGHT <span>AGENT OS</span>
        </a>
        {expanded && (
          <Button onClick={close} aria-label="Close navigation">
            Close
          </Button>
        )}
      </div>
      <nav aria-label="Primary">
        {(['Workspace', 'Security', 'Administration'] as const).map((group) => {
          const routes = applicationRoutes.filter(
            (route) =>
              route.group === group && evaluateAccess(state, route.requirements) === 'allowed'
          );
          return (
            routes.length > 0 && (
              <details key={group} open>
                <summary>{group}</summary>
                <ul>
                  {routes.map((route) => (
                    <li key={route.pathname}>
                      <a
                        href={route.pathname}
                        aria-current={pathname === route.pathname ? 'page' : undefined}
                      >
                        {route.title}
                      </a>
                    </li>
                  ))}
                </ul>
              </details>
            )
          );
        })}
      </nav>
    </>
  );
  return (
    <div ref={frame} className={`application-frame${expanded && !narrow ? ' rail-expanded' : ''}`}>
      <a className="skip-link" href="#main-content" inert={narrow && expanded}>
        Skip to content
      </a>
      {!narrow && expanded && (
        <aside className="application-rail" aria-label="Application navigation">
          {rail}
        </aside>
      )}
      <div className="application-workspace" inert={narrow && expanded}>
        {!expanded && (
          <button
            ref={opener}
            className="application-open"
            onClick={() => {
              enterFocus.current = !narrow;
              setExpanded(true);
            }}
            aria-label="Open navigation"
            aria-expanded={false}
          >
            Open
          </button>
        )}
        <header className="application-utility">
          <a href="/app">Fight Agent OS</a>
          <div className="application-user">
            <span>{state.principal.email}</span>
            <a href="/app/account">Account / Security</a>
            <Button onClick={onLogout}>Sign out</Button>
          </div>
          {theme && <ThemeSelector theme={theme} />}
        </header>
        <main id="main-content" tabIndex={-1} className="application-content" aria-label={title}>
          {children}
        </main>
        <footer className="application-footer">
          Client presentation only. Server authorization remains authoritative.
        </footer>
      </div>
      {narrow && (
        <Offcanvas
          container={() => frame.current ?? document.body}
          show={expanded}
          onHide={close}
          placement="start"
          className="application-drawer"
          aria-label="Application navigation"
          onKeyDown={(event: ReactKeyboardEvent<HTMLDivElement>) => {
            if (event.key !== 'Tab' || !expanded) return;
            // Offcanvas enforces document focus, but native Tab can otherwise leave for
            // browser chrome at the boundary. Keep this modal's sequential focus cyclic.
            const controls = [
              ...event.currentTarget.querySelectorAll<HTMLElement>(
                'a[href], button:not([disabled]), summary, [tabindex]:not([tabindex="-1"])'
              )
            ].filter((element) => element.getClientRects().length > 0);
            const first = controls[0];
            const last = controls.at(-1);
            if (
              event.shiftKey &&
              (document.activeElement === first || document.activeElement === event.currentTarget)
            ) {
              event.preventDefault();
              last?.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
              event.preventDefault();
              first?.focus();
            }
          }}
          onExited={() => opener.current?.focus()}
        >
          {rail}
        </Offcanvas>
      )}
    </div>
  );
}
