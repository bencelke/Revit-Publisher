#!/usr/bin/env bash
# Phase 3 content intelligence acceptance test.
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

reset_graph_articles() {
  docker compose run --rm wpcli wp eval "
    foreach (array('bmw-x3-g01-m40i-coolant-loss','bmw-x3-g01-m40i-cooling-guide','bmw-x3-g01-m40i-water-pump') as \$key) {
      \$posts = get_posts(array('post_type'=>'post','post_status'=>'any','numberposts'=>-1,'meta_key'=>'_revit_article_key','meta_value'=>\$key,'fields'=>'ids'));
      foreach (\$posts as \$id) wp_delete_post((int)\$id, true);
    }
  "
}

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

echo "==> Import content plan"
PLAN_ID=$(docker compose run --rm wpcli wp eval "
  wp_set_current_user(1);
  \$request = new WP_REST_Request('POST', '/revit-publisher/v1/content-plans/import');
  \$request->set_header('Content-Type', 'application/json');
  \$request->set_body(file_get_contents('${PLUGIN}/examples/content-plan-valid.json'));
  \$response = rest_do_request(\$request);
  echo (int) \$response->get_data()['plan_id'];
")

reset_graph_articles
COOLANT=$(import_json "${PLUGIN}/examples/graph/x3-coolant-loss.json")
PILLAR=$(import_json "${PLUGIN}/examples/graph/x3-cooling-pillar.json")
PUMP=$(import_json "${PLUGIN}/examples/graph/x3-water-pump.json")

echo "==> Verify plan coverage"
docker compose run --rm wpcli wp eval "
  wp_set_current_user(1);
  \$coverage = RevIt_Publisher_Services::plan_service()->get_coverage(${PLAN_ID});
  if ((int)\$coverage['summary']['planned_articles'] !== 13) exit(1);
  if ((int)\$coverage['summary']['existing_articles'] < 3) exit(1);
  echo 'Coverage OK';
"

echo "==> Verify pillar resolves"
docker compose run --rm wpcli wp eval "
  \$pillar = RevIt_Publisher_Services::graph()->get_pillar_article(${COOLANT});
  if ((\$pillar['status'] ?? '') !== 'resolved') exit(1);
  echo 'Pillar resolved';
"

echo "==> Apply contextual link"
docker compose run --rm wpcli wp eval "
  wp_set_current_user(1);
  \$suggestions = RevIt_Publisher_Services::link_service()->get_suggestions(${COOLANT});
  if (empty(\$suggestions)) exit(1);
  RevIt_Publisher_Services::link_service()->apply_link(${COOLANT}, \$suggestions[0]);
  echo 'Link applied';
"

echo "==> SEO analysis"
docker compose run --rm wpcli wp eval "
  \$analysis = RevIt_Publisher_Services::seo_score()->analyze(${COOLANT});
  if ((int)(\$analysis['total_score'] ?? 0) <= 0) exit(1);
  echo 'SEO Health: ' . \$analysis['total_score'];
"

echo "==> Update preview"
docker compose run --rm wpcli wp eval "
  wp_set_current_user(1);
  \$package = json_decode(file_get_contents('${PLUGIN}/examples/graph/x3-coolant-loss.json'));
  \$package->seo->seo_title = 'Updated Acceptance SEO Title';
  \$request = new WP_REST_Request('POST', '/revit-publisher/v1/article-packages/update-preview');
  \$request->set_header('Content-Type', 'application/json');
  \$request->set_body(wp_json_encode(array('post_id'=>${COOLANT}, 'mode'=>'seo') + json_decode(wp_json_encode(\$package), true)));
  \$response = rest_do_request(\$request);
  if ((\$response->get_data()['status'] ?? '') !== 'changed') exit(1);
  echo 'Update preview OK';
"

echo "Phase 3 acceptance test passed."
