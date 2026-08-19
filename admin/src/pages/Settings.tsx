import { FormEvent, useEffect, useState } from 'react';
import { fetchSettings, updateSettings } from '../api/graph';
import { SettingsResponse } from '../types/article-package';

export function SettingsPage() {
  const [settings, setSettings] = useState<SettingsResponse | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [saved, setSaved] = useState(false);

  useEffect(() => {
    fetchSettings()
      .then(setSettings)
      .catch((err: unknown) => setError(err instanceof Error ? err.message : 'Failed to load settings.'));
  }, []);

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
    <div className="revit-publisher-panel">
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
        <p className="revit-publisher-muted">Default mode: Suggest Only</p>
        <label>
          Max suggested links per article
          <input type="number" min={1} max={20} value={settings.max_suggested_links} onChange={(e) => setSettings({ ...settings, max_suggested_links: Number(e.target.value) })} />
        </label>
        <label><input type="checkbox" checked={settings.avoid_duplicate_target} onChange={(e) => setSettings({ ...settings, avoid_duplicate_target: e.target.checked })} /> Avoid linking same target more than once</label>

        <h2>Site Identity</h2>
        <label>
          Organization name
          <input type="text" value={settings.org_name} onChange={(e) => setSettings({ ...settings, org_name: e.target.value })} />
        </label>
        <label>
          Organization logo URL
          <input type="url" value={settings.org_logo_url} onChange={(e) => setSettings({ ...settings, org_logo_url: e.target.value })} />
        </label>

        <div className="revit-publisher-actions">
          <button type="submit">Save Settings</button>
        </div>
        {saved && <p className="revit-publisher-muted">Settings saved.</p>}
        {error && <p>{error}</p>}
      </form>
    </div>
  );
}
