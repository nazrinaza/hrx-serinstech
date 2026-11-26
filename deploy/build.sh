#!/usr/bin/env bash
# Builds a ready-to-upload zip for cPanel: project code + production vendor/ + compiled CSS/JS.
# The server then needs no Composer or Node. Run from anywhere:  bash deploy/build.sh
#
# Only files git tracks (or new files it doesn't ignore) go in, so .env, the local
# SQLite file, uploads and node_modules can never leak into the package.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

SHA="$(git rev-parse --short HEAD)"
[[ -n "$(git status --porcelain)" ]] && SHA="$SHA-dirty"
STAGE="$(mktemp -d)/hrx"
OUT="$ROOT/dist/hrx-serinstech-$SHA.zip"
mkdir -p "$STAGE" "$ROOT/dist"

echo "→ Copying project files ($SHA)"
git ls-files -z --cached --others --exclude-standard | while IFS= read -r -d '' f; do
  if [[ -e "$f" ]]; then mkdir -p "$STAGE/$(dirname "$f")"; cp -p "$f" "$STAGE/$f"; fi
done

echo "→ Installing production PHP packages (no dev tools)"
composer install --working-dir="$STAGE" --no-dev --optimize-autoloader --no-interaction --no-progress --quiet

echo "→ Building CSS/JS"
npm ci --silent
npm run build --silent
rm -rf "$STAGE/public/build" && cp -R public/build "$STAGE/public/build"

# Not needed on the server.
rm -rf "$STAGE/tests" "$STAGE/node_modules" "$STAGE/.github" "$STAGE/phpunit.xml" \
       "$STAGE/database/database.sqlite" "$STAGE/CLAUDE.md" "$STAGE/AGENTS.md"

echo "→ Zipping"
rm -f "$OUT"
(cd "$(dirname "$STAGE")" && zip -qr -X "$OUT" hrx)
rm -rf "$(dirname "$STAGE")"

echo "✓ $OUT ($(du -h "$OUT" | cut -f1))"
