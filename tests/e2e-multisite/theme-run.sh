#!/usr/bin/env bash
set -euo pipefail

: "${WP_PATH:?WP_PATH must point to the WordPress installation}"
: "${REPO_ROOT:?REPO_ROOT must point to the checked-out repository}"

WP=(wp --path="$WP_PATH" --user=1 --url=http://example.test)
SLUG="wp-ability-e2e-theme"
BUILD_DIR="$(mktemp -d)"
WWW_DIR="$BUILD_DIR/www"
MU_DIR="$WP_PATH/wp-content/mu-plugins"
PORT=8451

cleanup_theme_e2e() {
  if [[ -n "${THEME_SERVER_PID:-}" ]]; then
    kill "$THEME_SERVER_PID" >/dev/null 2>&1 || true
  fi
  rm -rf "$BUILD_DIR"
  rm -f "$MU_DIR/wp-ability-theme-e2e-http.php"
}
trap cleanup_theme_e2e EXIT

mkdir -p "$WWW_DIR" "$MU_DIR"

build_theme() {
  local version="$1"
  local source="$REPO_ROOT/tests/e2e/theme-fixture-v$version"
  local staging="$BUILD_DIR/v$version/$SLUG"
  mkdir -p "$staging"
  cp -R "$source/." "$staging/"
  (
    cd "$BUILD_DIR/v$version"
    zip -qr "$WWW_DIR/theme-v$version.zip" "$SLUG"
  )
}

build_theme 1
build_theme 2
build_theme 3

cat > "$MU_DIR/wp-ability-theme-e2e-http.php" <<'PHP'
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
			$ports[] = 8451;
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
THEME_SERVER_PID=$!

for _ in $(seq 1 20); do
  if curl -kfsS "https://127.0.0.1:$PORT/theme-v1.zip" -o /dev/null; then
    break
  fi
  sleep 0.5
done

PACKAGE_V1="https://127.0.0.1:$PORT/theme-v1.zip"
PACKAGE_V2="https://127.0.0.1:$PORT/theme-v2.zip"
PACKAGE_V3="https://127.0.0.1:$PORT/theme-v3.zip"

"${WP[@]}" eval-file "$REPO_ROOT/tests/e2e/theme-assert-flow.php" ability
"${WP[@]}" eval-file "$REPO_ROOT/tests/e2e/theme-assert-flow.php" remember-fallback
"${WP[@]}" eval-file "$REPO_ROOT/tests/e2e/theme-assert-flow.php" install "$PACKAGE_V1" true
"${WP[@]}" eval-file "$REPO_ROOT/tests/e2e/theme-assert-flow.php" assert 1.0.0 active
"${WP[@]}" eval-file "$REPO_ROOT/tests/e2e/theme-assert-flow.php" overwrite "$PACKAGE_V2"
"${WP[@]}" eval-file "$REPO_ROOT/tests/e2e/theme-assert-flow.php" assert 2.0.0 active
"${WP[@]}" eval-file "$REPO_ROOT/tests/e2e/theme-assert-flow.php" prime-update "$PACKAGE_V3" 3.0.0
"${WP[@]}" eval-file "$REPO_ROOT/tests/e2e/theme-assert-flow.php" update
"${WP[@]}" eval-file "$REPO_ROOT/tests/e2e/theme-assert-flow.php" assert 3.0.0 active
"${WP[@]}" eval-file "$REPO_ROOT/tests/e2e/theme-assert-flow.php" switch-back
"${WP[@]}" eval-file "$REPO_ROOT/tests/e2e/theme-assert-flow.php" delete
"${WP[@]}" eval-file "$REPO_ROOT/tests/e2e/theme-assert-flow.php" assert-absent

echo "PASS: complete Multisite theme management E2E flow"
