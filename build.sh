#!/usr/bin/env bash
# Builds the installable archive: dist/injellikrecommendations-<version>.zip
# The archive contains a single "injellikrecommendations/" folder, as expected by the PrestaShop module manager.
set -euo pipefail
cd "$(dirname "$0")"

MODULE=injellikrecommendations
VERSION=$(sed -n "s/.*\$this->version = '\([^']*\)'.*/\1/p" "$MODULE/$MODULE.php")
OUT="dist/$MODULE-$VERSION.zip"

mkdir -p dist
rm -f "$OUT"
zip -qr "$OUT" "$MODULE" \
    -x "$MODULE/config*.xml" \
    -x "*/.DS_Store" \
    -x "*/Thumbs.db"

echo "$OUT"
