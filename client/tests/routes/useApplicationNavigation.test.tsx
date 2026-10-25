import { fireEvent, render, screen } from '@testing-library/react';
import { afterEach, expect, it, vi } from 'vitest';

import { useApplicationNavigation } from '@/routes/useApplicationNavigation';

function Example() {
  const { pathname } = useApplicationNavigation('/app/swagger');
  return (
    <>
      <output aria-label="Current route">{pathname}</output>
      <button type="button">Operation summary</button>
      <a href="/app/dashboard">Dashboard</a>
      <a href="#main-content">Skip to content</a>
      <a href="/app/account" data-reload-application>
        Reload account
      </a>
    </>
  );
}

afterEach(() => {
  window.history.replaceState(null, '', '/');
  vi.restoreAllMocks();
});

it('leaves href-less embedded controls to their owner and still intercepts registered native links', () => {
  window.history.replaceState(null, '', '/app/swagger');
  render(<Example />);
  const push = vi.spyOn(window.history, 'pushState');
  // Upstream Swagger embeds an href-less anchor inside its summary button.
  const anchor = document.createElement('a');
  anchor.textContent = 'Operation details';
  screen.getByRole('button', { name: 'Operation summary' }).append(anchor);
  fireEvent.click(anchor);
  expect(screen.getByLabelText('Current route')).toHaveTextContent('/app/swagger');
  expect(push).not.toHaveBeenCalled();
  fireEvent.click(screen.getByRole('link', { name: 'Dashboard' }));
  expect(screen.getByLabelText('Current route')).toHaveTextContent('/app/dashboard');
  expect(push).toHaveBeenCalledExactlyOnceWith(null, '', '/app/dashboard');
});

it('preserves modifier, fragment and explicit document-reload navigation', () => {
  render(<Example />);
  const push = vi.spyOn(window.history, 'pushState');
  const prevented: boolean[] = [];
  const observe = (event: MouseEvent) => {
    prevented.push(event.defaultPrevented);
    event.preventDefault(); // Keep native document navigation outside this jsdom test.
  };
  document.addEventListener('click', observe);
  try {
    fireEvent.click(screen.getByRole('link', { name: 'Dashboard' }), { ctrlKey: true });
    fireEvent.click(screen.getByRole('link', { name: 'Skip to content' }));
    fireEvent.click(screen.getByRole('link', { name: 'Reload account' }));
    expect(prevented).toEqual([false, false, false]);
    expect(push).not.toHaveBeenCalled();
  } finally {
    document.removeEventListener('click', observe);
  }
});
