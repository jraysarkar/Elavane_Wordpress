#!/usr/bin/env bash
# Server-side deploy for Elvane's versioned code.
#
# Installed root-owned at /usr/local/bin/elvane-deploy and run by the
# `deploy` user through a single sudoers rule. GitHub Actions uploads the
# repository to $INCOMING and then calls: sudo /usr/local/bin/elvane-deploy <env>
#
# Only Elvane-owned paths are touched. Third-party plugins, uploads and the
# database are never modified. The previous version of every path is kept
# under $BACKUPS so a deploy can be rolled back by copying it back.

set -euo pipefail

ENVIRONMENT="${1:-}"
WP_ROOT="${WP_ROOT:-/var/www/wordpress}"
INCOMING="/home/deploy/incoming"
BACKUPS="/var/backups/elvane-deploy"
WEB_USER="www-data"
KEEP_BACKUPS=10

if [[ "$ENVIRONMENT" != "dev" && "$ENVIRONMENT" != "live" ]]; then
	echo "usage: elvane-deploy dev|live" >&2
	exit 2
fi

WP_CONTENT="$WP_ROOT/wp-content"
SRC="$INCOMING/wp-content"
STAMP="$(date +%Y%m%d-%H%M%S)"
BACKUP_DIR="$BACKUPS/$STAMP"

[[ -d "$SRC" ]] || { echo "nothing uploaded to $INCOMING" >&2; exit 1; }

echo "==> PHP syntax check"
find "$INCOMING" -name '*.php' -print0 | xargs -0 -n1 php -l >/dev/null

mkdir -p "$BACKUP_DIR"

# sync_dir <relative path under wp-content>: mirror one Elvane-owned directory.
sync_dir() {
	local rel="$1"
	if [[ -d "$WP_CONTENT/$rel" ]]; then
		mkdir -p "$BACKUP_DIR/$(dirname "$rel")"
		cp -a "$WP_CONTENT/$rel" "$BACKUP_DIR/$rel"
	fi
	mkdir -p "$WP_CONTENT/$rel"
	rsync -a --delete "$SRC/$rel/" "$WP_CONTENT/$rel/"
	chown -R "$WEB_USER:$WEB_USER" "$WP_CONTENT/$rel"
	echo "    synced $rel"
}

# sync_files <src dir> <dest dir>: add/replace individual files, never delete.
sync_files() {
	local from="$1" to="$2"
	[[ -d "$from" ]] || return 0
	mkdir -p "$to" "$BACKUP_DIR/mu-plugins"
	cp -a "$to/." "$BACKUP_DIR/mu-plugins/" 2>/dev/null || true
	rsync -a --exclude 'README.md' "$from/" "$to/"
	chown -R "$WEB_USER:$WEB_USER" "$to"
	echo "    synced files from ${from#$INCOMING/}"
}

echo "==> Deploying to $ENVIRONMENT ($WP_ROOT)"
sync_dir "themes/innovecouture-child"
for plugin in "$SRC"/plugins/elvane-*/; do
	[[ -d "$plugin" ]] && sync_dir "plugins/$(basename "$plugin")"
done
sync_files "$SRC/mu-plugins" "$WP_CONTENT/mu-plugins"

if [[ "$ENVIRONMENT" == "dev" ]]; then
	sync_files "$INCOMING/ops/dev-only/mu-plugins" "$WP_CONTENT/mu-plugins"
else
	# Safety net: dev-only helpers must never run on live.
	for f in "$INCOMING"/ops/dev-only/mu-plugins/*.php; do
		rm -f "$WP_CONTENT/mu-plugins/$(basename "$f")"
	done
fi

echo "==> Flushing caches"
sudo -u "$WEB_USER" wp --path="$WP_ROOT" cache flush --quiet || true
sudo -u "$WEB_USER" wp --path="$WP_ROOT" elementor flush-css --quiet 2>/dev/null || true

echo "==> Pruning old backups (keeping $KEEP_BACKUPS)"
ls -1dt "$BACKUPS"/*/ 2>/dev/null | tail -n +$((KEEP_BACKUPS + 1)) | xargs -r rm -rf

rm -rf "$INCOMING"
echo "==> Done. Previous version saved in $BACKUP_DIR"
