import { FormEvent, useEffect, useState } from 'react';
import {
  connectGsc,
  disconnectGsc,
  fetchGscProperties,
  fetchGscStatus,
  setGscProperty,
  syncGsc,
} from '../api/search-console';
import { fetchSettings, updateSettings } from '../api/graph';
import { SettingsResponse } from '../types/article-package';
import { GscProperty, GscStatus } from '../types/search-console';

export function SettingsPage() {
  const [settings, setSettings] = useState<SettingsResponse | null>(null);
  const [gscStatus, setGscStatus] = useState<GscStatus | null>(null);
  const [gscProperties, setGscProperties] = useState<GscProperty[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [saved, setSaved] = useState(false);
  const [gscBusy, setGscBusy] = useState(false);

  useEffect(() => {
    fetchSettings()
      .then(setSettings)
      .catch((err: unknown) => setError(err instanceof Error ? err.message : 'Failed to load settings.'));
    fetchGscStatus()
      .then(setGscStatus)
      .catch(() => setGscStatus(null));
  }, []);

  async function refreshGsc() {
    const status = await fetchGscStatus();
    setGscStatus(status);
    if (status.connected) {
      try {
        setGscProperties(await fetchGscProperties());
      } catch {
        setGscProperties([]);
      }
    }
  }

  async function handleGscConnect(fixture: boolean) {
    setGscBusy(true);
    setError(null);
    try {
      const result = await connectGsc(fixture);
      if (result.oauth_url) {
        window.location.href = result.oauth_url;
        return;
      }
      await refreshGsc();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Connection failed.');
    } finally {
      setGscBusy(false);
    }
  }

  async function handleGscDisconnect() {
    setGscBusy(true);
    try {
      await disconnectGsc();
      setGscProperties([]);
      await refreshGsc();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Disconnect failed.');
    } finally {
      setGscBusy(false);
    }
  }

  async function handleGscSync() {
    setGscBusy(true);
    setError(null);
    try {
      await syncGsc();
      await refreshGsc();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Sync failed.');
    } finally {
      setGscBusy(false);
    }
  }

  async function handlePropertyChange(property: string) {
    setGscBusy(true);
    try {
      await setGscProperty(property);
      setSettings((s) => (s ? { ...s, gsc_property: property } : s));
      await refreshGsc();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to set property.');
    } finally {
      setGscBusy(false);
    }
  }

  async function handleSubmit(event: FormEvent) {
    event.preventDefault();
    if (!settings) return;
    setSaved(false);
    try {
      const updated = await updateSettings(settings);
      setSettings(updated);
      setSaved(true);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to save settings.');
    }
  }

  if (!settings) {
    return <p className="revit-publisher-muted">Loading settings…</p>;
  }

  return (
    <div className="revit-publisher-panel revit-publisher-dark">
      <h1>Settings</h1>

      {settings.seo_plugin_conflict && (
        <div className="revit-publisher-result revit-publisher-result--error">
          <p>{settings.seo_plugin_conflict}</p>
        </div>
      )}

      <form className="revit-publisher-card" onSubmit={handleSubmit}>
        <h2>Public SEO Output</h2>
        <label><input type="checkbox" checked={settings.enable_meta_description} onChange={(e) => setSettings({ ...settings, enable_meta_description: e.target.checked })} /> Enable meta descriptions</label>
        <label><input type="checkbox" checked={settings.enable_canonical} onChange={(e) => setSettings({ ...settings, enable_canonical: e.target.checked })} /> Enable canonical URLs</label>
        <label><input type="checkbox" checked={settings.enable_robots} onChange={(e) => setSettings({ ...settings, enable_robots: e.target.checked })} /> Enable robots directives</label>
        <label><input type="checkbox" checked={settings.enable_article_schema} onChange={(e) => setSettings({ ...settings, enable_article_schema: e.target.checked })} /> Enable Article schema</label>
        <label><input type="checkbox" checked={settings.enable_breadcrumb_schema} onChange={(e) => setSettings({ ...settings, enable_breadcrumb_schema: e.target.checked })} /> Enable Breadcrumb schema</label>

        <h2>Internal Linking</h2>
        <label>Max suggested links per article<input type="number" min={1} max={20} value={settings.max_suggested_links} onChange={(e) => setSettings({ ...settings, max_suggested_links: Number(e.target.value) })} /></label>
        <label>Max batch links<input type="number" min={1} max={50} value={settings.max_batch_links ?? 50} onChange={(e) => setSettings({ ...settings, max_batch_links: Number(e.target.value) })} /></label>
        <label>Max cluster links per article<input type="number" min={1} max={20} value={settings.max_cluster_links_per_article ?? 5} onChange={(e) => setSettings({ ...settings, max_cluster_links_per_article: Number(e.target.value) })} /></label>
        <label><input type="checkbox" checked={settings.avoid_duplicate_target} onChange={(e) => setSettings({ ...settings, avoid_duplicate_target: e.target.checked })} /> Avoid linking same target more than once</label>

        <h2>Content Maintenance</h2>
        <label>Review article after (months)<input type="number" min={0} max={60} value={settings.review_after_months ?? 12} onChange={(e) => setSettings({ ...settings, review_after_months: Number(e.target.value) })} /></label>
        <label><input type="checkbox" checked={settings.scheduled_audit_enabled ?? true} onChange={(e) => setSettings({ ...settings, scheduled_audit_enabled: e.target.checked })} /> Enable scheduled audit</label>
        <label>Audit frequency
          <select value={settings.audit_frequency ?? 'daily'} onChange={(e) => setSettings({ ...settings, audit_frequency: e.target.value })}>
            <option value="daily">Daily</option>
            <option value="revit_twice_daily">Twice daily</option>
            <option value="weekly">Weekly</option>
          </select>
        </label>
        <label>Issue retention (days)<input type="number" min={30} max={730} value={settings.issue_retention_days ?? 365} onChange={(e) => setSettings({ ...settings, issue_retention_days: Number(e.target.value) })} /></label>

        <h2>Redirects & 404</h2>
        <label><input type="checkbox" checked={settings.enable_404_monitor ?? false} onChange={(e) => setSettings({ ...settings, enable_404_monitor: e.target.checked })} /> Enable 404 monitoring</label>
        <label><input type="checkbox" checked={settings.external_redirects_allowed ?? false} onChange={(e) => setSettings({ ...settings, external_redirects_allowed: e.target.checked })} /> Allow external redirect targets</label>

        <h2>Site Identity</h2>
        <label>Organization name<input type="text" value={settings.org_name} onChange={(e) => setSettings({ ...settings, org_name: e.target.value })} /></label>
        <label>Organization logo URL<input type="url" value={settings.org_logo_url} onChange={(e) => setSettings({ ...settings, org_logo_url: e.target.value })} /></label>

        <h2>Google Search Console</h2>
        {gscStatus && (
          <div className="revit-gsc-settings-status">
            <p>
              Status: <strong>{gscStatus.connected ? 'Connected' : 'Not connected'}</strong>
              {gscStatus.fixture_mode && ' (fixture)'}
            </p>
            {gscStatus.connected && gscStatus.property && (
              <p className="revit-publisher-muted">Property: {gscStatus.property}</p>
            )}
            {gscStatus.last_sync && (
              <p className="revit-publisher-muted">Last sync: {gscStatus.last_sync}</p>
            )}
            {gscStatus.last_error && (
              <p className="revit-publisher-muted">Last error: {gscStatus.last_error}</p>
            )}
          </div>
        )}
        <div className="revit-publisher-actions">
          {!gscStatus?.connected && (
            <>
              {gscStatus?.credentials.client_id_configured && (
                <button type="button" disabled={gscBusy} onClick={() => handleGscConnect(false)}>
                  Connect with Google
                </button>
              )}
              <button type="button" disabled={gscBusy} onClick={() => handleGscConnect(true)}>
                Connect with Fixture (Dev)
              </button>
            </>
          )}
          {gscStatus?.connected && (
            <>
              <button type="button" disabled={gscBusy} onClick={handleGscSync}>
                {gscBusy ? 'Syncing…' : 'Sync Now'}
              </button>
              <button type="button" disabled={gscBusy} onClick={handleGscDisconnect}>
                Disconnect
              </button>
              <button
                type="button"
                disabled={gscBusy}
                onClick={() => refreshGsc().catch(() => undefined)}
              >
                Load Properties
              </button>
            </>
          )}
        </div>
        {gscStatus?.connected && gscProperties.length > 0 && (
          <label>
            Search Console Property
            <select
              value={settings.gsc_property ?? ''}
              onChange={(e) => handlePropertyChange(e.target.value)}
            >
              <option value="">Select property…</option>
              {gscProperties.map((p) => (
                <option key={p.site_url} value={p.site_url}>{p.site_url}</option>
              ))}
            </select>
          </label>
        )}
        <label><input type="checkbox" checked={settings.gsc_sync_enabled ?? true} onChange={(e) => setSettings({ ...settings, gsc_sync_enabled: e.target.checked })} /> Enable scheduled sync</label>
        <label>Sync frequency
          <select value={settings.gsc_sync_frequency ?? 'daily'} onChange={(e) => setSettings({ ...settings, gsc_sync_frequency: e.target.value })}>
            <option value="daily">Daily</option>
            <option value="twicedaily">Twice daily</option>
          </select>
        </label>
        <label>Max rows per sync<input type="number" min={100} max={5000} value={settings.gsc_max_rows ?? 1000} onChange={(e) => setSettings({ ...settings, gsc_max_rows: Number(e.target.value) })} /></label>
        <label>Daily URL inspection limit<input type="number" min={1} max={100} value={settings.gsc_inspection_daily_max ?? 20} onChange={(e) => setSettings({ ...settings, gsc_inspection_daily_max: Number(e.target.value) })} /></label>
        <label><input type="checkbox" checked={settings.gsc_sitemap_write_enabled ?? false} onChange={(e) => setSettings({ ...settings, gsc_sitemap_write_enabled: e.target.checked })} /> Allow sitemap submission (write scope)</label>

        <h3>Opportunity Thresholds</h3>
        <label>Min impressions for opportunities<input type="number" min={100} max={10000} value={settings.gsc_min_impressions ?? 1000} onChange={(e) => setSettings({ ...settings, gsc_min_impressions: Number(e.target.value) })} /></label>
        <label>Page 2 position min<input type="number" min={1} max={50} value={settings.gsc_page2_min ?? 11} onChange={(e) => setSettings({ ...settings, gsc_page2_min: Number(e.target.value) })} /></label>
        <label>Page 2 position max<input type="number" min={1} max={50} value={settings.gsc_page2_max ?? 20} onChange={(e) => setSettings({ ...settings, gsc_page2_max: Number(e.target.value) })} /></label>
        <label>Zero visibility grace (days)<input type="number" min={30} max={365} value={settings.gsc_zero_visibility_days ?? 90} onChange={(e) => setSettings({ ...settings, gsc_zero_visibility_days: Number(e.target.value) })} /></label>
        <label>Decline threshold (%)<input type="number" min={5} max={80} value={settings.gsc_decline_threshold_pct ?? 20} onChange={(e) => setSettings({ ...settings, gsc_decline_threshold_pct: Number(e.target.value) })} /></label>

        <div className="revit-publisher-actions">
          <button type="submit">Save Settings</button>
        </div>
        {saved && <p className="revit-publisher-muted">Settings saved.</p>}
        {error && <p>{error}</p>}
      </form>
    </div>
  );
}
