#!/usr/bin/env bash
# Validate production plugin package.
set -euo pipefail

ZIP="${1:-}"
if [ -z "$ZIP" ]; then
  echo "Usage: $0 path/to/revit-publisher-vX.zip"
  exit 1
fi

TMP="$(mktemp -d)"
unzip -q "$ZIP" -d "$TMP"

PLUGIN="$(dirname "$(find "$TMP" -name revit-publisher.php | head -1)")"

test -f "$PLUGIN/revit-publisher.php"
test -f "$PLUGIN/vendor/autoload.php"
test -f "$PLUGIN/admin/dist/assets/main.js"
test -f "$PLUGIN/schemas/revit-article-v1.schema.json"
test -f "$PLUGIN/schemas/revit-publisher-backup-v1.schema.json"
test ! -f "$PLUGIN/.env"
test ! -d "$PLUGIN/.git"
test ! -d "$PLUGIN/node_modules"

if grep -R "client_secret\|access_token\|refresh_token" "$PLUGIN" --include="*.json" 2>/dev/null; then
  echo "FAIL: credential strings found in package"
  exit 1
fi

echo "Package validation passed: $ZIP"
rm -rf "$TMP"
