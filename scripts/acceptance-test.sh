#!/usr/bin/env bash
# Runtime acceptance test for RevIt Publisher Phase 1.
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

echo "==> Preview package..."
docker compose run --rm wpcli wp eval "
  wp_set_current_user(1);
  \$request = new WP_REST_Request('POST', '/revit-publisher/v1/article-packages/preview');
  \$request->set_header('Content-Type', 'application/json');
  \$request->set_body(file_get_contents('${PLUGIN}/examples/article-valid.json'));
  \$response = rest_do_request(\$request);
  if (!\$response->get_data()['valid']) { exit(1); }
  echo 'Preview OK: ' . \$response->get_data()['article']['title'];
"

echo "==> Import package..."
POST_ID=$(docker compose run --rm wpcli wp eval "
  wp_set_current_user(1);
  \$request = new WP_REST_Request('POST', '/revit-publisher/v1/article-packages/import');
  \$request->set_header('Content-Type', 'application/json');
  \$request->set_body(file_get_contents('${PLUGIN}/examples/article-valid.json'));
  \$response = rest_do_request(\$request);
  \$data = \$response->get_data();
  if (empty(\$data['success'])) { fwrite(STDERR, wp_json_encode(\$data)); exit(1); }
  echo (int) \$data['post_id'];
")

echo "Imported post ID: $POST_ID"

echo "==> Verifying post..."
docker compose run --rm wpcli wp post get "$POST_ID" --field=post_title
test "$(docker compose run --rm wpcli wp post get "$POST_ID" --field=post_status)" = "draft"
docker compose run --rm wpcli wp post meta get "$POST_ID" _revit_article_key
docker compose run --rm wpcli wp post meta get "$POST_ID" _revit_package_hash
docker compose run --rm wpcli wp post term list "$POST_ID" revit_manufacturer --field=name | grep -q BMW

echo "==> Duplicate import test..."
DUPE_STATUS=$(docker compose run --rm wpcli wp eval "
  wp_set_current_user(1);
  \$request = new WP_REST_Request('POST', '/revit-publisher/v1/article-packages/import');
  \$request->set_header('Content-Type', 'application/json');
  \$request->set_body(file_get_contents('${PLUGIN}/examples/article-valid.json'));
  \$response = rest_do_request(\$request);
  echo \$response->get_status();
")

echo "Duplicate HTTP status: $DUPE_STATUS"
test "$DUPE_STATUS" = "409"

echo "==> Acceptance test passed."
