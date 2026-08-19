import { useEffect, useState } from 'react';
import { fetchVehicleDetail, fetchVehicles } from '../api/operations';

interface VehicleRow {
  label: string;
  seo_health_avg: number;
  plan_coverage: number;
  published: number;
  missing_articles: number;
  orphans: number;
  unresolved_links?: number;
  high_overlaps: number;
}

export function VehiclesPage() {
  const [vehicles, setVehicles] = useState<VehicleRow[]>([]);
  const [selected, setSelected] = useState<string | null>(null);
  const [detail, setDetail] = useState<Record<string, unknown> | null>(null);

  useEffect(() => {
    fetchVehicles().then((data) => setVehicles(data as VehicleRow[]));
  }, []);

  useEffect(() => {
    if (!selected) {
      setDetail(null);
      return;
    }
    fetchVehicleDetail(selected).then((data) => setDetail(data as Record<string, unknown>));
  }, [selected]);

  return (
    <div className="revit-publisher-panel revit-publisher-dark">
      <h1>Vehicle Content Health</h1>
      <div className="revit-publisher-grid">
        {vehicles.map((v) => (
          <button
            key={v.label}
            type="button"
            className="revit-publisher-card revit-publisher-metric revit-publisher-card--clickable"
            onClick={() => setSelected(v.label)}
          >
            <strong>{v.label}</strong>
            <div>SEO Health {v.seo_health_avg}</div>
            <div className="revit-publisher-muted">Coverage {v.plan_coverage}% · Published {v.published} · Missing {v.missing_articles}</div>
          </button>
        ))}
      </div>
      {detail && (
        <div className="revit-publisher-card">
          <h2>{String(detail.vehicle)}</h2>
          <ul className="revit-publisher-list">
            <li>Articles: {String(detail.articles)} · Published: {String(detail.published)} · Draft: {String(detail.draft)}</li>
            <li>Clusters: {String(detail.clusters)} · Plan Coverage: {String(detail.plan_coverage)}%</li>
            <li>SEO Health Avg: {String(detail.seo_health_avg)}</li>
          </ul>
          <h3>Needs Attention</h3>
          <pre>{JSON.stringify(detail.needs_attention, null, 2)}</pre>
        </div>
      )}
    </div>
  );
}
