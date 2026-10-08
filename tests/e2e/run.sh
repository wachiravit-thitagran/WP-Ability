#!/usr/bin/env bash
set -euo pipefail

: "${WP_PATH:?WP_PATH must point to the WordPress installation}"
: "${REPO_ROOT:?REPO_ROOT must point to the checked-out repository}"

command -v wp >/dev/null 2>&1 || { echo "wp-cli is required" >&2; exit 2; }
command -v zip >/dev/null 2>&1 || { echo "zip is required" >&2; exit 2; }
command -v openssl >/dev/null 2>&1 || { echo "openssl is required" >&2; exit 2; }
command -v curl >/dev/null 2>&1 || { echo "curl is required" >&2; exit 2; }

WP=(wp --path="$WP_PATH" --user=1)
FIXTURE_SLUG="wp-ability-e2e-fixture"
PLUGIN_FILE="$FIXTURE_SLUG/fixture-plugin.php"
BUILD_DIR="$(mktemp -d)"
WWW_DIR="$BUILD_DIR/www"
MU_DIR="$WP_PATH/wp-content/mu-plugins"
PORT=8443

cleanup() {
  if [[ -n "${SERVER_PID:-}" ]]; then
    kill "$SERVER_PID" >/dev/null 2>&1 || true
  fi
  rm -rf "$BUILD_DIR"
  rm -f "$MU_DIR/wp-ability-e2e-http.php"
}
trap cleanup EXIT

mkdir -p "$WWW_DIR" "$MU_DIR"

build_fixture() {
  local version="$1"
  local source="$REPO_ROOT/tests/e2e/fixture-v$version/fixture-plugin.php"
  local staging="$BUILD_DIR/v$version/$FIXTURE_SLUG"
  mkdir -p "$staging"
  cp "$source" "$staging/fixture-plugin.php"
  (
    cd "$BUILD_DIR/v$version"
    zip -qr "$WWW_DIR/fixture-v$version.zip" "$FIXTURE_SLUG"
  )
}

build_fixture 1
build_fixture 2

cat > "$MU_DIR/wp-ability-e2e-http.php" <<'PHP'
<?php
/**
 * E2E-only HTTP policy overrides for the local TLS fixture server.
 */
add_filter(
	'http_request_host_is_external',
	static function ( $external, $host ) {
		if ( in_array( $host, array( '127.0.0.1', 'localhost' ), true ) ) {
			return true;
		}
		return $external;
	},
	10,
	2
);

add_filter(
	'http_allowed_safe_ports',
	static function ( $ports, $host ) {
		if ( in_array( $host, array( '127.0.0.1', 'localhost' ), true ) ) {
			$ports[] = 8443;
		}
		return array_values( array_unique( $ports ) );
	},
	10,
	2
);

add_filter(
	'https_ssl_verify',
	static function ( $verify, $url ) {
		$host = wp_parse_url( $url, PHP_URL_HOST );
		if ( in_array( $host, array( '127.0.0.1', 'localhost' ), true ) ) {
			return false;
		}
		return $verify;
	},
	10,
	2
);
PHP

openssl req -x509 -newkey rsa:2048 -nodes   -keyout "$BUILD_DIR/key.pem"   -out "$BUILD_DIR/cert.pem"   -days 1   -subj "/CN=localhost"   -addext "subjectAltName=DNS:localhost,IP:127.0.0.1" >/dev/null 2>&1

(
  cd "$WWW_DIR"
  exec openssl s_server -quiet -WWW -accept "$PORT"     -cert "$BUILD_DIR/cert.pem"     -key "$BUILD_DIR/key.pem"
) >"$BUILD_DIR/server.log" 2>&1 &
SERVER_PID=$!

for _ in $(seq 1 20); do
  if curl -kfsS "https://127.0.0.1:$PORT/fixture-v1.zip" -o /dev/null; then
    break
  fi
  sleep 0.5
done
curl -kfsS "https://127.0.0.1:$PORT/fixture-v1.zip" -o /dev/null

"${WP[@]}" plugin is-active wordpress-abilities-bridge
"${WP[@]}" eval-file "$REPO_ROOT/tests/e2e/assert-flow.php" ability
"${WP[@]}" eval-file "$REPO_ROOT/tests/e2e/assert-flow.php" discovery

PACKAGE_V1="https://127.0.0.1:$PORT/fixture-v1.zip"
PACKAGE_V2="https://127.0.0.1:$PORT/fixture-v2.zip"
SHA_V1="$(sha256sum "$WWW_DIR/fixture-v1.zip" | awk '{print $1}')"

"${WP[@]}" eval-file "$REPO_ROOT/tests/e2e/assert-flow.php" install-checksum-mismatch "$PACKAGE_V1"
"${WP[@]}" eval-file "$REPO_ROOT/tests/e2e/assert-flow.php" install "$PACKAGE_V1" false true "$SHA_V1"
"${WP[@]}" eval-file "$REPO_ROOT/tests/e2e/assert-flow.php" assert-version 1.0.0 active
"${WP[@]}" eval-file "$REPO_ROOT/tests/e2e/assert-flow.php" assert-inventory 1.0.0 active false

"${WP[@]}" eval-file "$REPO_ROOT/tests/e2e/assert-flow.php" auto-update true
"${WP[@]}" eval-file "$REPO_ROOT/tests/e2e/assert-flow.php" assert-inventory 1.0.0 active true
"${WP[@]}" eval-file "$REPO_ROOT/tests/e2e/assert-flow.php" auto-update false
"${WP[@]}" eval-file "$REPO_ROOT/tests/e2e/assert-flow.php" assert-inventory 1.0.0 active false

"${WP[@]}" eval-file "$REPO_ROOT/tests/e2e/assert-flow.php" prime-update "$PACKAGE_V2"
"${WP[@]}" eval-file "$REPO_ROOT/tests/e2e/assert-flow.php" check-update
"${WP[@]}" eval-file "$REPO_ROOT/tests/e2e/assert-flow.php" update
"${WP[@]}" eval-file "$REPO_ROOT/tests/e2e/assert-flow.php" assert-version 2.0.0 active
"${WP[@]}" eval-file "$REPO_ROOT/tests/e2e/assert-flow.php" assert-inventory 2.0.0 active false

"${WP[@]}" eval-file "$REPO_ROOT/tests/e2e/assert-flow.php" deactivate
"${WP[@]}" eval-file "$REPO_ROOT/tests/e2e/assert-flow.php" assert-version 2.0.0 inactive

"${WP[@]}" eval-file "$REPO_ROOT/tests/e2e/assert-flow.php" delete
"${WP[@]}" eval-file "$REPO_ROOT/tests/e2e/assert-flow.php" assert-absent

bash "$REPO_ROOT/tests/e2e/theme-run.sh"

echo "PASS: complete single-site plugin and theme management E2E flow"
