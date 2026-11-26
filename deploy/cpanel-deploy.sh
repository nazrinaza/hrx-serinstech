#!/bin/bash
# Run by cPanel → Git™ Version Control → "Deploy HEAD Commit" (via .cpanel.yml).
# Copies the code from the Git clone (~/repositories/hrx-serinstech) to the live folder (~/hrx),
# installs PHP packages and updates the database. The live .env and uploaded files are never touched.
#
# Override the defaults with environment variables if needed:
#   HRX_LIVE   live folder            (default: ~/hrx)
#   HRX_PHP    PHP 8.3+ binary         (default: auto-detect)
#   HRX_COMPOSER composer command      (default: composer in PATH, else ~/composer.phar)
set -euo pipefail

SRC="$(cd "$(dirname "$0")/.." && pwd)"
LIVE="${HRX_LIVE:-$HOME/hrx}"
log() { echo "[hrx-deploy] $*"; }

# --- Find PHP 8.3+ (cPanel's CLI `php` is often an older version than the website's).
find_php() {
  local c
  for c in "${HRX_PHP:-}" /opt/cpanel/ea-php83/root/usr/bin/php /opt/cpanel/ea-php84/root/usr/bin/php \
           /opt/alt/php83/usr/bin/php /opt/alt/php84/usr/bin/php php; do
    [[ -z "$c" ]] && continue
    command -v "$c" >/dev/null 2>&1 || continue
    if "$c" -r 'exit(PHP_VERSION_ID >= 80300 ? 0 : 1);' 2>/dev/null; then echo "$c"; return 0; fi
  done
  return 1
}
PHP="$(find_php)" || { log "ERROR: PHP 8.3 or newer not found. Set HRX_PHP in .cpanel.yml."; exit 1; }
log "PHP: $PHP ($("$PHP" -r 'echo PHP_VERSION;'))"

# --- Find Composer. Put the chosen PHP first in PATH so `composer` (a `#!/usr/bin/env php` script) uses it.
[[ "$PHP" == */* ]] && export PATH="$(dirname "$PHP"):$PATH"
if [[ -n "${HRX_COMPOSER:-}" ]]; then COMPOSER=($HRX_COMPOSER)
elif [[ -f "$HOME/composer.phar" ]]; then COMPOSER=("$PHP" "$HOME/composer.phar")
elif command -v composer >/dev/null 2>&1; then COMPOSER=(composer)
else log "ERROR: Composer not found. See 'Install Composer' in deploy/CPANEL-GUIDE.md."; exit 1
fi

[[ -f "$SRC/public/build/manifest.json" ]] || { log "ERROR: public/build is missing. Run 'npm run build' and commit it."; exit 1; }

# --- Put the live site in maintenance mode while files change (only once it is installed).
INSTALLED=0
[[ -f "$LIVE/.env" && -f "$LIVE/vendor/autoload.php" ]] && INSTALLED=1
if [[ $INSTALLED == 1 ]]; then
  "$PHP" "$LIVE/artisan" down --retry=15 >/dev/null 2>&1 || true
  trap '"$PHP" "$LIVE/artisan" up >/dev/null 2>&1 || true' EXIT
fi

# --- Copy code. --delete removes files that were deleted from git, but never the excluded paths.
log "Copying code to $LIVE"
mkdir -p "$LIVE"
rsync -a --delete \
  --exclude='/.git/' --exclude='/.env' --exclude='/vendor/' --exclude='/storage/' \
  --exclude='/public/storage' --exclude='/bootstrap/cache/*.php' --exclude='/node_modules/' \
  --exclude='/tests/' --exclude='/database/database.sqlite' \
  "$SRC/" "$LIVE/"
# Storage folder skeleton on first deploy; never overwrites logs or uploaded files.
rsync -a --ignore-existing "$SRC/storage/" "$LIVE/storage/"

cd "$LIVE"
log "Installing PHP packages"
"${COMPOSER[@]}" install --no-dev --optimize-autoloader --no-interaction --no-progress 2>&1 | tail -n 3

if [[ ! -f .env ]]; then
  log "First deploy: code is in place. Next: create $LIVE/.env (see the guide), then finish the install in Terminal."
  exit 0
fi

log "Updating database and caches"
"$PHP" artisan migrate --force
"$PHP" artisan optimize
[[ -e public/storage ]] || "$PHP" artisan storage:link

log "Done: $(cd "$SRC" && git log -1 --format='%h %s' 2>/dev/null || echo 'deployed')"
