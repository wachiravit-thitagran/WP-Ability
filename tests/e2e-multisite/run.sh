#!/usr/bin/env bash
set -euo pipefail

: "${WP_PATH:?WP_PATH must point to the WordPress installation}"
: "${REPO_ROOT:?REPO_ROOT must point to the checked-out repository}"

WP=(wp --path="$WP_PATH" --user=1 --url=http://example.test)
FIXTURE_SLUG="wp-ability-e2e-fixture"
BUILD_DIR="$(mktemp -d)"
WWW_DIR="$BUILD_DIR/www"
MU_DIR="$WP_PATH/wp-content/mu-plugins"
PORT=8444

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
add_filter(
	'http_request_host_is_external',
	static function ( $external, $host ) {
		return in_array( $host, array( '127.0.0.1', 'localhost' ), true ) ? true : $external;
	},
	10,
	2
);
add_filter(
	'http_allowed_safe_ports',
	static function ( $ports, $host ) {
		if ( in_array( $host, array( '127.0.0.1', 'localhost' ), true ) ) {
			$ports[] = 8444;
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
		return in_array( $host, array( '127.0.0.1', 'localhost' ), true ) ? false : $verify;
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

PACKAGE_V1="https://127.0.0.1:$PORT/fixture-v1.zip"
PACKAGE_V2="https://127.0.0.1:$PORT/fixture-v2.zip"

"${WP[@]}" eval-file "$REPO_ROOT/tests/e2e-multisite/assert-flow.php" ability
"${WP[@]}" eval-file "$REPO_ROOT/tests/e2e-multisite/assert-flow.php" install "$PACKAGE_V1"
"${WP[@]}" eval-file "$REPO_ROOT/tests/e2e-multisite/assert-flow.php" assert-version 1.0.0
"${WP[@]}" eval-file "$REPO_ROOT/tests/e2e-multisite/assert-flow.php" network-activate
"${WP[@]}" eval-file "$REPO_ROOT/tests/e2e-multisite/assert-flow.php" assert-network true
"${WP[@]}" eval-file "$REPO_ROOT/tests/e2e-multisite/assert-flow.php" network-deactivate
"${WP[@]}" eval-file "$REPO_ROOT/tests/e2e-multisite/assert-flow.php" assert-network false
"${WP[@]}" eval-file "$REPO_ROOT/tests/e2e-multisite/assert-flow.php" prime-update "$PACKAGE_V2"
"${WP[@]}" eval-file "$REPO_ROOT/tests/e2e-multisite/assert-flow.php" update
"${WP[@]}" eval-file "$REPO_ROOT/tests/e2e-multisite/assert-flow.php" assert-version 2.0.0
"${WP[@]}" eval-file "$REPO_ROOT/tests/e2e-multisite/assert-flow.php" delete
"${WP[@]}" eval-file "$REPO_ROOT/tests/e2e-multisite/assert-flow.php" assert-absent

echo "PASS: complete Multisite plugin management E2E flow"
