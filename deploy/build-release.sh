#!/usr/bin/env bash
# Builds a ready-to-upload ZIP (includes vendor/) for hosts where you cannot run Composer.
# Usage: bash deploy/build-release.sh   → creates spims-release-YYYYmmdd.zip in the project root
set -euo pipefail
cd "$(dirname "$0")/.."
OUT="spims-release-$(date +%Y%m%d).zip"
TMP="$(mktemp -d)"
git archive --format=tar HEAD | tar -x -C "$TMP"
( cd "$TMP" && composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist )
rm -rf "$TMP/tests" "$TMP/phpunit.xml" "$TMP/.github"
( cd "$TMP" && zip -qr "$OLDPWD/$OUT" . -x ".env" )
rm -rf "$TMP"
echo "Created $OUT — upload and extract it, then follow docs/INSTALLATION_HOSTINGER.md"
