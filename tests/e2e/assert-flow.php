<?php
/**
 * Real WordPress E2E driver and assertions.
 *
 * Invoked through WP-CLI eval-file so WordPress, registered abilities,
 * permissions, and Plugin_Upgrader are all real.
 */

defined( 'ABSPATH' ) || exit;

$command     = isset( $args[0] ) ? $args[0] : '';
$plugin_file = 'wp-ability-e2e-fixture/fixture-plugin.php';

/**
 * Resolve a registered ability or terminate the E2E run.
 *
 * @param string $name Ability name.
 * @return WP_Ability
 */
function wp_ability_e2e_ability( $name ) {
	$ability = wp_get_ability( $name );

	if ( ! $ability ) {
		WP_CLI::error( 'Ability is not registered: ' . $name );
	}

	return $ability;
}

/**
 * Fail if an ability returned WP_Error.
 *
 * @param mixed  $result Result.
 * @param string $step   Step name.
 * @return mixed
 */
function wp_ability_e2e_require_success( $result, $step ) {
	if ( is_wp_error( $result ) ) {
		WP_CLI::error( $step . ' failed [' . $result->get_error_code() . ']: ' . $result->get_error_message() );
	}

	return $result;
}

switch ( $command ) {
	case 'ability':
		wp_ability_e2e_ability( 'wordpress/plugin-install-package' );
		WP_CLI::success( 'plugin-install-package ability is registered.' );
		break;

	case 'install':
		$url       = isset( $args[1] ) ? $args[1] : '';
		$overwrite = isset( $args[2] ) && 'true' === $args[2];
		$activate  = isset( $args[3] ) && 'true' === $args[3];
		$result    = wp_ability_e2e_ability( 'wordpress/plugin-install-package' )->execute(
			array(
				'package_url' => $url,
				'overwrite'   => $overwrite,
				'activate'    => $activate,
			)
		);
		$result    = wp_ability_e2e_require_success( $result, 'Package install' );

		if ( empty( $result['installed'] ) || $plugin_file !== $result['plugin_file'] ) {
			WP_CLI::error( 'Package install returned an unexpected result: ' . wp_json_encode( $result ) );
		}

		WP_CLI::log( wp_json_encode( $result ) );
		break;

	case 'assert-version':
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		$expected = isset( $args[1] ) ? $args[1] : '';
		$state    = isset( $args[2] ) ? $args[2] : 'inactive';
		$plugins  = get_plugins();

		if ( ! isset( $plugins[ $plugin_file ] ) ) {
			WP_CLI::error( 'Fixture plugin is not installed.' );
		}

		if ( $expected !== $plugins[ $plugin_file ]['Version'] ) {
			WP_CLI::error( 'Expected fixture version ' . $expected . ', got ' . $plugins[ $plugin_file ]['Version'] . '.' );
		}

		$should_be_active = 'active' === $state;
		if ( $should_be_active !== is_plugin_active( $plugin_file ) ) {
			WP_CLI::error( 'Fixture activation state did not match ' . $state . '.' );
		}

		WP_CLI::success( 'Fixture is version ' . $expected . ' and ' . $state . '.' );
		break;

	case 'deactivate':
		$result = wp_ability_e2e_ability( 'wordpress/plugin-deactivate' )->execute(
			array( 'plugin_file' => $plugin_file )
		);
		wp_ability_e2e_require_success( $result, 'Plugin deactivate' );
		WP_CLI::success( 'Fixture plugin deactivated through ability.' );
		break;

	case 'delete':
		$result = wp_ability_e2e_ability( 'wordpress/plugin-delete' )->execute(
			array( 'plugin_file' => $plugin_file )
		);
		wp_ability_e2e_require_success( $result, 'Plugin delete' );
		WP_CLI::success( 'Fixture plugin deleted through ability.' );
		break;

	case 'assert-absent':
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		$plugins = get_plugins();

		if ( isset( $plugins[ $plugin_file ] ) || is_dir( WP_PLUGIN_DIR . '/wp-ability-e2e-fixture' ) ) {
			WP_CLI::error( 'Fixture plugin still exists after delete.' );
		}

		WP_CLI::success( 'Fixture plugin is absent.' );
		break;

	default:
		WP_CLI::error( 'Unknown E2E command: ' . $command );
}
