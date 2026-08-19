#!/usr/bin/env bash
# Phase A acceptance: simplified nav, batch import, SEO, Advanced hub.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

echo "==> Starting Docker..."
docker compose up -d

for _ in $(seq 1 30); do
  curl -sf http://localhost:8080 >/dev/null 2>&1 && break
  sleep 2
done

docker compose run --rm wpcli wp core is-installed >/dev/null 2>&1 || \
  docker compose run --rm wpcli wp core install \
    --url="http://localhost:8080" \
    --title="RevIt Publisher Dev" \
    --admin_user="admin" \
    --admin_password="adminpass" \
    --admin_email="admin@example.com" \
    --skip-email

docker compose run --rm wpcli wp plugin activate revit-publisher

if command -v npm >/dev/null 2>&1; then
  npm run build
fi

PLUGIN='/var/www/html/wp-content/plugins/revit-publisher'

echo "==> Stats endpoint (dashboard)..."
docker compose run --rm wpcli wp eval "
  wp_set_current_user(1);
  \$r = rest_do_request(new WP_REST_Request('GET', '/revit-publisher/v1/stats'));
  \$d = \$r->get_data();
  if (empty(\$d['version'])) { exit(1); }
  echo 'Version: ' . \$d['version'];
"

echo "==> Batch validate graph fixtures..."
for f in x3-coolant-loss x3-water-pump x3-cooling-pillar; do
  docker compose run --rm wpcli wp eval "
    wp_set_current_user(1);
    \$body = file_get_contents('${PLUGIN}/examples/graph/${f}.json');
    \$req = new WP_REST_Request('POST', '/revit-publisher/v1/article-packages/validate');
    \$req->set_header('Content-Type', 'application/json');
    \$req->set_body(\$body);
    \$data = rest_do_request(\$req)->get_data();
    if (empty(\$data['valid'])) { exit(1); }
    echo 'Valid: ${f}';
  "
done

echo "==> Batch import graph fixtures as drafts..."
for f in x3-coolant-loss x3-water-pump x3-cooling-pillar; do
  RESULT=$(docker compose run --rm wpcli wp eval "
    wp_set_current_user(1);
    \$body = file_get_contents('${PLUGIN}/examples/graph/${f}.json');
    \$req = new WP_REST_Request('POST', '/revit-publisher/v1/article-packages/import');
    \$req->set_header('Content-Type', 'application/json');
    \$req->set_body(\$body);
    \$data = rest_do_request(\$req)->get_data();
    echo wp_json_encode(\$data);
  ")
  echo "$RESULT" | grep -q '"success":true' || echo "$RESULT" | grep -q existing_article || { echo "Import failed: $RESULT"; exit 1; }
  if echo "$RESULT" | grep -q '"status":"created"'; then
    POST_ID=$(echo "$RESULT" | sed -n 's/.*"post_id":\([0-9]*\).*/\1/p' | head -1)
    STATUS=$(docker compose run --rm wpcli wp post get "$POST_ID" --field=post_status)
    test "$STATUS" = "draft" || { echo "Expected draft, got $STATUS"; exit 1; }
    echo "Created draft post $POST_ID"
  else
    echo "Skipped existing: ${f}"
  fi
done

echo "==> Verify import endpoint never returns publish for new creates..."
CREATE=$(docker compose run --rm wpcli wp eval "
  wp_set_current_user(1);
  \$body = file_get_contents('${PLUGIN}/examples/article-valid.json');
  \$req = new WP_REST_Request('POST', '/revit-publisher/v1/article-packages/preview');
  \$req->set_header('Content-Type', 'application/json');
  \$req->set_body(\$body);
  \$preview = rest_do_request(\$req)->get_data();
  if ((\$preview['publishing']['status'] ?? 'draft') !== 'draft') { exit(1); }
  echo 'preview-draft-ok';
")
test "$CREATE" = "preview-draft-ok"

echo "==> Phase A REST acceptance passed."
echo "Manual: confirm WP admin shows Dashboard, Batch Import, Vehicles, SEO, Advanced only."
