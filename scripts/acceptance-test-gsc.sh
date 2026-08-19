#!/usr/bin/env bash
# Phase 6 Google Search Console acceptance test (fixture client).
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

docker compose up -d
for _ in $(seq 1 30); do curl -sf http://localhost:8080 >/dev/null 2>&1 && break; sleep 2; done

docker compose run --rm wpcli wp core is-installed >/dev/null 2>&1 || \
  docker compose run --rm wpcli wp core install \
    --url="http://localhost:8080" --title="RevIt Dev" \
    --admin_user="admin" --admin_password="adminpass" \
    --admin_email="admin@example.com" --skip-email

docker compose run --rm wpcli wp rewrite structure '/%postname%/' --hard
docker compose run --rm wpcli wp plugin activate revit-publisher

PLUGIN='/var/www/html/wp-content/plugins/revit-publisher'

import_json() {
  local file="$1"
  docker compose run --rm wpcli wp eval "
    wp_set_current_user(1);
    \$request = new WP_REST_Request('POST', '/revit-publisher/v1/article-packages/import');
    \$request->set_header('Content-Type', 'application/json');
    \$request->set_body(file_get_contents('${file}'));
    \$response = rest_do_request(\$request);
    \$data = \$response->get_data();
    if (!empty(\$data['success'])) { echo (int)\$data['post_id']; return; }
    if ((\$data['status'] ?? '') === 'existing_article') { echo (int)\$data['post_id']; return; }
    exit(1);
  " | tr -d '[:space:]'
}

publish_post() {
  docker compose run --rm wpcli wp post update "$1" --post_status=publish >/dev/null
}

echo "==> Import X3 articles"
COOLANT=$(import_json "${PLUGIN}/examples/graph/x3-coolant-loss.json")
PUMP=$(import_json "${PLUGIN}/examples/graph/x3-water-pump.json")
PILLAR=$(import_json "${PLUGIN}/examples/graph/x3-cooling-pillar.json")
publish_post "$COOLANT"
publish_post "$PUMP"
publish_post "$PILLAR"

echo "==> Connect fixture GSC and sync"
docker compose run --rm wpcli wp eval "
  RevIt_Publisher_GSC_Schema::install();
  wp_set_current_user(1);
  RevIt_Publisher_Services::gsc_auth()->connect_fixture();
  \$result = RevIt_Publisher_Services::gsc_sync()->sync(true);
  if (is_wp_error(\$result)) exit(1);
  if (empty(\$result['success'])) exit(1);
  echo 'Sync OK: ' . (int)(\$result['pages_updated'] ?? 0) . ' pages';
"

echo "==> Verify metrics and opportunities"
docker compose run --rm wpcli wp eval "
  \$summary = RevIt_Publisher_Services::gsc_insights()->get_summary_with_comparison('28d');
  if ((int)(\$summary['current']['impressions'] ?? 0) <= 0) exit(1);
  \$opps = RevIt_Publisher_Services::gsc_opportunities()->list_opportunities('28d');
  \$types = array_column(\$opps, 'issue_type');
  if (!in_array('gsc_page2_opportunity', \$types, true)) exit(1);
  echo count(\$opps) . ' opportunities';
"

echo "==> Inspect URL and refresh export privacy"
docker compose run --rm wpcli wp eval "
  wp_set_current_user(1);
  \$inspect = RevIt_Publisher_Services::gsc_inspections()->inspect_post(${COOLANT});
  if (is_wp_error(\$inspect)) exit(1);
  \$export = RevIt_Publisher_Services::gsc_refresh_export()->export_for_post(${PUMP}, 'page2_opportunity');
  if (is_wp_error(\$export)) exit(1);
  \$json = wp_json_encode(\$export);
  if (str_contains(\$json, 'access_token')) exit(1);
  echo 'Inspect + export OK';
"

echo "==> Verify sync lock"
docker compose run --rm wpcli wp eval "
  wp_set_current_user(1);
  set_transient('revit_gsc_sync_lock', '1', 60);
  \$result = RevIt_Publisher_Services::gsc_sync()->sync(true);
  if (!is_wp_error(\$result)) exit(1);
  delete_transient('revit_gsc_sync_lock');
  echo 'Lock OK';
"

echo "Phase 6 GSC acceptance passed."
