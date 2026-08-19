#!/usr/bin/env bash
# Phase 4 SEO operations acceptance test.
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

docker compose run --rm wpcli wp plugin activate revit-publisher
npm run build >/dev/null 2>&1 || true

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
  "
}

echo "==> Import ecosystem"
PILLAR=$(import_json "${PLUGIN}/examples/graph/x3-cooling-pillar.json")
COOLANT=$(import_json "${PLUGIN}/examples/graph/x3-coolant-loss.json")
PUMP=$(import_json "${PLUGIN}/examples/graph/x3-water-pump.json")

echo "==> Run audit"
docker compose run --rm wpcli wp eval "
  wp_set_current_user(1);
  \$result = RevIt_Publisher_Services::site_audit()->run(true);
  if (empty(\$result['success']) && (\$result['status'] ?? '') !== 'batch') exit(1);
  echo 'Audit OK';
"

echo "==> Verify issues queue"
docker compose run --rm wpcli wp eval "
  \$issues = RevIt_Publisher_Services::issues()->list_issues();
  if (!is_array(\$issues)) exit(1);
  echo count(\$issues) . ' issues';
"

echo "==> Consolidation preview"
docker compose run --rm wpcli wp eval "
  wp_set_current_user(1);
  \$preview = RevIt_Publisher_Services::consolidation()->preview(${PUMP}, ${PILLAR});
  if (is_wp_error(\$preview)) exit(1);
  echo 'Preview OK';
"

echo "==> Apply consolidation"
docker compose run --rm wpcli wp eval "
  wp_set_current_user(1);
  \$result = RevIt_Publisher_Services::consolidation()->apply(${PUMP}, ${PILLAR}, 'draft');
  if (is_wp_error(\$result)) {
    if ('revit_duplicate_source' === \$result->get_error_code()) {
      echo 'Consolidation OK (existing redirect)';
      return;
    }
    exit(1);
  }
  if (empty(\$result['success'])) exit(1);
  echo 'Consolidation OK';
"

echo "==> Verify redirect lookup"
docker compose run --rm wpcli wp eval "
  \$pump = RevIt_Publisher_Services::registry()->find_post_id_by_article_key('bmw-x3-g01-m40i-water-pump');
  \$path = RevIt_Publisher_Services::redirects()->get_post_path((int)\$pump);
  \$redirect = RevIt_Publisher_Services::redirects()->lookup(\$path);
  if (null === \$redirect) exit(1);
  echo 'Redirect OK';
"

echo "==> Verify source not deleted"
docker compose run --rm wpcli wp eval "
  \$post = get_post(${PUMP});
  if (!\$post) exit(1);
  echo 'Source preserved: ' . \$post->post_status;
"

echo "Phase 4 acceptance test passed."
