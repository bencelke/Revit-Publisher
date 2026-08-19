#!/usr/bin/env bash
# Phase 5 public vehicle SEO acceptance test.
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
docker compose run --rm wpcli wp rewrite flush --hard
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

publish_post() {
  local id="$1"
  docker compose run --rm wpcli wp post update "$id" --post_status=publish >/dev/null
}

echo "==> Import X3 ecosystem"
PILLAR=$(import_json "${PLUGIN}/examples/graph/x3-cooling-pillar.json")
COOLANT=$(import_json "${PLUGIN}/examples/graph/x3-coolant-loss.json")
PUMP=$(import_json "${PLUGIN}/examples/graph/x3-water-pump.json")
publish_post "$PILLAR"
publish_post "$COOLANT"
publish_post "$PUMP"

echo "==> Create vehicle hub draft"
HUB=$(docker compose run --rm wpcli wp eval "
  wp_set_current_user(1);
  \$identity = RevIt_Publisher_Services::vehicle_hubs()->identity_from_label('BMW X3 G01 M40i');
  if (null === \$identity) exit(1);
  \$key = (string)(\$identity['vehicle_key'] ?? '');
  \$hub = RevIt_Publisher_Services::vehicle_hubs()->create_draft(\$key, \$identity);
  if (is_wp_error(\$hub)) exit(1);
  echo (int)\$hub;
")

echo "==> Verify hub sections"
docker compose run --rm wpcli wp eval "
  \$sections = RevIt_Publisher_Services::vehicle_hubs()->get_articles_by_section(${HUB});
  if (empty(\$sections['common_problems'])) exit(1);
  foreach (\$sections['common_problems'] as \$article) {
    if ('publish' !== get_post_status((int)\$article['post_id'])) exit(1);
  }
  echo 'Sections OK';
"

echo "==> Publish hub"
publish_post "$HUB"

echo "==> Verify public hub URL and sitemap"
docker compose run --rm wpcli wp eval "
  \$url = get_permalink(${HUB});
  if (!is_string(\$url) || '' === \$url) exit(1);
  if (!RevIt_Publisher_Services::sitemap()->is_post_indexable(${HUB}, 'revit_vehicle')) exit(1);
  echo \$url;
"

echo "==> Verify noindex article excluded"
docker compose run --rm wpcli wp eval "
  update_post_meta(${COOLANT}, RevIt_Publisher_Post_Meta_Keys::INDEX, '0');
  if (RevIt_Publisher_Services::sitemap()->is_post_indexable(${COOLANT}, 'post')) exit(1);
  echo 'Noindex OK';
"

echo "==> Issue retention purge"
docker compose run --rm wpcli wp eval "
  \$resolved = wp_insert_post(array('post_type' => RevIt_Publisher_Operations_Post_Types::ISSUE, 'post_status' => 'private', 'post_title' => 'Old'));
  update_post_meta(\$resolved, RevIt_Publisher_Operations_Meta_Keys::ISSUE_STATUS, RevIt_Publisher_Issue_Service::STATUS_RESOLVED);
  update_post_meta(\$resolved, RevIt_Publisher_Operations_Meta_Keys::ISSUE_RESOLVED_AT, gmdate('c', time() - (400 * DAY_IN_SECONDS)));
  RevIt_Publisher_Issue_Retention_Cron::run_purge();
  if (get_post(\$resolved)) exit(1);
  echo 'Retention OK';
"

echo "Phase 5 public SEO acceptance passed."
