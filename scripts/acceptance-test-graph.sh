#!/usr/bin/env bash
# Phase 2 graph/linking/SEO acceptance test for RevIt Publisher.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

echo "==> Starting Docker environment..."
docker compose up -d

echo "==> Waiting for WordPress..."
for _ in $(seq 1 30); do
  if curl -sf http://localhost:8080 >/dev/null 2>&1; then
    break
  fi
  sleep 2
done

echo "==> Installing WordPress (if needed)..."
docker compose run --rm wpcli wp core is-installed >/dev/null 2>&1 || \
  docker compose run --rm wpcli wp core install \
    --url="http://localhost:8080" \
    --title="RevIt Publisher Dev" \
    --admin_user="admin" \
    --admin_password="adminpass" \
    --admin_email="admin@example.com" \
    --skip-email

echo "==> Installing plugin dependencies..."
docker compose run --rm --entrypoint sh wpcli -c "
  cd /var/www/html/wp-content/plugins/revit-publisher
  if [ ! -f vendor/autoload.php ]; then
    curl -sS https://getcomposer.org/installer | php
    php composer.phar install --no-interaction
  fi
"

echo "==> Activating RevIt Publisher..."
docker compose run --rm wpcli wp plugin activate revit-publisher

if command -v npm >/dev/null 2>&1; then
  echo "==> Building admin assets..."
  npm run build
fi

PLUGIN='/var/www/html/wp-content/plugins/revit-publisher'
GRAPH="${PLUGIN}/examples/graph"

echo "==> Resetting graph example articles..."
docker compose run --rm wpcli wp eval "
  \$keys = array('bmw-x3-g01-m40i-coolant-loss','bmw-x3-g01-m40i-cooling-guide','bmw-x3-g01-m40i-water-pump');
  foreach (\$keys as \$key) {
    \$posts = get_posts(array(
      'post_type' => 'post',
      'post_status' => 'any',
      'numberposts' => -1,
      'meta_key' => '_revit_article_key',
      'meta_value' => \$key,
      'fields' => 'ids',
    ));
    foreach (\$posts as \$id) {
      wp_delete_post((int) \$id, true);
    }
  }
  echo 'Graph articles reset';
"

import_json() {
  local file="$1"
  docker compose run --rm wpcli wp eval "
    wp_set_current_user(1);
    \$request = new WP_REST_Request('POST', '/revit-publisher/v1/article-packages/import');
    \$request->set_header('Content-Type', 'application/json');
    \$request->set_body(file_get_contents('${file}'));
    \$response = rest_do_request(\$request);
    \$data = \$response->get_data();
    if (!empty(\$data['success'])) { echo (int) \$data['post_id']; return; }
    if ((\$data['status'] ?? '') === 'existing_article') { echo (int) \$data['post_id']; return; }
    fwrite(STDERR, wp_json_encode(\$data)); exit(1);
  "
}

echo "==> Import supporting article before pillar..."
COOLANT_ID=$(import_json "${GRAPH}/x3-coolant-loss.json")
echo "Coolant post ID: $COOLANT_ID"

echo "==> Verify pillar unresolved..."
docker compose run --rm wpcli wp eval "
  wp_set_current_user(1);
  \$pillar = RevIt_Publisher_Services::graph()->get_pillar_article(${COOLANT_ID});
  if ((\$pillar['status'] ?? '') !== 'pillar_planned') { exit(1); }
  echo 'Pillar planned — not imported yet';
"

echo "==> Import pillar article..."
PILLAR_ID=$(import_json "${GRAPH}/x3-cooling-pillar.json")
echo "Pillar post ID: $PILLAR_ID"

echo "==> Verify pillar relationship resolves..."
docker compose run --rm wpcli wp eval "
  wp_set_current_user(1);
  \$pillar = RevIt_Publisher_Services::graph()->get_pillar_article(${COOLANT_ID});
  if ((\$pillar['status'] ?? '') !== 'resolved') { exit(1); }
  echo 'Pillar resolved: ' . \$pillar['title'];
"

echo "==> Import water pump article..."
PUMP_ID=$(import_json "${GRAPH}/x3-water-pump.json")
echo "Water pump post ID: $PUMP_ID"

echo "==> Verify link opportunities..."
docker compose run --rm wpcli wp eval "
  wp_set_current_user(1);
  \$suggestions = RevIt_Publisher_Services::link_service()->get_suggestions(${COOLANT_ID});
  if (empty(\$suggestions)) { exit(1); }
  echo count(\$suggestions) . ' link suggestions';
"

echo "==> Apply one contextual link..."
docker compose run --rm wpcli wp eval "
  wp_set_current_user(1);
  \$suggestions = RevIt_Publisher_Services::link_service()->get_suggestions(${COOLANT_ID});
  \$result = RevIt_Publisher_Services::link_service()->apply_link(${COOLANT_ID}, \$suggestions[0]);
  if (true !== \$result) { exit(1); }
  echo 'Link applied';
"

echo "==> Verify Gutenberg content remains valid..."
docker compose run --rm wpcli wp eval "
  \$post = get_post(${COOLANT_ID});
  \$blocks = parse_blocks(\$post->post_content);
  if (empty(\$blocks)) { exit(1); }
  if (!str_contains(\$post->post_content, '<a href=')) { exit(1); }
  echo 'Gutenberg valid with link';
"

echo "==> Publish and verify SEO output..."
docker compose run --rm wpcli wp post update "$COOLANT_ID" --post_status=publish

HTML=$(curl -s "http://localhost:8080/?p=${COOLANT_ID}")
echo "$HTML" | grep -q 'name="description"'
echo "$HTML" | grep -q 'rel="canonical"'
echo "$HTML" | grep -q 'application/ld+json'
echo "$HTML" | grep -q '"@type":"Article"'

echo "==> Verify link audit..."
docker compose run --rm wpcli wp eval "
  wp_set_current_user(1);
  \$audit = RevIt_Publisher_Services::audit_service()->audit_all_links();
  if ((int) (\$audit['resolved'] ?? 0) < 1) { exit(1); }
  echo 'Resolved links: ' . \$audit['resolved'];
"

echo "==> Verify duplicate topic detection..."
docker compose run --rm wpcli wp eval "
  wp_set_current_user(1);
  \$duplicates = RevIt_Publisher_Services::health_service()->find_duplicate_topics();
  echo 'Duplicate topic groups: ' . count(\$duplicates);
"

echo "==> Phase 2 graph acceptance test passed."
