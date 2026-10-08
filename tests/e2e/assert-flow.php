<?php
/**
 * Real WordPress E2E driver and assertions.
 *
 * Invoked through WP-CLI eval-file so WordPress, registered abilities,
 * permissions, and Plugin_Upgrader are all real.
 *
 * @package WP_Ability
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
		foreach (
			array(
				'wordpress/plugin-list',
				'wordpress/plugin-get',
				'wordpress/plugin-search',
				'wordpress/plugin-get-information',
				'wordpress/plugin-install-package',
				'wordpress/plugin-check-updates',
				'wordpress/plugin-update',
				'wordpress/plugin-enable-auto-update',
				'wordpress/plugin-disable-auto-update',
				'wordpress/plugin-deactivate',
				'wordpress/plugin-delete',
			) as $ability_name
		) {
			wp_ability_e2e_ability( $ability_name );
		}
		WP_CLI::success( 'Single-site plugin management abilities are registered.' );
		break;

	case 'discovery':
		$search_result = wp_ability_e2e_require_success(
			wp_ability_e2e_ability( 'wordpress/plugin-search' )->execute(
				array(
					'search'   => 'akismet',
					'page'     => 1,
					'per_page' => 5,
				)
			),
			'Plugin search'
		);
		if ( empty( $search_result['plugins'] ) ) {
			WP_CLI::error( 'WordPress.org plugin search returned no results.' );
		}

		$info = wp_ability_e2e_require_success(
			wp_ability_e2e_ability( 'wordpress/plugin-get-information' )->execute(
				array( 'slug' => 'akismet' )
			),
			'Plugin information'
		);
		if ( 'akismet' !== $info['slug'] ) {
			WP_CLI::error( 'Plugin information returned an unexpected slug.' );
		}
		WP_CLI::success( 'WordPress.org discovery abilities use Core successfully.' );
		break;

	case 'install':
		$url       = isset( $args[1] ) ? $args[1] : '';
		$overwrite = isset( $args[2] ) && 'true' === $args[2];
		$activate  = isset( $args[3] ) && 'true' === $args[3];
		$sha256    = isset( $args[4] ) ? $args[4] : '';
		$result    = wp_ability_e2e_ability( 'wordpress/plugin-install-package' )->execute(
			array(
				'package_url' => $url,
				'overwrite'   => $overwrite,
				'activate'        => $activate,
				'expected_sha256' => $sha256,
			)
		);
		$result    = wp_ability_e2e_require_success( $result, 'Package install' );

		if ( empty( $result['installed'] ) || $plugin_file !== $result['plugin_file'] ) {
			WP_CLI::error( 'Package install returned an unexpected result: ' . wp_json_encode( $result ) );
		}

		WP_CLI::log( wp_json_encode( $result ) );
		break;


	case 'install-checksum-mismatch':
		$url    = isset( $args[1] ) ? $args[1] : '';
		$result = wp_ability_e2e_ability( 'wordpress/plugin-install-package' )->execute(
			array(
				'package_url'     => $url,
				'overwrite'       => false,
				'activate'        => false,
				'expected_sha256' => str_repeat( '0', 64 ),
			)
		);
		if ( ! is_wp_error( $result ) || 'wp_ability_package_checksum_mismatch' !== $result->get_error_code() ) {
			WP_CLI::error( 'Plugin package checksum mismatch was not rejected.' );
		}
		WP_CLI::success( 'Plugin package checksum mismatch rejected.' );
		break;

	case 'assert-version':
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		$expected = isset( $args[1] ) ? $args[1] : '';
		$state    = isset( $args[2] ) ? $args[2] : 'inactive';
		$installed_plugins = get_plugins();

		if ( ! isset( $installed_plugins[ $plugin_file ] ) ) {
			WP_CLI::error( 'Fixture plugin is not installed.' );
		}

		if ( $expected !== $installed_plugins[ $plugin_file ]['Version'] ) {
			WP_CLI::error( 'Expected fixture version ' . $expected . ', got ' . $installed_plugins[ $plugin_file ]['Version'] . '.' );
		}

		$should_be_active = 'active' === $state;
		if ( is_plugin_active( $plugin_file ) !== $should_be_active ) {
			WP_CLI::error( 'Fixture activation state did not match ' . $state . '.' );
		}

		WP_CLI::success( 'Fixture is version ' . $expected . ' and ' . $state . '.' );
		break;


	case 'assert-inventory':
		$expected_version = isset( $args[1] ) ? $args[1] : '';
		$expected_state   = isset( $args[2] ) ? $args[2] : 'inactive';
		$expected_auto    = isset( $args[3] ) && 'true' === $args[3];

		$list = wp_ability_e2e_require_success(
			wp_ability_e2e_ability( 'wordpress/plugin-list' )->execute( array() ),
			'Plugin list'
		);
		$list_item = null;
		foreach ( $list['plugins'] as $plugin_item ) {
			if ( $plugin_file === $plugin_item['plugin_file'] ) {
				$list_item = $plugin_item;
				break;
			}
		}
		if ( ! $list_item ) {
			WP_CLI::error( 'Fixture plugin was absent from plugin-list.' );
		}

		$detail = wp_ability_e2e_require_success(
			wp_ability_e2e_ability( 'wordpress/plugin-get' )->execute(
				array( 'plugin_file' => $plugin_file )
			),
			'Plugin get'
		);

		$expected_active = 'active' === $expected_state;
		foreach ( array( $list_item, $detail ) as $payload ) {
			if ( $expected_version !== $payload['version'] ) {
				WP_CLI::error( 'Inventory version mismatch.' );
			}
			if ( $expected_active !== $payload['active'] ) {
				WP_CLI::error( 'Inventory active state mismatch.' );
			}
			if ( ! array_key_exists( 'auto_update_enabled', $payload ) || $expected_auto !== $payload['auto_update_enabled'] ) {
				WP_CLI::error( 'Inventory auto-update state mismatch.' );
			}
		}
		WP_CLI::success( 'Plugin list/get inventory matches Core state.' );
		break;

	case 'auto-update':
		$enabled = isset( $args[1] ) && 'true' === $args[1];
		$name    = $enabled ? 'wordpress/plugin-enable-auto-update' : 'wordpress/plugin-disable-auto-update';
		$result  = wp_ability_e2e_require_success(
			wp_ability_e2e_ability( $name )->execute(
				array( 'plugin_file' => $plugin_file )
			),
			'Plugin auto-update toggle'
		);
		if ( $enabled !== $result['auto_update_enabled'] ) {
			WP_CLI::error( 'Auto-update ability returned an unexpected state.' );
		}
		break;

	case 'prime-update':
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		$package_url = isset( $args[1] ) ? $args[1] : '';
		$checked     = array();
		foreach ( get_plugins() as $file => $data ) {
			$checked[ $file ] = $data['Version'];
		}
		set_site_transient(
			'update_plugins',
			(object) array(
				'last_checked' => time(),
				'checked'      => $checked,
				'response'     => array(
					$plugin_file => (object) array(
						'id'          => 'wp-ability-e2e-fixture',
						'slug'        => 'wp-ability-e2e-fixture',
						'plugin'      => $plugin_file,
						'new_version' => '2.0.0',
						'url'         => 'https://example.test/',
						'package'     => $package_url,
					),
				),
				'no_update'    => array(),
			)
		);
		WP_CLI::success( 'Core plugin update transient primed for fixture v2.' );
		break;

	case 'check-update':
		$result = wp_ability_e2e_require_success(
			wp_ability_e2e_ability( 'wordpress/plugin-check-updates' )->execute( array() ),
			'Plugin update check'
		);
		if ( '2.0.0' !== $result['updates']['response'][ $plugin_file ]['new_version'] ) {
			WP_CLI::error( 'Plugin update check did not return fixture v2.' );
		}
		break;

	case 'update':
		$result = wp_ability_e2e_require_success(
			wp_ability_e2e_ability( 'wordpress/plugin-update' )->execute(
				array( 'plugin_file' => $plugin_file )
			),
			'Plugin update'
		);
		if ( empty( $result['updated'] ) ) {
			WP_CLI::error( 'Plugin update did not report success.' );
		}
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
		$installed_plugins = get_plugins();

		if ( isset( $installed_plugins[ $plugin_file ] ) || is_dir( WP_PLUGIN_DIR . '/wp-ability-e2e-fixture' ) ) {
			WP_CLI::error( 'Fixture plugin still exists after delete.' );
		}

		WP_CLI::success( 'Fixture plugin is absent.' );
		break;

	default:
		WP_CLI::error( 'Unknown E2E command: ' . $command );
}
