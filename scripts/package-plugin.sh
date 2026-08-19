#!/usr/bin/env bash
# Package RevIt Publisher for production deployment.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
VERSION="$(grep REVIT_PUBLISHER_VERSION "$ROOT/revit-publisher.php" | head -1 | cut -d"'" -f4)"
OUT="$ROOT/build/revit-publisher-v${VERSION}.zip"

cd "$ROOT"
npm run build
if [ ! -d vendor ]; then
  composer install --no-dev --optimize-autoloader --no-interaction
fi

rm -rf build/pkg
mkdir -p build/pkg/revit-publisher

rsync -a \
  --exclude node_modules \
  --exclude tests \
  --exclude docker-compose.yml \
  --exclude .git \
  --exclude .env \
  --exclude build \
  --exclude scripts/acceptance-test*.sh \
  "$ROOT/" build/pkg/revit-publisher/

mkdir -p build
rm -f "$OUT"
(cd build/pkg && zip -rq "$OUT" revit-publisher)

echo "Created $OUT"
