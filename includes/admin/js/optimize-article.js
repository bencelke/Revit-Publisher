(function () {
  function panelEl() {
    return document.getElementById('revit-optimize-panel');
  }

  function config() {
    return window.revitPublisherOptimize || {};
  }

  async function request(path, options) {
    const cfg = config();
    const response = await fetch(cfg.restUrl + path, {
      ...options,
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': cfg.nonce,
        ...(options && options.headers),
      },
    });
    const data = await response.json().catch(function () { return {}; });
    if (!response.ok) {
      throw new Error(data.message || 'Request failed');
    }
    return data;
  }

  function render(data) {
    const panel = panelEl();
    if (!panel) return;
    const checklist = data.checklist || {};
    const issues = checklist.issues || [];
    const fixes = data.safe_fixes || [];
    const links = data.link_opportunities || [];
    const inbound = data.inbound_opportunities || [];

    let html = '<p><strong>Scan complete</strong> — status: ' + (data.post_status || '') + '</p>';
    html += '<p>Inbound ' + (data.inbound_count || 0) + ' · Outbound ' + (data.outbound_count || 0) + '</p>';
    html += '<p><strong>Issues</strong></p><ul>';
    if (!issues.length) {
      html += '<li>No mechanical SEO issues.</li>';
    }
    issues.slice(0, 12).forEach(function (issue) {
      html += '<li>' + escapeHtml(issue.message) + (issue.safe_fix ? ' (safe fix)' : ' (review)') + '</li>';
    });
    html += '</ul>';

    if (fixes.length) {
      html += '<p><strong>Safe fixes</strong></p><ul>';
      fixes.forEach(function (fix) {
        html += '<li>' + escapeHtml(fix.label) + '<br /><span>Before: ' + escapeHtml(String(fix.before || '—')) + ' → After: ' + escapeHtml(String(fix.after || '')) + '</span></li>';
      });
      html += '</ul>';
      html += '<p><button type="button" class="button" id="revit-apply-safe">Apply Safe Fixes</button></p>';
    }

    html += '<p><strong>Link opportunities</strong></p><ul>';
    if (!links.length) {
      html += '<li>No natural-anchor opportunities found.</li>';
    }
    links.slice(0, 8).forEach(function (idea, index) {
      html += '<li>' + escapeHtml(idea.source_title) + ' → ' + escapeHtml(idea.target_title);
      html += '<br />Anchor: “' + escapeHtml(idea.anchor) + '” · ' + escapeHtml(idea.reason);
      html += ' · ' + escapeHtml(idea.confidence) + (idea.safe_to_auto_apply ? ' · Safe' : ' · Review');
      if (idea.safe_to_auto_apply) {
        html += ' <button type="button" class="button revit-apply-link" data-index="' + index + '">Apply link</button>';
      }
      html += '</li>';
    });
    html += '</ul>';
    if (inbound.length) {
      html += '<p>Recommended inbound opportunities: ' + inbound.length + '</p>';
    }
    html += '<p id="revit-optimize-notice"></p>';
    panel.innerHTML = html;
    panel.dataset.links = JSON.stringify(links);
    panel.dataset.fixes = JSON.stringify(fixes);
  }

  function escapeHtml(value) {
    return String(value || '').replace(/[&<>"']/g, function (ch) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[ch];
    });
  }

  function notice(message) {
    const el = document.getElementById('revit-optimize-notice');
    if (el) el.textContent = message;
  }

  document.addEventListener('click', async function (event) {
    const target = event.target;
    if (!(target instanceof HTMLElement)) return;

    if (target.id === 'revit-optimize-article') {
      const postId = target.getAttribute('data-post-id');
      const panel = panelEl();
      if (!postId || !panel) return;
      panel.style.display = 'block';
      panel.textContent = 'Scanning article…';
      try {
        const data = await request('/seo-scan/articles/' + postId);
        render(data);
        panel.dataset.postId = postId;
      } catch (err) {
        panel.textContent = err instanceof Error ? err.message : 'Scan failed.';
      }
    }

    if (target.id === 'revit-apply-safe') {
      const panel = panelEl();
      const postId = panel && panel.dataset.postId;
      const fixes = panel ? JSON.parse(panel.dataset.fixes || '[]') : [];
      if (!postId) return;
      try {
        const result = await request('/seo-scan/articles/' + postId + '/apply-safe', {
          method: 'POST',
          body: JSON.stringify({ codes: fixes.map(function (fix) { return fix.code; }) }),
        });
        notice('Applied ' + (result.applied || []).length + ' safe fix(es). Post remains ' + (result.post_status || 'draft') + '.');
        if (result.optimize) render(result.optimize);
      } catch (err) {
        notice(err instanceof Error ? err.message : 'Apply failed.');
      }
    }

    if (target.classList.contains('revit-apply-link')) {
      const panel = panelEl();
      const links = panel ? JSON.parse(panel.dataset.links || '[]') : [];
      const idea = links[Number(target.getAttribute('data-index'))];
      if (!idea) return;
      try {
        const result = await request('/seo-scan/apply-link', {
          method: 'POST',
          body: JSON.stringify({
            source_post_id: idea.source_post_id,
            target_post_id: idea.target_post_id,
            anchor: idea.anchor,
            relationship: idea.relationship || 'related',
          }),
        });
        notice('Link applied. Gutenberg content updated. Status: ' + (result.post_status || 'draft') + '.');
      } catch (err) {
        notice(err instanceof Error ? err.message : 'Link apply failed.');
      }
    }
  });
})();
