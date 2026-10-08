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
	 * Optional upgrader factory used by tests.
	 *
	 * @var callable|null
	 */
	private $upgrader_factory;

	/**
	 * Optional activation callback used by tests.
	 *
	 * @var callable|null
	 */
	private $activator;

	/**
	 * Optional active-state callback used by tests.
	 *
	 * @var callable|null
	 */
	private $active_checker;

	/**
	 * Constructor.
	 *
	 * @param callable|null $upgrader_factory Optional upgrader factory.
	 * @param callable|null $activator        Optional activation callback.
	 * @param callable|null $active_checker   Optional active-state callback.
	 */
	public function __construct( $upgrader_factory = null, $activator = null, $active_checker = null ) {
		$this->upgrader_factory = $upgrader_factory;
		$this->activator        = $activator;
		$this->active_checker   = $active_checker;
	}

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
	 * Install or overwrite a plugin package using WordPress Core.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public function install( array $input ) {
		$package_url = $this->validate_package_url( isset( $input['package_url'] ) ? $input['package_url'] : '' );

		if ( is_wp_error( $package_url ) ) {
			return $package_url;
		}

		$overwrite = ! empty( $input['overwrite'] );
		$activate  = ! empty( $input['activate'] );

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		$upgrader = $this->create_upgrader();
		if ( is_wp_error( $upgrader ) ) {
			return $upgrader;
		}

		$result = $upgrader->install(
			$package_url,
			array(
				'overwrite_package'  => $overwrite,
				'clear_update_cache' => true,
			)
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		if ( false === $result ) {
			return new \WP_Error(
				'wp_ability_plugin_package_install_failed',
				__( 'Plugin package installation failed.', 'wp-ability' )
			);
		}

		$plugin_file = $upgrader->plugin_info();

		if ( ! is_string( $plugin_file ) || '' === $plugin_file ) {
			return new \WP_Error(
				'wp_ability_plugin_file_unknown',
				__( 'The installed plugin file could not be determined.', 'wp-ability' )
			);
		}

		wp_clean_plugins_cache( true );

		$activated = false;

		if ( $activate ) {
			$activation = $this->activate_plugin( $plugin_file );

			if ( is_wp_error( $activation ) ) {
				return $activation;
			}

			if ( ! $this->is_plugin_active( $plugin_file ) ) {
				return new \WP_Error(
					'wp_ability_plugin_activation_unverified',
					__( 'Plugin activation could not be verified.', 'wp-ability' )
				);
			}

			$activated = true;
		}

		$response = array(
			'plugin_file' => $plugin_file,
			'installed'   => true,
			'overwritten' => $overwrite,
			'activated'   => $activated,
		);

		$plugins = get_plugins();
		if ( isset( $plugins[ $plugin_file ]['Version'] ) ) {
			$response['version'] = $plugins[ $plugin_file ]['Version'];
		}

		return $response;
	}

	/**
	 * Create the WordPress plugin upgrader.
	 *
	 * @return object|\WP_Error
	 */
	private function create_upgrader() {
		if ( is_callable( $this->upgrader_factory ) ) {
			return call_user_func( $this->upgrader_factory );
		}

		if ( ! class_exists( '\Automatic_Upgrader_Skin' ) || ! class_exists( '\Plugin_Upgrader' ) ) {
			return new \WP_Error(
				'wp_ability_plugin_upgrader_unavailable',
				__( 'WordPress plugin upgrader is unavailable.', 'wp-ability' )
			);
		}

		$skin = new \Automatic_Upgrader_Skin();

		return new \Plugin_Upgrader( $skin );
	}

	/**
	 * Activate the installed plugin.
	 *
	 * @param string $plugin_file Plugin file.
	 * @return null|\WP_Error
	 */
	private function activate_plugin( $plugin_file ) {
		if ( is_callable( $this->activator ) ) {
			return call_user_func( $this->activator, $plugin_file );
		}

		return activate_plugin( $plugin_file );
	}

	/**
	 * Check whether the installed plugin is active.
	 *
	 * @param string $plugin_file Plugin file.
	 * @return bool
	 */
	private function is_plugin_active( $plugin_file ) {
		if ( is_callable( $this->active_checker ) ) {
			return (bool) call_user_func( $this->active_checker, $plugin_file );
		}

		return is_plugin_active( $plugin_file );
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
