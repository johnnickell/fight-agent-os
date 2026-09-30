import { Component } from 'react';

import { SystemErrorPage } from '@/pages/SystemErrorPage';

import type { ReactNode } from 'react';

export class SystemErrorBoundary extends Component<{ children: ReactNode }, { failed: boolean }> {
  override state = { failed: false };

  static getDerivedStateFromError() {
    return { failed: true };
  }

  override render() {
    return this.state.failed ? <SystemErrorPage /> : this.props.children;
  }
}
