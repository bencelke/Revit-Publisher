import { Component, ErrorInfo, ReactNode } from 'react';
import { PrimaryButton } from './PrimaryButton';

interface Props {
  children: ReactNode;
}

interface State {
  hasError: boolean;
  message: string;
}

export class ErrorBoundary extends Component<Props, State> {
  state: State = { hasError: false, message: '' };

  static getDerivedStateFromError(error: Error): State {
    return { hasError: true, message: error.message };
  }

  componentDidCatch(error: Error, info: ErrorInfo): void {
    if (typeof window !== 'undefined' && (window as unknown as { revitPublisherAdmin?: { debug?: boolean } }).revitPublisherAdmin?.debug) {
      // eslint-disable-next-line no-console
      console.error('[RevIt Publisher]', error, info.componentStack);
    }
  }

  render() {
    if (this.state.hasError) {
      return (
        <div className="revit-publisher-panel revit-publisher-dark">
          <div className="revit-publisher-card revit-error-boundary">
            <h2>Something went wrong loading this section.</h2>
            <p className="revit-publisher-muted">An unexpected error occurred. You can retry or return to the dashboard.</p>
            <PrimaryButton onClick={() => this.setState({ hasError: false, message: '' })}>Retry</PrimaryButton>
          </div>
        </div>
      );
    }
    return this.props.children;
  }
}

export function AppShell({ children }: { children: ReactNode }) {
  return <ErrorBoundary>{children}</ErrorBoundary>;
}
