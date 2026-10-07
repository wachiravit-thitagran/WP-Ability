<?php
/**
 * Native WordPress plugin package installer.
 *
 * @package WP_Ability
 */

namespace WP_Ability;

defined( 'ABSPATH' ) || exit;

/**
 * Validates and installs plugin ZIP packages through WordPress Core.
 */
final class Plugin_Package_Installer {

	/**
	 * Validate a remote plugin package URL.
	 *
	 * @param string $package_url Package URL.
	 * @return string|\WP_Error
	 */
	public function validate_package_url( $package_url ) {
		$package_url = trim( (string) $package_url );

		if ( '' === $package_url ) {
			return $this->invalid_package_url();
		}

		$parts = wp_parse_url( $package_url );

		if ( ! is_array( $parts ) || empty( $parts['scheme'] ) || 'https' !== strtolower( $parts['scheme'] ) ) {
			return $this->invalid_package_url();
		}

		$validated = wp_http_validate_url( $package_url );

		if ( false === $validated ) {
			return $this->invalid_package_url();
		}

		return $validated;
	}

	/**
	 * Create a consistent invalid package URL error.
	 *
	 * @return \WP_Error
	 */
	private function invalid_package_url() {
		return new \WP_Error(
			'wp_ability_invalid_plugin_package_url',
			__( 'A valid HTTPS plugin package URL is required.', 'wp-ability' )
		);
	}
}
