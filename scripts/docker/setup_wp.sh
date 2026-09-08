#!/usr/bin/env bash
# Boots the local WordPress (Divi 5 + Beaver Builder Lite + both converter
# plugins), seeds a fixture page and converts it. Idempotent.
set -euo pipefail
ROOT=$(cd "$(dirname "$0")/../../" && pwd)
WP_URL=http://localhost:8010
ADMIN_USER=admin
ADMIN_PASS=admin
ADMIN_EMAIL=admin@example.test

for f in Divi.zip beaver-builder-lite-version.2.10.3.2.zip; do
  if [ ! -f "$ROOT/references/$f" ]; then
    echo "Missing references/$f — see references/README.md" >&2
    exit 1
  fi
done

cd "$ROOT"
echo "Starting Docker environment..."
docker compose up -d

echo "Waiting for WordPress to answer..."
until curl -sSf "$WP_URL" >/dev/null 2>&1 || curl -sS -o /dev/null -w '%{http_code}' "$WP_URL" 2>/dev/null | grep -qE '^(200|302)$'; do
  printf '.'; sleep 2
done
echo

WP=$(docker compose ps -q wordpress)
run() { docker exec -i "$WP" bash -lc "$1"; }

echo "Installing WP-CLI inside the container..."
run "command -v wp >/dev/null 2>&1 || (curl -sS https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar -o /usr/local/bin/wp && chmod +x /usr/local/bin/wp)"
run "command -v unzip >/dev/null 2>&1 || (apt-get update -qq >/dev/null && apt-get install -y -qq unzip >/dev/null)"

echo "Installing WordPress core..."
run "wp core is-installed --allow-root || wp core install --url=$WP_URL --title='Beaver to Divi 5' --admin_user=$ADMIN_USER --admin_password=$ADMIN_PASS --admin_email=$ADMIN_EMAIL --skip-email --allow-root"
# Re-assert the password: long-lived containers drift, and the e2e specs hard-code it.
run "wp user update 1 --user_pass=$ADMIN_PASS --allow-root >/dev/null"
run "wp option update permalink_structure '/%postname%/' --allow-root >/dev/null"

echo "Installing Divi 5..."
# --force so a newer references/Divi.zip replaces an older installed Divi.
run "wp theme install /tmp/Divi.zip --force --allow-root"
run "wp theme activate Divi --allow-root"

echo "Installing Beaver Builder Lite..."
run "wp plugin is-installed beaver-builder-lite-version --allow-root || wp plugin install /tmp/beaver-builder-lite.zip --allow-root"
run "wp plugin activate beaver-builder-lite-version --allow-root"

echo "Activating the converter plugins..."
run "wp plugin activate jhmg-converter-for-beaver-builder-to-divi --allow-root"
run "wp plugin activate jhmg-converter-for-beaver-builder-to-divi-pro --allow-root || true"

echo "Copying WP-CLI helper scripts..."
for s in set-beaver-data.php import-bb-template.php convert-run.php convert-to-new-page.php; do
  docker cp "$ROOT/scripts/docker/$s" "$WP:/tmp/$s"
done

echo "Seeding and converting a fixture page (bundled 'contact' layout)..."
PAGE_ID=$(run "wp post create --post_type=page --post_status=publish --post_title='Contact (Beaver Builder)' --porcelain --allow-root")
run "FIXTURE=beaver-templates/contact PAGE_ID=$PAGE_ID wp eval-file /tmp/set-beaver-data.php --allow-root"
NEW_ID=$(run "SOURCE_PAGE_ID=$PAGE_ID wp eval-file /tmp/convert-to-new-page.php --allow-root")

echo
echo "Setup complete."
echo "  Site:        $WP_URL  (admin: $ADMIN_USER / $ADMIN_PASS)"
echo "  Converter:   $WP_URL/wp-admin/tools.php?page=bdc-converter"
echo "  Source page: $WP_URL/?page_id=$PAGE_ID   →   converted: $WP_URL/?page_id=$NEW_ID"
echo "  WordPress $(run 'wp core version --allow-root') · Divi $(run "wp theme get Divi --field=version --allow-root") · Beaver Builder $(run "wp plugin get beaver-builder-lite-version --field=version --allow-root")"
