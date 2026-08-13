#!/usr/bin/env bash
set -euo pipefail

cd /var/www/html

bootstrap_status=/tmp/supreme-bootstrap-status.json
write_status() {
  printf '{"stage":"%s","ok":%s}\n' "$1" "$2" > "$bootstrap_status"
}
write_status "starting" false

# Apache and the official WordPress entrypoint create wp-config.php at runtime.
# Wait for both that file and MySQL before performing the idempotent site setup.
for attempt in $(seq 1 90); do
  export SUPREME_DB_ATTEMPT="$attempt"
  if [[ -f wp-config.php ]] && php -r '
    $host = getenv("WORDPRESS_DB_HOST") ?: "";
    $user = getenv("WORDPRESS_DB_USER") ?: "";
    $pass = getenv("WORDPRESS_DB_PASSWORD") ?: "";
    $name = getenv("WORDPRESS_DB_NAME") ?: "";
    mysqli_report(MYSQLI_REPORT_OFF);
    $db = @new mysqli($host, $user, $pass, $name);
    if ($db->connect_errno) {
      if (((int) getenv("SUPREME_DB_ATTEMPT")) % 15 === 0) {
        fwrite(STDERR, "Supreme bootstrap: database connection code " . $db->connect_errno . ".\n");
      }
      exit(1);
    }
    exit(0);
  '; then
    break
  fi
  if [[ "$attempt" == "90" ]]; then
    echo "Supreme bootstrap: database did not become ready within 180 seconds." >&2
    exit 1
  fi
  sleep 2
done

# Recover an interrupted first installation without deleting any data. Core
# tables may already exist while the required URL options are absent, a state
# in which WordPress intentionally blocks both HTTP and WP-CLI startup.
php -r '
  $host = (string) getenv("WORDPRESS_DB_HOST");
  $user = (string) getenv("WORDPRESS_DB_USER");
  $pass = (string) getenv("WORDPRESS_DB_PASSWORD");
  $name = (string) getenv("WORDPRESS_DB_NAME");
  $prefix = preg_replace("/[^A-Za-z0-9_]/", "", (string) (getenv("WORDPRESS_TABLE_PREFIX") ?: "wp_"));
  $url = (string) (getenv("WP_HOME") ?: (getenv("RAILWAY_PUBLIC_DOMAIN") ? "https://" . getenv("RAILWAY_PUBLIC_DOMAIN") : "http://localhost"));
  mysqli_report(MYSQLI_REPORT_OFF);
  $db = @new mysqli($host, $user, $pass, $name);
  if ($db->connect_errno) {
    exit(1);
  }
  foreach (["siteurl", "home"] as $option) {
    $sql = "INSERT INTO `{$prefix}options` (option_name, option_value, autoload) VALUES (?, ?, \"yes\") ON DUPLICATE KEY UPDATE option_value = IF(option_value = \"\", VALUES(option_value), option_value)";
    $statement = $db->prepare($sql);
    if (!$statement) {
      exit(1);
    }
    $statement->bind_param("ss", $option, $url);
    if (!$statement->execute()) {
      exit(1);
    }
    $statement->close();
  }
  $permalink = "/%postname%/";
  $sql = "INSERT INTO `{$prefix}options` (option_name, option_value, autoload) VALUES (\"permalink_structure\", ?, \"yes\") ON DUPLICATE KEY UPDATE option_value = VALUES(option_value)";
  $statement = $db->prepare($sql);
  if (!$statement) {
    exit(1);
  }
  $statement->bind_param("s", $permalink);
  if (!$statement->execute()) {
    exit(1);
  }
  $statement->close();
  $db->query("DELETE FROM `{$prefix}options` WHERE option_name = \"rewrite_rules\"");
  $db->close();
'

wp_cmd=(wp --allow-root --path=/var/www/html)

write_status "linting" false
write_status "lint-plugin-main" false
php -l /var/www/html/wp-content/plugins/supreme-autoparts-core/supreme-autoparts-core.php >/dev/null
for source_file in /var/www/html/wp-content/plugins/supreme-autoparts-core/includes/*.php /var/www/html/wp-content/themes/supreme-autoparts/*.php; do
  lint_name="$(basename "$source_file" .php | tr -cd 'A-Za-z0-9_-')"
  write_status "lint-${lint_name}" false
  php -l "$source_file" >/dev/null
done
write_status "install-check" false

if ! "${wp_cmd[@]}" core is-installed >/dev/null 2>&1; then
  : "${WP_ADMIN_USER:=supremeadmin}"
  : "${WP_ADMIN_EMAIL:=calvin@supremeautoparts.co.ke}"
  if [[ -z "${WP_ADMIN_PASSWORD:-}" ]]; then
    echo "Supreme bootstrap: WP_ADMIN_PASSWORD is required for first installation." >&2
    exit 1
  fi
  "${wp_cmd[@]}" core install \
    --url="${WP_HOME:-https://${RAILWAY_PUBLIC_DOMAIN:-localhost}}" \
    --title="${SA_NAME:-Supreme Autoparts}" \
    --admin_user="$WP_ADMIN_USER" \
    --admin_password="$WP_ADMIN_PASSWORD" \
    --admin_email="$WP_ADMIN_EMAIL" \
    --skip-email
fi

write_status "plugin-activation" false
"${wp_cmd[@]}" plugin activate woocommerce supreme-autoparts-core >/dev/null
write_status "theme-activation" false
"${wp_cmd[@]}" theme activate supreme-autoparts >/dev/null
write_status "routing" false

# Routing is deployment configuration, not one-time catalog state. Enforce it
# on every release so an interrupted bootstrap cannot leave query-string URLs.
"${wp_cmd[@]}" option update permalink_structure '/%postname%/' >/dev/null
"${wp_cmd[@]}" option update woocommerce_coming_soon 'no' >/dev/null
"${wp_cmd[@]}" option update woocommerce_store_pages_only 'no' >/dev/null
"${wp_cmd[@]}" option update fresh_site '0' >/dev/null
"${wp_cmd[@]}" rewrite flush --hard >/dev/null

# Keep the requested administrator identity in sync without embedding a secret.
admin_user="${WP_ADMIN_USER:-supremeadmin}"
if "${wp_cmd[@]}" user get "$admin_user" --field=ID >/dev/null 2>&1; then
  update_args=(user update "$admin_user" --user_email="${WP_ADMIN_EMAIL:-calvin@supremeautoparts.co.ke}")
  if [[ -n "${WP_ADMIN_PASSWORD:-}" ]]; then
    update_args+=(--user_pass="$WP_ADMIN_PASSWORD")
  fi
  "${wp_cmd[@]}" "${update_args[@]}" >/dev/null
fi

write_status "catalog-setup" false
"${wp_cmd[@]}" supreme catalog setup
"${wp_cmd[@]}" option update sa_bootstrap_complete 1

# Seed a bounded, production-safe first catalog. The full normalized inventory
# remains available for managed batch imports, while this ensures search,
# product pages, cart, and checkout are functional immediately.
write_status "catalog-import-seed" false
"${wp_cmd[@]}" supreme catalog import /opt/supreme/data/products.csv --limit=48 --status=publish

# Continue the full catalog asynchronously in bounded, idempotent batches.
# This keeps Railway startup and HTTP health independent from a long import.
(
  total_rows=17529
  batch_size=250
  offset="$("${wp_cmd[@]}" option get sa_catalog_import_offset 2>/dev/null || echo 0)"
  [[ "$offset" =~ ^[0-9]+$ ]] || offset=0
  while (( offset < total_rows )); do
    write_status "catalog-import-${offset}" false
    "${wp_cmd[@]}" supreme catalog import /opt/supreme/data/products.csv --offset="$offset" --limit="$batch_size" --status=publish
    offset=$((offset + batch_size))
    if (( offset > total_rows )); then offset=$total_rows; fi
    "${wp_cmd[@]}" option update sa_catalog_import_offset "$offset" >/dev/null
    sleep 2
  done
  write_status "complete" true
) &

write_status "complete" true
echo "Supreme bootstrap: WordPress, WooCommerce, theme, and store pages are ready."
