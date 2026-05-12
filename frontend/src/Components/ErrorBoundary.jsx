import React from "react";

export class ErrorBoundary extends React.Component {
  constructor(props) {
    super(props);
    this.state = { hasError: false };
  }

  static getDerivedStateFromError() {
    return { hasError: true };
  }

  componentDidCatch(error) {
    // Keep this as console fallback; add Sentry/etc in production monitoring phase.
    console.error("Unhandled UI error:", error);
  }

  render() {
    if (this.state.hasError) {
      return (
        <div className="flex min-h-screen items-center justify-center bg-slate-950 px-4 text-white">
          <div className="max-w-md rounded-xl border border-white/10 bg-white/5 p-6 text-center">
            <h1 className="text-xl font-bold">Something went wrong</h1>
            <p className="mt-2 text-sm text-slate-300">
              Please refresh the page. If the issue persists, sign in again.
            </p>
          </div>
        </div>
      );
    }

    return this.props.children;
  }
}

