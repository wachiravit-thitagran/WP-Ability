<?php
/**
 * PHPUnit bootstrap.
 *
 * @package WP_Ability
 */

$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	printf( "Could not find WordPress test library at %s.\\n", esc_html( $_tests_dir ) );
	exit( 1 );
}

require_once $_tests_dir . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	static function () {
		require dirname( __DIR__ ) . '/wp-ability.php';
	}
);

require $_tests_dir . '/includes/bootstrap.php';
