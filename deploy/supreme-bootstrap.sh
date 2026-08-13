#!/usr/bin/env bash
set -euo pipefail

cd /var/www/html

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

"${wp_cmd[@]}" plugin activate woocommerce supreme-autoparts-core >/dev/null
"${wp_cmd[@]}" theme activate supreme-autoparts >/dev/null

# Routing is deployment configuration, not one-time catalog state. Enforce it
# on every release so an interrupted bootstrap cannot leave query-string URLs.
"${wp_cmd[@]}" option update permalink_structure '/%postname%/' >/dev/null
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

if [[ "$("${wp_cmd[@]}" option get sa_bootstrap_complete 2>/dev/null || true)" != "1" ]]; then
  "${wp_cmd[@]}" supreme catalog setup
  "${wp_cmd[@]}" option update sa_bootstrap_complete 1
fi

echo "Supreme bootstrap: WordPress, WooCommerce, theme, and store pages are ready."
