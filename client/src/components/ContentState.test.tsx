import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import { ContentState } from './ContentState';
import { ContentPanel } from './ContentPanel';
import { Notice } from './Notice';

describe('Content presentation', () => {
  it('renders explicit loading, empty and success outcomes without a color-only meaning', () => {
    const { rerender } = render(
      <ContentState
        kind="loading"
        title="Loading"
        message="Waiting for examples…"
      />,
    );
    expect(screen.getByRole('status')).toHaveTextContent(
      'LoadingWaiting for examples…',
    );
    expect(screen.queryByRole('button')).not.toBeInTheDocument();
    rerender(
      <ContentState
        kind="empty"
        title="No examples"
        message="Nothing to display."
      />,
    );
    expect(screen.getByText('No examples')).toBeVisible();
    expect(screen.queryByRole('status')).not.toBeInTheDocument();
    rerender(
      <ContentState
        kind="success"
        title="Complete"
        message="Examples are ready."
      />,
    );
    expect(screen.getByRole('status')).toHaveTextContent(
      'CompleteExamples are ready.',
    );
  });

  it('only retries through explicit activation, then renders the caller-owned recovery', async () => {
    const retry = vi.fn();
    const { rerender } = render(
      <ContentState
        kind="error"
        title="Unavailable"
        message="Try again later."
        onRetry={retry}
      />,
    );
    expect(screen.getByRole('status')).toHaveTextContent(
      'UnavailableTry again later.',
    );
    expect(retry).not.toHaveBeenCalled();
    const user = userEvent.setup();
    await user.tab();
    expect(screen.getByRole('button', { name: 'Try again' })).toHaveFocus();
    await user.keyboard('{Enter}');
    expect(retry).toHaveBeenCalledOnce();
    rerender(
      <ContentState kind="loading" title="Loading" message="Waiting…" />,
    );
    expect(screen.queryByRole('button')).not.toBeInTheDocument();
    expect(screen.getByRole('status')).toHaveTextContent('Waiting…');
  });

  it('does not invent recovery when none is supplied', () => {
    render(
      <ContentState
        kind="error"
        title="Unavailable"
        message="Contact the example owner."
      />,
    );
    expect(screen.queryByRole('button')).not.toBeInTheDocument();
  });

  it('distinguishes urgent alerts from static notices', () => {
    const { rerender } = render(
      <Notice tone="danger" title="Error" announce="assertive">
        Example failed.
      </Notice>,
    );
    expect(screen.getByRole('alert')).toHaveTextContent('ErrorExample failed.');
    expect(screen.getByRole('alert')).toHaveAttribute('aria-atomic', 'true');
    rerender(
      <Notice tone="warning" title="Warning" announce="off">
        Review this example.
      </Notice>,
    );
    expect(screen.queryByRole('alert')).not.toBeInTheDocument();
    expect(screen.queryByRole('status')).not.toBeInTheDocument();
    expect(screen.getByText('Review this example.')).toBeVisible();
  });

  it('labels independent panels and exposes optional header actions as native controls', async () => {
    const click = vi.fn();
    render(
      <>
        <ContentPanel
          title="First"
          actions={<button onClick={click}>Inspect</button>}
        >
          First content
        </ContentPanel>
        <ContentPanel title="Second">Second content</ContentPanel>
      </>,
    );
    expect(screen.getByRole('region', { name: 'First' })).toHaveTextContent(
      'First content',
    );
    expect(screen.getByRole('region', { name: 'Second' })).toHaveTextContent(
      'Second content',
    );
    await userEvent
      .setup()
      .click(screen.getByRole('button', { name: 'Inspect' }));
    expect(click).toHaveBeenCalledOnce();
  });
});
