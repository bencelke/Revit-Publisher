import { useCallback, useEffect, useState } from 'react';
import { fetchVehicleDetail, fetchVehicles } from '../api/operations';
import {
  createVehicleHubDraft,
  fetchHubCreatePreview,
  fetchVehicleHubs,
} from '../api/vehicle-hubs';
import { HubCreatePreview, VehicleHubRecord, VehicleRowWithHub } from '../types/public-seo';

function formatYears(start?: string, end?: string): string {
  if (start && end) return `${start}–${end}`;
  if (start) return `${start}+`;
  if (end) return `–${end}`;
  return '—';
}

function buildPreviewFromDetail(
  label: string,
  detail: Record<string, unknown>,
): HubCreatePreview {
  const identity = (detail.identity ?? {}) as Record<string, unknown>;
  return {
    vehicle_label: label,
    vehicle_key: String(identity.vehicle_key ?? detail.vehicle_key ?? ''),
    years: formatYears(
      String(identity.start_year ?? detail.start_year ?? ''),
      String(identity.end_year ?? detail.end_year ?? ''),
    ),
    engines: Array.isArray(identity.engines)
      ? (identity.engines as string[])
      : Array.isArray(detail.engines)
        ? (detail.engines as string[])
        : [],
    published_articles: Number(detail.published ?? 0),
    clusters_count: Number(detail.clusters ?? detail.clusters_count ?? 0),
  };
}

export function VehiclesPage() {
  const [vehicles, setVehicles] = useState<VehicleRowWithHub[]>([]);
  const [hubs, setHubs] = useState<VehicleHubRecord[]>([]);
  const [selected, setSelected] = useState<string | null>(null);
  const [detail, setDetail] = useState<Record<string, unknown> | null>(null);
  const [preview, setPreview] = useState<HubCreatePreview | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [creating, setCreating] = useState(false);
  const [loading, setLoading] = useState(true);

  const loadVehicles = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const [vehicleRows, hubRows] = await Promise.all([
        fetchVehicles(),
        fetchVehicleHubs().catch(() => [] as VehicleHubRecord[]),
      ]);
      setHubs(hubRows);

      const hubByLabel = new Map<string, VehicleHubRecord>();
      hubRows.forEach((hub) => {
        if (hub.vehicle_label) {
          hubByLabel.set(hub.vehicle_label, hub);
        }
      });

      setVehicles(
        (vehicleRows as VehicleRowWithHub[]).map((row) => ({
          ...row,
          hub: hubByLabel.get(row.label) ?? null,
        })),
      );
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to load vehicles.');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    loadVehicles();
  }, [loadVehicles]);

  useEffect(() => {
    if (!selected) {
      setDetail(null);
      setPreview(null);
      return;
    }

    const vehicle = vehicles.find((v) => v.label === selected);
    if (vehicle?.hub) {
      setDetail(null);
      setPreview(null);
      return;
    }

    fetchVehicleDetail(selected)
      .then(async (data) => {
        const record = data as Record<string, unknown>;
        setDetail(record);
        try {
          setPreview(await fetchHubCreatePreview(selected));
        } catch {
          setPreview(buildPreviewFromDetail(selected, record));
        }
      })
      .catch((err: unknown) => {
        setError(err instanceof Error ? err.message : 'Failed to load vehicle detail.');
      });
  }, [selected, vehicles]);

  async function handleCreateHub(label: string) {
    setCreating(true);
    setError(null);
    try {
      const result = await createVehicleHubDraft(label);
      if (result.edit_url) {
        window.open(result.edit_url, '_blank');
      }
      setSelected(null);
      await loadVehicles();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to create hub draft.');
    } finally {
      setCreating(false);
    }
  }

  const selectedVehicle = vehicles.find((v) => v.label === selected);
  const selectedHub = selectedVehicle?.hub ?? null;
  const displayPreview = preview ?? (detail ? buildPreviewFromDetail(selected ?? '', detail) : null);

  return (
    <div className="revit-publisher-panel revit-publisher-dark">
      <h1>Vehicle Content Health</h1>
      <p className="revit-publisher-muted">Multi-vehicle health metrics and public hub management.</p>

      {error && (
        <div className="revit-publisher-result revit-publisher-result--error">
          <p>{error}</p>
        </div>
      )}

      {loading && <p className="revit-publisher-muted">Loading…</p>}

      <div className="revit-publisher-grid">
        {vehicles.map((v) => (
          <button
            key={v.label}
            type="button"
            className={`revit-publisher-card revit-publisher-metric revit-publisher-card--clickable${selected === v.label ? ' is-active' : ''}`}
            onClick={() => setSelected(v.label)}
          >
            <strong>{v.label}</strong>
            <div>SEO Health {v.seo_health_avg}</div>
            <div className="revit-publisher-muted">
              Coverage {v.plan_coverage}% · Published {v.published} · Missing {v.missing_articles}
            </div>
            {v.hub ? (
              <div className="revit-publisher-muted">
                Hub: {v.hub.status}
                {v.hub.status === 'publish' && v.hub.permalink && (
                  <> · <a href={v.hub.permalink} target="_blank" rel="noreferrer" onClick={(e) => e.stopPropagation()}>View</a></>
                )}
              </div>
            ) : (
              <div className="revit-publisher-muted">No public hub</div>
            )}
          </button>
        ))}
      </div>

      {selectedHub && (
        <div className="revit-publisher-card">
          <h2>{selectedHub.title}</h2>
          <ul className="revit-publisher-list">
            <li>Status: <strong>{selectedHub.status}</strong></li>
            <li>Vehicle key: {selectedHub.vehicle_key}</li>
          </ul>
          <div className="revit-publisher-actions">
            <a className="button" href={selectedHub.edit_url}>Edit Hub</a>
            {selectedHub.permalink && selectedHub.status === 'publish' && (
              <a className="button" href={selectedHub.permalink} target="_blank" rel="noreferrer">View Public Hub</a>
            )}
          </div>
        </div>
      )}

      {!selectedHub && selected && displayPreview && (
        <div className="revit-publisher-card">
          <h2>Create Vehicle Hub</h2>
          <div className="revit-publisher-preview">
            <h3>{displayPreview.vehicle_label}</h3>
            <ul className="revit-publisher-list">
              <li>Years: {displayPreview.years}</li>
              <li>
                Engine{displayPreview.engines.length !== 1 ? 's' : ''}:{' '}
                {displayPreview.engines.length > 0 ? displayPreview.engines.join(', ') : '—'}
              </li>
              <li>Published Articles: {displayPreview.published_articles}</li>
              <li>Clusters: {displayPreview.clusters_count}</li>
            </ul>
          </div>
          <p className="revit-publisher-disclaimer">Hubs are created as drafts and never auto-published.</p>
          <div className="revit-publisher-actions">
            <button
              type="button"
              disabled={creating}
              onClick={() => handleCreateHub(displayPreview.vehicle_label)}
            >
              {creating ? 'Creating…' : 'Create Hub Draft'}
            </button>
          </div>
        </div>
      )}

      {detail && !selectedHub && (
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

      {hubs.length > 0 && (
        <div className="revit-publisher-card">
          <h2>Vehicle Hubs ({hubs.length})</h2>
          {hubs.map((hub) => (
            <div key={hub.hub_id} className="revit-publisher-list-item">
              <strong>{hub.title}</strong>
              <div className="revit-publisher-muted">
                {hub.status}
                {hub.vehicle_label && <> · {hub.vehicle_label}</>}
              </div>
              <div className="revit-publisher-actions">
                <a href={hub.edit_url}>Edit</a>
                {hub.permalink && hub.status === 'publish' && (
                  <a href={hub.permalink} target="_blank" rel="noreferrer">View</a>
                )}
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
