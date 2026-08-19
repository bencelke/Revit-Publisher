import { PrimaryButton } from './PrimaryButton';

export function EmptyState({
  title,
  description,
  actionLabel,
  onAction,
  href,
}: {
  title: string;
  description: string;
  actionLabel?: string;
  onAction?: () => void;
  href?: string;
}) {
  return (
    <div className="revit-empty-state">
      <h3>{title}</h3>
      <p className="revit-publisher-muted">{description}</p>
      {actionLabel && (href ? <PrimaryButton href={href}>{actionLabel}</PrimaryButton> : (
        <PrimaryButton onClick={onAction}>{actionLabel}</PrimaryButton>
      ))}
    </div>
  );
}

export function WarningBanner({ children }: { children: React.ReactNode }) {
  return <div className="revit-warning-banner">{children}</div>;
}

export function LoadingBlock({ label = 'Loading…' }: { label?: string }) {
  return <div className="revit-loading-block" aria-busy="true">{label}</div>;
}

export function SectionError({ message, onRetry }: { message: string; onRetry?: () => void }) {
  return (
    <div className="revit-publisher-card revit-section-error">
      <p>{message}</p>
      {onRetry && <PrimaryButton onClick={onRetry}>Retry</PrimaryButton>}
    </div>
  );
}

export function FixtureBanner() {
  return (
    <div className="revit-fixture-banner" role="status">
      <strong>TEST DATA</strong>
      <span>Google Search Console fixture — not production analytics</span>
    </div>
  );
}

export function VehicleBadge({ label }: { label: string }) {
  return <span className="revit-vehicle-badge">{label}</span>;
}
