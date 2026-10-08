<?php
/**
 * WordPress theme management abilities.
 *
 * @package WordPress_Abilities_Bridge
 */

namespace WP_Ability;

defined( 'ABSPATH' ) || exit;

/**
 * Registers semantic abilities for WordPress Core theme management.
 */
final class Theme_Abilities {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'wp_abilities_api_init', array( $this, 'register_abilities' ) );
	}

	/**
	 * Register theme abilities.
	 *
	 * @return void
	 */
	public function register_abilities() {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		$this->register( 'wordpress/theme-list', __( 'List WordPress Themes', 'wp-ability' ), __( 'Lists installed themes and their current update and activation state.', 'wp-ability' ), $this->empty_schema(), array( $this, 'theme_list' ), array( $this, 'can_switch_themes' ), true, false, true );
		$this->register( 'wordpress/theme-get', __( 'Get WordPress Theme', 'wp-ability' ), __( 'Returns one installed WordPress theme and its current state.', 'wp-ability' ), $this->stylesheet_schema(), array( $this, 'theme_get' ), array( $this, 'can_switch_themes' ), true, false, true );
		$this->register( 'wordpress/theme-search', __( 'Search WordPress Themes', 'wp-ability' ), __( 'Searches the WordPress.org theme directory.', 'wp-ability' ), $this->search_schema(), array( $this, 'theme_search' ), array( $this, 'can_install_themes' ), true, false, true, true );
		$this->register( 'wordpress/theme-get-information', __( 'Get WordPress Theme Information', 'wp-ability' ), __( 'Returns WordPress.org theme information for a slug.', 'wp-ability' ), $this->slug_schema(), array( $this, 'theme_get_information' ), array( $this, 'can_install_themes' ), true, false, true, true );
		$this->register( 'wordpress/theme-install', __( 'Install WordPress Theme', 'wp-ability' ), __( 'Installs a theme from the WordPress.org theme directory and can optionally activate it.', 'wp-ability' ), $this->install_schema(), array( $this, 'theme_install' ), array( $this, 'can_install_themes' ), false, false, false, true );
		$this->register( 'wordpress/theme-install-package', __( 'Install WordPress Theme Package', 'wp-ability' ), __( 'Installs or overwrites a WordPress theme from an HTTPS ZIP package URL using the native WordPress theme upgrader.', 'wp-ability' ), $this->package_schema(), array( $this, 'theme_install_package' ), array( $this, 'can_install_theme_package' ), false, false, false, true );
		$this->register( 'wordpress/theme-check-updates', __( 'Check WordPress Theme Updates', 'wp-ability' ), __( 'Refreshes and returns WordPress Core theme update metadata.', 'wp-ability' ), $this->empty_schema(), array( $this, 'theme_check_updates' ), array( $this, 'can_update_themes' ), false, false, true, true );
		$this->register( 'wordpress/theme-update', __( 'Update WordPress Theme', 'wp-ability' ), __( 'Updates one installed WordPress theme using the Core theme upgrader.', 'wp-ability' ), $this->stylesheet_schema(), array( $this, 'theme_update' ), array( $this, 'can_update_themes' ), false, false, false, true );
		$this->register( 'wordpress/theme-update-many', __( 'Update WordPress Themes', 'wp-ability' ), __( 'Updates multiple installed WordPress themes using the Core bulk upgrader.', 'wp-ability' ), $this->stylesheets_schema(), array( $this, 'theme_update_many' ), array( $this, 'can_update_themes' ), false, false, false, true );
		$this->register( 'wordpress/theme-enable-auto-update', __( 'Enable Theme Auto-Update', 'wp-ability' ), __( 'Enables WordPress Core automatic updates for one installed theme.', 'wp-ability' ), $this->stylesheet_schema(), array( $this, 'theme_enable_auto_update' ), array( $this, 'can_update_themes' ), false, false, true );
		$this->register( 'wordpress/theme-disable-auto-update', __( 'Disable Theme Auto-Update', 'wp-ability' ), __( 'Disables WordPress Core automatic updates for one installed theme.', 'wp-ability' ), $this->stylesheet_schema(), array( $this, 'theme_disable_auto_update' ), array( $this, 'can_update_themes' ), false, false, true );
		$this->register( 'wordpress/theme-activate', __( 'Activate WordPress Theme', 'wp-ability' ), __( 'Switches the current site to an installed theme.', 'wp-ability' ), $this->stylesheet_schema(), array( $this, 'theme_activate' ), array( $this, 'can_switch_themes' ), false, false, true );
		$this->register( 'wordpress/theme-delete', __( 'Delete WordPress Theme', 'wp-ability' ), __( 'Deletes an installed inactive theme.', 'wp-ability' ), $this->stylesheet_schema(), array( $this, 'theme_delete' ), array( $this, 'can_delete_themes' ), false, true, true );
	}

	/**
	 * Register one theme ability.
	 *
	 * @param string   $name Ability name.
	 * @param string   $label Human label.
	 * @param string   $description Description.
	 * @param array    $schema Input schema.
	 * @param callable $execute Execute callback.
	 * @param callable $permission Permission callback.
	 * @param bool     $readonly Read-only operation.
	 * @param bool     $destructive Destructive operation.
	 * @param bool     $idempotent Idempotent operation.
	 * @param bool     $open_world May access external systems.
	 * @return void
	 */
	private function register( $name, $label, $description, array $schema, $execute, $permission, $readonly, $destructive, $idempotent, $open_world = false ) {
		Ability_Registrar::register(
			$name,
			array(
				'label'               => $label,
				'description'         => $description,
				'category'            => 'wordpress-admin',
				'input_schema'        => $schema,
				'execute_callback'    => $execute,
				'permission_callback' => $permission,
				'meta'                => array(
					'public'      => true,
					'annotations' => array(
						'readonly'      => (bool) $readonly,
						'destructive'   => (bool) $destructive,
						'idempotent'    => (bool) $idempotent,
						'openWorldHint' => (bool) $open_world,
					),
				),
			)
		);
	}

	/**
	 * Empty input schema.
	 *
	 * @return array
	 */
	private function empty_schema() {
		return array(
			'type'                 => 'object',
			'properties'           => array(),
			'additionalProperties' => false,
		);
	}

	/**
	 * Installed stylesheet schema.
	 *
	 * @return array
	 */
	private function stylesheet_schema() {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'stylesheet' => array(
					'type'      => 'string',
					'minLength' => 1,
				),
			),
			'required'             => array( 'stylesheet' ),
			'additionalProperties' => false,
		);
	}

	/**
	 * Installed stylesheets schema.
	 *
	 * @return array
	 */
	private function stylesheets_schema() {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'stylesheets' => array(
					'type'     => 'array',
					'minItems' => 1,
					'items'    => array(
						'type'      => 'string',
						'minLength' => 1,
					),
				),
			),
			'required'             => array( 'stylesheets' ),
			'additionalProperties' => false,
		);
	}

	/**
	 * WordPress.org slug schema.
	 *
	 * @return array
	 */
	private function slug_schema() {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'slug' => array(
					'type'      => 'string',
					'minLength' => 1,
				),
			),
			'required'             => array( 'slug' ),
			'additionalProperties' => false,
		);
	}

	/**
	 * Theme search schema.
	 *
	 * @return array
	 */
	private function search_schema() {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'search'   => array( 'type' => 'string' ),
				'page'     => array(
					'type'    => 'integer',
					'minimum' => 1,
					'default' => 1,
				),
				'per_page' => array(
					'type'    => 'integer',
					'minimum' => 1,
					'maximum' => 100,
					'default' => 20,
				),
			),
			'additionalProperties' => false,
		);
	}

	/**
	 * WordPress.org install schema.
	 *
	 * @return array
	 */
	private function install_schema() {
		$schema                         = $this->slug_schema();
		$schema['properties']['activate'] = array(
			'type'    => 'boolean',
			'default' => false,
		);
		return $schema;
	}

	/**
	 * Remote package install schema.
	 *
	 * @return array
	 */
	private function package_schema() {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'package_url' => array(
					'type'      => 'string',
					'format'    => 'uri',
					'minLength' => 1,
				),
				'overwrite'   => array(
					'type'    => 'boolean',
					'default' => false,
				),
				'activate'    => array(
					'type'    => 'boolean',
					'default' => false,
				),
				'expected_sha256' => array(
					'type'      => 'string',
					'minLength' => 64,
					'maxLength' => 64,
				),
			),
			'required'             => array( 'package_url' ),
			'additionalProperties' => false,
		);
	}

	/**
	 * Whether the current user can switch themes.
	 *
	 * @return bool
	 */
	public function can_switch_themes() {
		return current_user_can( 'switch_themes' );
	}

	/**
	 * Whether the current user can install themes.
	 *
	 * @return bool
	 */
	public function can_install_themes() {
		return current_user_can( 'install_themes' );
	}

	/**
	 * Whether the current user can update themes.
	 *
	 * @return bool
	 */
	public function can_update_themes() {
		return current_user_can( 'update_themes' );
	}

	/**
	 * Whether the current user can delete themes.
	 *
	 * @return bool
	 */
	public function can_delete_themes() {
		return current_user_can( 'delete_themes' );
	}

	/**
	 * Permission callback for package installs.
	 *
	 * @param array $input Ability input.
	 * @return bool
	 */
	public function can_install_theme_package( $input = array() ) {
		if ( ! current_user_can( 'install_themes' ) ) {
			return false;
		}

		if ( ! empty( $input['overwrite'] ) && ! current_user_can( 'update_themes' ) ) {
			return false;
		}

		if ( ! empty( $input['activate'] ) && ! current_user_can( 'switch_themes' ) ) {
			return false;
		}

		return true;
	}

	/**
	 * List installed themes.
	 *
	 * @return array
	 */
	public function theme_list() {
		$result = array();
		foreach ( wp_get_themes() as $stylesheet => $theme ) {
			$result[] = $this->theme_record( $stylesheet, $theme );
		}
		return array( 'themes' => $result );
	}

	/**
	 * Return one installed theme.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public function theme_get( array $input ) {
		$stylesheet = sanitize_key( $input['stylesheet'] );
		$theme      = wp_get_theme( $stylesheet );
		if ( ! $theme->exists() ) {
			return new \WP_Error( 'wp_ability_theme_not_found', __( 'Theme not found.', 'wp-ability' ) );
		}
		return $this->theme_record( $stylesheet, $theme );
	}

	/**
	 * Search WordPress.org themes.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public function theme_search( array $input ) {
		require_once ABSPATH . 'wp-admin/includes/theme.php';

		$args = array(
			'page'     => isset( $input['page'] ) ? (int) $input['page'] : 1,
			'per_page' => isset( $input['per_page'] ) ? (int) $input['per_page'] : 20,
		);
		if ( ! empty( $input['search'] ) ) {
			$args['search'] = sanitize_text_field( $input['search'] );
		}

		$result = themes_api( 'query_themes', $args );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $this->normalize_api_result( $result );
	}

	/**
	 * Return WordPress.org theme information.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public function theme_get_information( array $input ) {
		require_once ABSPATH . 'wp-admin/includes/theme.php';

		$result = themes_api(
			'theme_information',
			array(
				'slug' => sanitize_key( $input['slug'] ),
			)
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $this->normalize_api_result( $result );
	}

	/**
	 * Install a WordPress.org theme.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public function theme_install( array $input ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/theme.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

		$slug = sanitize_key( $input['slug'] );
		$api  = themes_api( 'theme_information', array( 'slug' => $slug ) );
		if ( is_wp_error( $api ) ) {
			return $api;
		}
		if ( empty( $api->download_link ) ) {
			return new \WP_Error( 'wp_ability_theme_package_unavailable', __( 'Theme package is unavailable.', 'wp-ability' ) );
		}

		return $this->install_package_with_upgrader( $api->download_link, false, ! empty( $input['activate'] ), '' );
	}

	/**
	 * Install or overwrite an HTTPS theme package.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public function theme_install_package( array $input ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

		$package_url = trim( (string) $input['package_url'] );
		$parts       = wp_parse_url( $package_url );
		if ( ! is_array( $parts ) || empty( $parts['scheme'] ) || 'https' !== strtolower( $parts['scheme'] ) || false === wp_http_validate_url( $package_url ) ) {
			return new \WP_Error( 'wp_ability_invalid_theme_package_url', __( 'A valid HTTPS theme package URL is required.', 'wp-ability' ) );
		}

		return $this->install_package_with_upgrader(
			$package_url,
			! empty( $input['overwrite'] ),
			! empty( $input['activate'] ),
			isset( $input['expected_sha256'] ) ? $input['expected_sha256'] : ''
		);
	}

	/**
	 * Refresh theme updates.
	 *
	 * @return array
	 */
	public function theme_check_updates() {
		wp_update_themes();
		$updates = get_site_transient( 'update_themes' );
		return array(
			'updates' => is_object( $updates ) ? $this->normalize_api_result( $updates ) : array(),
		);
	}

	/**
	 * Update one installed theme.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public function theme_update( array $input ) {
		$stylesheet = sanitize_key( $input['stylesheet'] );
		$themes     = $this->installed_stylesheets( array( $stylesheet ) );
		if ( is_wp_error( $themes ) ) {
			return $themes;
		}

		$result = $this->bulk_upgrade( $themes );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$item = isset( $result[ $stylesheet ] ) ? $result[ $stylesheet ] : null;
		if ( is_wp_error( $item ) ) {
			return $item;
		}

		$theme = wp_get_theme( $stylesheet );
		return array(
			'stylesheet' => $stylesheet,
			'updated'    => true,
			'version'    => $theme->get( 'Version' ),
			'active'     => get_stylesheet() === $stylesheet,
		);
	}

	/**
	 * Update multiple installed themes.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public function theme_update_many( array $input ) {
		$stylesheets = $this->installed_stylesheets( $input['stylesheets'] );
		if ( is_wp_error( $stylesheets ) ) {
			return $stylesheets;
		}

		$result = $this->bulk_upgrade( $stylesheets );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array(
			'stylesheets' => $stylesheets,
			'results'     => $this->normalize_api_result( $result ),
		);
	}

	/**
	 * Enable Core theme auto-updates.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public function theme_enable_auto_update( array $input ) {
		return $this->set_auto_update( $input['stylesheet'], true );
	}

	/**
	 * Disable Core theme auto-updates.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public function theme_disable_auto_update( array $input ) {
		return $this->set_auto_update( $input['stylesheet'], false );
	}

	/**
	 * Activate an installed theme.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public function theme_activate( array $input ) {
		$stylesheet = sanitize_key( $input['stylesheet'] );
		$theme      = wp_get_theme( $stylesheet );
		if ( ! $theme->exists() || $theme->errors() ) {
			return new \WP_Error( 'wp_ability_theme_not_found', __( 'Theme not found or invalid.', 'wp-ability' ) );
		}
		switch_theme( $stylesheet );
		return array(
			'stylesheet' => $stylesheet,
			'active'     => get_stylesheet() === $stylesheet,
		);
	}

	/**
	 * Delete an inactive theme.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public function theme_delete( array $input ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/theme.php';

		$stylesheet = sanitize_key( $input['stylesheet'] );
		if ( get_stylesheet() === $stylesheet || get_template() === $stylesheet ) {
			return new \WP_Error( 'wp_ability_theme_active', __( 'The active theme cannot be deleted.', 'wp-ability' ) );
		}
		$theme = wp_get_theme( $stylesheet );
		if ( ! $theme->exists() ) {
			return new \WP_Error( 'wp_ability_theme_not_found', __( 'Theme not found.', 'wp-ability' ) );
		}
		$result = delete_theme( $stylesheet );
		return is_wp_error( $result ) ? $result : array(
			'stylesheet' => $stylesheet,
			'deleted'    => true,
		);
	}

	/**
	 * Install a package with Theme_Upgrader.
	 *
	 * @param string $package_url Package URL.
	 * @param bool   $overwrite Whether to overwrite an existing theme.
	 * @param bool   $activate Whether to activate after install.
	 * @param string $expected_sha256 Optional expected SHA-256.
	 * @return array|\WP_Error
	 */
	private function install_package_with_upgrader( $package_url, $overwrite, $activate, $expected_sha256 ) {
		$skin           = new \Automatic_Upgrader_Skin();
		$upgrader       = new \Theme_Upgrader( $skin );
		$package_source = $package_url;
		$verified_temp  = '';

		if ( '' !== trim( (string) $expected_sha256 ) ) {
			$verifier = new Package_Integrity_Verifier( $expected_sha256 );
			$verified = $verifier->verify( false, $package_url, null, array() );

			if ( is_wp_error( $verified ) ) {
				return $verified;
			}

			$package_source = $verified;
			$verified_temp  = $verified;
		}

		try {
			$result = $upgrader->install(
				$package_source,
				array(
					'overwrite_package'  => (bool) $overwrite,
					'clear_update_cache' => true,
				)
			);
		} finally {
			if ( '' !== $verified_temp && file_exists( $verified_temp ) ) {
				wp_delete_file( $verified_temp );
			}
		}

		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( isset( $upgrader->skin->result ) && is_wp_error( $upgrader->skin->result ) ) {
			return $upgrader->skin->result;
		}
		if ( false === $result ) {
			return new \WP_Error( 'wp_ability_theme_package_install_failed', __( 'Theme package installation failed.', 'wp-ability' ) );
		}

		wp_clean_themes_cache( true );

		$theme      = method_exists( $upgrader, 'theme_info' ) ? $upgrader->theme_info() : false;
		$stylesheet = $theme instanceof \WP_Theme ? $theme->get_stylesheet() : '';
		if ( '' === $stylesheet && isset( $upgrader->result['destination_name'] ) ) {
			$stylesheet = sanitize_key( $upgrader->result['destination_name'] );
			$theme      = wp_get_theme( $stylesheet );
		}

		if ( '' === $stylesheet || ! $theme || ! $theme->exists() ) {
			return new \WP_Error( 'wp_ability_theme_stylesheet_unknown', __( 'The installed theme stylesheet could not be determined.', 'wp-ability' ) );
		}

		if ( $activate ) {
			switch_theme( $stylesheet );
			if ( get_stylesheet() !== $stylesheet ) {
				return new \WP_Error( 'wp_ability_theme_activation_unverified', __( 'Theme activation could not be verified.', 'wp-ability' ) );
			}
		}

		return array(
			'stylesheet'  => $stylesheet,
			'installed'   => true,
			'overwritten' => (bool) $overwrite,
			'activated'   => (bool) $activate,
			'version'     => $theme->get( 'Version' ),
		);
	}

	/**
	 * Bulk-upgrade installed themes.
	 *
	 * @param array $stylesheets Theme stylesheets.
	 * @return array|\WP_Error
	 */
	private function bulk_upgrade( array $stylesheets ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-ajax-upgrader-skin.php';

		wp_update_themes();

		$skin     = new \WP_Ajax_Upgrader_Skin();
		$upgrader = new \Theme_Upgrader( $skin );
		$result   = $upgrader->bulk_upgrade( $stylesheets );

		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( false === $result ) {
			return new \WP_Error( 'wp_ability_theme_update_failed', __( 'Theme update failed.', 'wp-ability' ) );
		}

		wp_clean_themes_cache( true );
		return $result;
	}

	/**
	 * Validate installed stylesheets.
	 *
	 * @param array $stylesheets Theme stylesheets.
	 * @return array|\WP_Error
	 */
	private function installed_stylesheets( array $stylesheets ) {
		$installed = wp_get_themes();
		$result    = array();

		foreach ( $stylesheets as $stylesheet ) {
			$stylesheet = sanitize_key( $stylesheet );
			if ( ! isset( $installed[ $stylesheet ] ) ) {
				return new \WP_Error( 'wp_ability_theme_not_found', __( 'Theme not found.', 'wp-ability' ) );
			}
			$result[] = $stylesheet;
		}

		return array_values( array_unique( $result ) );
	}

	/**
	 * Set one theme's Core auto-update state.
	 *
	 * @param string $stylesheet Theme stylesheet.
	 * @param bool   $enabled Whether auto-update is enabled.
	 * @return array|\WP_Error
	 */
	private function set_auto_update( $stylesheet, $enabled ) {
		$stylesheet = sanitize_key( $stylesheet );
		$theme      = wp_get_theme( $stylesheet );
		if ( ! $theme->exists() ) {
			return new \WP_Error( 'wp_ability_theme_not_found', __( 'Theme not found.', 'wp-ability' ) );
		}

		$auto = get_site_option( 'auto_update_themes', array() );
		$auto = is_array( $auto ) ? $auto : array();

		if ( $enabled ) {
			if ( ! in_array( $stylesheet, $auto, true ) ) {
				$auto[] = $stylesheet;
			}
		} else {
			$auto = array_values( array_diff( $auto, array( $stylesheet ) ) );
		}

		update_site_option( 'auto_update_themes', array_values( array_unique( $auto ) ) );

		return array(
			'stylesheet'          => $stylesheet,
			'auto_update_enabled' => (bool) $enabled,
		);
	}

	/**
	 * Build one installed theme record.
	 *
	 * @param string    $stylesheet Theme stylesheet.
	 * @param \WP_Theme $theme Theme object.
	 * @return array
	 */
	private function theme_record( $stylesheet, \WP_Theme $theme ) {
		$updates = get_site_transient( 'update_themes' );
		$auto    = get_site_option( 'auto_update_themes', array() );
		$auto    = is_array( $auto ) ? $auto : array();

		return array(
			'stylesheet'          => $stylesheet,
			'name'                => $theme->get( 'Name' ),
			'version'             => $theme->get( 'Version' ),
			'author'              => $theme->get( 'Author' ),
			'description'         => $theme->get( 'Description' ),
			'template'            => $theme->get_template(),
			'active'              => get_stylesheet() === $stylesheet,
			'update_available'    => is_object( $updates ) && isset( $updates->response[ $stylesheet ] ),
			'auto_update_enabled' => in_array( $stylesheet, $auto, true ),
		);
	}

	/**
	 * Normalize WordPress API objects to JSON-compatible arrays.
	 *
	 * @param mixed $value Value to normalize.
	 * @return mixed
	 */
	private function normalize_api_result( $value ) {
		return json_decode( wp_json_encode( $value ), true );
	}
}
