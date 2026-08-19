#!/usr/bin/env bash
# Phase 7 editorial + production hardening acceptance test.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

bash scripts/acceptance-test-gsc.sh

PLUGIN='/var/www/html/wp-content/plugins/revit-publisher'

echo "==> Reconcile editorial queue"
docker compose run --rm wpcli wp eval "
  wp_set_current_user(1);
  RevIt_Publisher_Services::site_audit()->run(true);
  \$result = RevIt_Publisher_Services::editorial_reconciler()->reconcile();
  if (empty(\$result['success'])) exit(1);
  \$items = RevIt_Publisher_Services::editorial_queue()->list_items(array('limit'=>20));
  if (count(\$items) < 1) exit(1);
  echo count(\$items) . ' queue items';
"

echo "==> Defer and complete"
docker compose run --rm wpcli wp eval "
  wp_set_current_user(1);
  \$items = RevIt_Publisher_Services::editorial_queue()->list_items(array('limit'=>1));
  \$id = (int)(\$items[0]['id'] ?? 0);
  RevIt_Publisher_Services::editorial_queue()->update_item(\$id, array('status'=>'deferred','deferred_until'=>gmdate('Y-m-d', strtotime('+7 days'))));
  RevIt_Publisher_Services::editorial_queue()->update_item(\$id, array('status'=>'completed'));
  echo 'Lifecycle OK';
"

echo "==> System health"
docker compose run --rm wpcli wp eval "
  wp_set_current_user(1);
  \$health = RevIt_Publisher_Services::system_health()->get_diagnostics();
  if (empty(\$health['checks'])) exit(1);
  echo 'Health OK';
"

echo "==> Backup export privacy"
docker compose run --rm wpcli wp eval "
  \$backup = RevIt_Publisher_Services::backup()->export();
  \$json = wp_json_encode(\$backup);
  if (str_contains(\$json, 'access_token')) exit(1);
  echo 'Backup OK';
"

echo "==> Migration idempotency"
docker compose run --rm wpcli wp eval "
  \$a = RevIt_Publisher_Services::migrations()->maybe_upgrade();
  \$b = RevIt_Publisher_Services::migrations()->maybe_upgrade();
  if (empty(\$a['success']) || empty(\$b['success'])) exit(1);
  echo 'Migration OK';
"

echo "==> Package build"
npm run build
bash scripts/package-plugin.sh
ZIP="$(ls build/revit-publisher-v*.zip | tail -1)"
bash scripts/validate-package.sh "$ZIP"

echo "Phase 7 acceptance passed."
