<?php
/**
 * Real WordPress theme-management E2E driver.
 *
 * @package WordPress_Abilities_Bridge
 */

defined( 'ABSPATH' ) || exit;

$command    = isset( $args[0] ) ? $args[0] : '';
$stylesheet = 'wp-ability-e2e-theme';

/**
 * Resolve a registered ability.
 *
 * @param string $name Ability name.
 * @return WP_Ability
 */
function wp_ability_theme_e2e_ability( $name ) {
	$ability = wp_get_ability( $name );
	if ( ! $ability ) {
		WP_CLI::error( 'Ability is not registered: ' . $name );
	}
	return $ability;
}

/**
 * Fail when an ability returns WP_Error.
 *
 * @param mixed  $result Result.
 * @param string $step Step label.
 * @return mixed
 */
function wp_ability_theme_e2e_success( $result, $step ) {
	if ( is_wp_error( $result ) ) {
		WP_CLI::error( $step . ' failed [' . $result->get_error_code() . ']: ' . $result->get_error_message() );
	}
	return $result;
}

switch ( $command ) {
	case 'ability':
		foreach (
			array(
				'wordpress/theme-list',
				'wordpress/theme-get',
				'wordpress/theme-search',
				'wordpress/theme-get-information',
				'wordpress/theme-install',
				'wordpress/theme-install-package',
				'wordpress/theme-check-updates',
				'wordpress/theme-update',
				'wordpress/theme-update-many',
				'wordpress/theme-enable-auto-update',
				'wordpress/theme-disable-auto-update',
				'wordpress/theme-activate',
				'wordpress/theme-delete',
			) as $ability_name
		) {
			wp_ability_theme_e2e_ability( $ability_name );
		}
		WP_CLI::success( 'Theme-management abilities are registered.' );
		break;

	case 'discovery':
		$theme_search_result = wp_ability_theme_e2e_success(
			wp_ability_theme_e2e_ability( 'wordpress/theme-search' )->execute(
				array(
					'search'   => 'twenty twenty-five',
					'page'     => 1,
					'per_page' => 5,
				)
			),
			'Theme search'
		);
		if ( empty( $theme_search_result['themes'] ) ) {
			WP_CLI::error( 'WordPress.org theme search returned no results.' );
		}

		$info = wp_ability_theme_e2e_success(
			wp_ability_theme_e2e_ability( 'wordpress/theme-get-information' )->execute(
				array( 'slug' => 'twentytwentyfive' )
			),
			'Theme information'
		);
		if ( 'twentytwentyfive' !== $info['slug'] ) {
			WP_CLI::error( 'Theme information returned an unexpected slug.' );
		}
		WP_CLI::success( 'WordPress.org theme discovery uses Core successfully.' );
		break;

	case 'remember-fallback':
		update_option( 'wp_ability_e2e_theme_fallback', get_stylesheet(), false );
		break;


	case 'install-checksum-mismatch':
		$url    = isset( $args[1] ) ? $args[1] : '';
		$result = wp_ability_theme_e2e_ability( 'wordpress/theme-install-package' )->execute(
			array(
				'package_url'     => $url,
				'overwrite'       => false,
				'activate'        => false,
				'expected_sha256' => str_repeat( '0', 64 ),
			)
		);
		if ( ! is_wp_error( $result ) || 'wp_ability_package_checksum_mismatch' !== $result->get_error_code() ) {
			WP_CLI::error( 'Theme package checksum mismatch was not rejected.' );
		}
		break;

	case 'install':
		$url      = isset( $args[1] ) ? $args[1] : '';
		$activate = isset( $args[2] ) && 'true' === $args[2];
		$sha256   = isset( $args[3] ) ? $args[3] : '';
		$result   = wp_ability_theme_e2e_success(
			wp_ability_theme_e2e_ability( 'wordpress/theme-install-package' )->execute(
				array(
					'package_url' => $url,
					'overwrite'   => false,
					'activate'        => $activate,
					'expected_sha256' => $sha256,
				)
			),
			'Theme package install'
		);
		if ( empty( $result['installed'] ) || $stylesheet !== $result['stylesheet'] ) {
			WP_CLI::error( 'Unexpected theme package install result: ' . wp_json_encode( $result ) );
		}
		break;

	case 'overwrite':
		$url    = isset( $args[1] ) ? $args[1] : '';
		$sha256 = isset( $args[2] ) ? $args[2] : '';
		$result = wp_ability_theme_e2e_success(
			wp_ability_theme_e2e_ability( 'wordpress/theme-install-package' )->execute(
				array(
					'package_url' => $url,
					'overwrite'   => true,
					'activate'        => true,
					'expected_sha256' => $sha256,
				)
			),
			'Theme package overwrite'
		);
		if ( empty( $result['overwritten'] ) || empty( $result['activated'] ) ) {
			WP_CLI::error( 'Theme overwrite did not report expected state.' );
		}
		break;

	case 'assert':
		$version = isset( $args[1] ) ? $args[1] : '';
		$active  = isset( $args[2] ) && 'active' === $args[2];
		$theme   = wp_get_theme( $stylesheet );
		if ( ! $theme->exists() || $version !== $theme->get( 'Version' ) ) {
			WP_CLI::error( 'Theme version mismatch.' );
		}
		$actual_active = in_array( get_stylesheet(), array( $stylesheet ), true );
		if ( $active xor $actual_active ) {
			WP_CLI::error( 'Theme active state mismatch.' );
		}

		$detail = wp_ability_theme_e2e_success(
			wp_ability_theme_e2e_ability( 'wordpress/theme-get' )->execute(
				array( 'stylesheet' => $stylesheet )
			),
			'Theme get'
		);
		if ( $version !== $detail['version'] || $active !== $detail['active'] ) {
			WP_CLI::error( 'Theme inventory state mismatch.' );
		}
		break;

	case 'auto-update':
		$enabled = isset( $args[1] ) && 'true' === $args[1];
		$name    = $enabled ? 'wordpress/theme-enable-auto-update' : 'wordpress/theme-disable-auto-update';
		$result  = wp_ability_theme_e2e_success(
			wp_ability_theme_e2e_ability( $name )->execute(
				array( 'stylesheet' => $stylesheet )
			),
			'Theme auto-update toggle'
		);
		if ( $enabled !== $result['auto_update_enabled'] ) {
			WP_CLI::error( 'Theme auto-update state mismatch.' );
		}
		break;

	case 'prime-update':
		$package_url = isset( $args[1] ) ? $args[1] : '';
		$new_version = isset( $args[2] ) ? $args[2] : '';
		$checked     = array();
		foreach ( wp_get_themes() as $slug => $theme ) {
			$checked[ $slug ] = $theme->get( 'Version' );
		}
		set_site_transient(
			'update_themes',
			(object) array(
				'last_checked' => time(),
				'checked'      => $checked,
				'response'     => array(
					$stylesheet => array(
						'theme'       => $stylesheet,
						'new_version' => $new_version,
						'url'         => 'https://example.test/',
						'package'     => $package_url,
					),
				),
				'no_update'    => array(),
			)
		);
		break;

	case 'check-update':
		$result = wp_ability_theme_e2e_success(
			wp_ability_theme_e2e_ability( 'wordpress/theme-check-updates' )->execute( array() ),
			'Theme update check'
		);
		$expected = isset( $args[1] ) ? $args[1] : '';
		if ( $expected !== $result['updates']['response'][ $stylesheet ]['new_version'] ) {
			WP_CLI::error( 'Theme update check did not return the expected fixture version.' );
		}
		break;

	case 'update':
		$result = wp_ability_theme_e2e_success(
			wp_ability_theme_e2e_ability( 'wordpress/theme-update' )->execute(
				array( 'stylesheet' => $stylesheet )
			),
			'Theme update'
		);
		if ( empty( $result['updated'] ) ) {
			WP_CLI::error( 'Theme update did not report success.' );
		}
		break;

	case 'switch-back':
		$fallback = get_option( 'wp_ability_e2e_theme_fallback' );
		if ( ! $fallback || ! wp_get_theme( $fallback )->exists() ) {
			WP_CLI::error( 'Fallback theme is unavailable.' );
		}
		switch_theme( $fallback );
		if ( get_stylesheet() === $stylesheet ) {
			WP_CLI::error( 'Fixture theme remained active.' );
		}
		break;

	case 'delete':
		$result = wp_ability_theme_e2e_success(
			wp_ability_theme_e2e_ability( 'wordpress/theme-delete' )->execute(
				array( 'stylesheet' => $stylesheet )
			),
			'Theme delete'
		);
		if ( empty( $result['deleted'] ) ) {
			WP_CLI::error( 'Theme delete did not report success.' );
		}
		break;

	case 'assert-absent':
		if ( wp_get_theme( $stylesheet )->exists() || is_dir( get_theme_root() . '/' . $stylesheet ) ) {
			WP_CLI::error( 'Fixture theme still exists.' );
		}
		delete_option( 'wp_ability_e2e_theme_fallback' );
		break;

	default:
		WP_CLI::error( 'Unknown theme E2E command: ' . $command );
}
