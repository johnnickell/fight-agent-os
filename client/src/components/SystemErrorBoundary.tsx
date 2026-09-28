import { Component } from 'react';
import type { ReactNode } from 'react';
import { SystemErrorPage } from '../pages/SystemErrorPage';

export class SystemErrorBoundary extends Component<
  { children: ReactNode },
  { failed: boolean }
> {
  override state = { failed: false };

  static getDerivedStateFromError() {
    return { failed: true };
  }

  override render() {
    return this.state.failed ? <SystemErrorPage /> : this.props.children;
  }
}
