<?php
/**
 * Real WordPress Multisite E2E driver.
 *
 * @package WordPress_Abilities_Bridge
 */

defined( 'ABSPATH' ) || exit;

$command     = isset( $args[0] ) ? $args[0] : '';
$plugin_file = 'wp-ability-e2e-fixture/fixture-plugin.php';

/**
 * Resolve a registered ability.
 *
 * @param string $name Ability name.
 * @return WP_Ability
 */
function wp_ability_multisite_e2e_ability( $name ) {
	$ability = wp_get_ability( $name );
	if ( ! $ability ) {
		WP_CLI::error( 'Ability is not registered: ' . $name );
	}
	return $ability;
}

/**
 * Fail the E2E run when a Core-backed ability returns an error.
 *
 * @param mixed  $result Ability result.
 * @param string $step   Step label.
 * @return mixed
 */
function wp_ability_multisite_e2e_success( $result, $step ) {
	if ( is_wp_error( $result ) ) {
		WP_CLI::error( $step . ' failed [' . $result->get_error_code() . ']: ' . $result->get_error_message() );
	}
	return $result;
}

switch ( $command ) {
	case 'ability':
		if ( ! is_multisite() ) {
			WP_CLI::error( 'Multisite is not enabled.' );
		}
		foreach (
			array(
				'wordpress/plugin-install-package',
				'wordpress/plugin-list',
				'wordpress/plugin-get',
				'wordpress/plugin-network-activate',
				'wordpress/plugin-network-deactivate',
				'wordpress/plugin-update',
				'wordpress/plugin-delete',
			) as $ability_name
		) {
			wp_ability_multisite_e2e_ability( $ability_name );
		}
		WP_CLI::success( 'Multisite plugin management abilities are registered.' );
		break;

	case 'install':
		$url    = isset( $args[1] ) ? $args[1] : '';
		$result = wp_ability_multisite_e2e_success(
			wp_ability_multisite_e2e_ability( 'wordpress/plugin-install-package' )->execute(
				array(
					'package_url' => $url,
					'overwrite'   => false,
					'activate'    => false,
				)
			),
			'Package install'
		);
		if ( empty( $result['installed'] ) || $plugin_file !== $result['plugin_file'] ) {
			WP_CLI::error( 'Unexpected package install result.' );
		}
		break;

	case 'network-activate':
		$result = wp_ability_multisite_e2e_success(
			wp_ability_multisite_e2e_ability( 'wordpress/plugin-network-activate' )->execute(
				array( 'plugin_file' => $plugin_file )
			),
			'Network activate'
		);
		if ( empty( $result['network_active'] ) || ! is_plugin_active_for_network( $plugin_file ) ) {
			WP_CLI::error( 'Plugin is not network active.' );
		}
		break;

	case 'assert-network':
		$expected = isset( $args[1] ) && 'true' === $args[1];
		$actual   = is_plugin_active_for_network( $plugin_file );
		if ( $expected !== $actual ) {
			WP_CLI::error( 'Network activation state mismatch.' );
		}

		$list = wp_ability_multisite_e2e_success(
			wp_ability_multisite_e2e_ability( 'wordpress/plugin-list' )->execute( array() ),
			'Plugin list'
		);
		$item = null;
		foreach ( $list['plugins'] as $plugin_item ) {
			if ( $plugin_file === $plugin_item['plugin_file'] ) {
				$item = $plugin_item;
				break;
			}
		}
		if ( ! $item || $expected !== $item['network_active'] ) {
			WP_CLI::error( 'Plugin inventory network state mismatch.' );
		}
		break;

	case 'network-deactivate':
		$result = wp_ability_multisite_e2e_success(
			wp_ability_multisite_e2e_ability( 'wordpress/plugin-network-deactivate' )->execute(
				array( 'plugin_file' => $plugin_file )
			),
			'Network deactivate'
		);
		if ( ! empty( $result['network_active'] ) || is_plugin_active_for_network( $plugin_file ) ) {
			WP_CLI::error( 'Plugin remained network active.' );
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
		break;

	case 'update':
		$result = wp_ability_multisite_e2e_success(
			wp_ability_multisite_e2e_ability( 'wordpress/plugin-update' )->execute(
				array( 'plugin_file' => $plugin_file )
			),
			'Plugin update'
		);
		if ( empty( $result['updated'] ) ) {
			WP_CLI::error( 'Plugin update did not report success.' );
		}
		break;

	case 'assert-version':
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		$expected = isset( $args[1] ) ? $args[1] : '';
		$installed = get_plugins();
		if ( ! isset( $installed[ $plugin_file ] ) || $expected !== $installed[ $plugin_file ]['Version'] ) {
			WP_CLI::error( 'Fixture version mismatch.' );
		}
		break;

	case 'delete':
		$result = wp_ability_multisite_e2e_success(
			wp_ability_multisite_e2e_ability( 'wordpress/plugin-delete' )->execute(
				array( 'plugin_file' => $plugin_file )
			),
			'Plugin delete'
		);
		if ( empty( $result['deleted'] ) ) {
			WP_CLI::error( 'Plugin delete did not report success.' );
		}
		break;

	case 'assert-absent':
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		if ( isset( get_plugins()[ $plugin_file ] ) || is_dir( WP_PLUGIN_DIR . '/wp-ability-e2e-fixture' ) ) {
			WP_CLI::error( 'Fixture plugin still exists.' );
		}
		break;

	default:
		WP_CLI::error( 'Unknown Multisite E2E command: ' . $command );
}
