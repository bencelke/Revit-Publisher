export function StatCard({ label, value, hint }: { label: string; value: string | number; hint?: string }) {
  return (
    <div className="revit-stat-card">
      <span className="revit-stat-card__label">{label}</span>
      <span className="revit-stat-card__value">{value}</span>
      {hint && <span className="revit-stat-card__hint">{hint}</span>}
    </div>
  );
}
