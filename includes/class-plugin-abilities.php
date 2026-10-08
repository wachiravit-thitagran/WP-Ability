<?php
/**
 * WordPress plugin management abilities.
 *
 * @package WordPress_Abilities_Bridge
 */

namespace WP_Ability;

defined( 'ABSPATH' ) || exit;

/**
 * Registers semantic abilities for WordPress Core plugin management.
 */
final class Plugin_Abilities {

	/**
	 * Plugin package installer service.
	 *
	 * @var object|null
	 */
	private $plugin_package_installer;

	/**
	 * Constructor.
	 *
	 * @param object|null $plugin_package_installer Optional package installer service.
	 */
	public function __construct( $plugin_package_installer = null ) {
		$this->plugin_package_installer = $plugin_package_installer;
		add_action( 'wp_abilities_api_init', array( $this, 'register_abilities' ) );
	}

	/**
	 * Register plugin abilities.
	 *
	 * @return void
	 */
	public function register_abilities() {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		$this->register_plugin_install();
		$this->register_plugin_install_package();
		$this->register_plugin_update();
		$this->register_plugin_auto_updates();
		$this->register_plugin_bulk_actions();
		$this->register_plugin_network_actions();
		$this->register_plugin_discovery();
		$this->register_plugin_management();
	}

	/**
	 * Metadata shared by plugin abilities.
	 *
	 * @param bool $is_readonly Whether the operation is read-only.
	 * @param bool $destructive Whether the operation is destructive.
	 * @param bool $idempotent Whether repeating the request is safe.
	 * @param bool $open_world Whether the operation may access external systems.
	 * @return array
	 */
	private function meta( $is_readonly, $destructive, $idempotent, $open_world = false ) {
		return array(
			'public'      => true,
			'annotations' => array(
				'readonly'      => (bool) $is_readonly,
				'destructive'   => (bool) $destructive,
				'idempotent'    => (bool) $idempotent,
				'openWorldHint' => (bool) $open_world,
			),
		);
	}

	/**
	 * Register plugin installation ability.
	 *
	 * @return void
	 */
	private function register_plugin_install() {
		wp_register_ability(
			'wordpress/plugin-install',
			array(
				'label'               => __( 'Install WordPress Plugin', 'wp-ability' ),
				'description'         => __( 'Installs a plugin from the WordPress.org plugin directory by slug and can optionally activate it after installation.', 'wp-ability' ),
				'category'            => 'wordpress-admin',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'slug'     => array(
							'type'        => 'string',
							'minLength'   => 1,
							'description' => __( 'WordPress.org plugin slug, for example "akismet".', 'wp-ability' ),
						),
						'activate' => array(
							'type'        => 'boolean',
							'default'     => false,
							'description' => __( 'Whether to activate the plugin after installation.', 'wp-ability' ),
						),
					),
					'required'             => array( 'slug' ),
					'additionalProperties' => false,
				),
				'execute_callback'    => array( $this, 'plugin_install' ),
				'permission_callback' => array( $this, 'can_install_plugins' ),
				'meta'                => $this->meta( false, false, false, true ),
			)
		);
	}

	/**
	 * Register package installation ability.
	 *
	 * @return void
	 */
	private function register_plugin_install_package() {
		wp_register_ability(
			'wordpress/plugin-install-package',
			array(
				'label'               => __( 'Install WordPress Plugin Package', 'wp-ability' ),
				'description'         => __( 'Installs or overwrites a WordPress plugin from an HTTPS ZIP package URL using the native WordPress plugin upgrader.', 'wp-ability' ),
				'category'            => 'wordpress-admin',
				'input_schema'        => array(
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
					),
					'required'             => array( 'package_url' ),
					'additionalProperties' => false,
				),
				'execute_callback'    => array( $this, 'plugin_install_package' ),
				'permission_callback' => array( $this, 'can_install_plugin_package' ),
				'meta'                => $this->meta( false, false, false, true ),
			)
		);
	}

	/**
	 * Register single plugin update ability.
	 *
	 * @return void
	 */
	private function register_plugin_update() {
		wp_register_ability(
			'wordpress/plugin-update',
			array(
				'label'               => __( 'Update WordPress Plugin', 'wp-ability' ),
				'description'         => __( 'Updates one installed WordPress plugin using its registered plugin file path.', 'wp-ability' ),
				'category'            => 'wordpress-admin',
				'input_schema'        => $this->plugin_file_schema(),
				'execute_callback'    => array( $this, 'plugin_update' ),
				'permission_callback' => array( $this, 'can_update_plugins' ),
				'meta'                => $this->meta( false, false, false, true ),
			)
		);

		wp_register_ability(
			'wordpress/plugin-check-updates',
			array(
				'label'               => __( 'Check WordPress Plugin Updates', 'wp-ability' ),
				'description'         => __( 'Refreshes and returns WordPress Core plugin update metadata.', 'wp-ability' ),
				'category'            => 'wordpress-admin',
				'input_schema'        => $this->empty_schema(),
				'execute_callback'    => array( $this, 'plugin_check_updates' ),
				'permission_callback' => array( $this, 'can_update_plugins' ),
				'meta'                => $this->meta( false, false, true, true ),
			)
		);

		wp_register_ability(
			'wordpress/plugin-update-many',
			array(
				'label'               => __( 'Update WordPress Plugins', 'wp-ability' ),
				'description'         => __( 'Updates multiple installed WordPress plugins using the Core bulk upgrader.', 'wp-ability' ),
				'category'            => 'wordpress-admin',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'plugin_files' => array(
							'type'     => 'array',
							'minItems' => 1,
							'items'    => array(
								'type'      => 'string',
								'minLength' => 1,
							),
						),
					),
					'required'             => array( 'plugin_files' ),
					'additionalProperties' => false,
				),
				'execute_callback'    => array( $this, 'plugin_update_many' ),
				'permission_callback' => array( $this, 'can_update_plugins' ),
				'meta'                => $this->meta( false, false, false, true ),
			)
		);
	}


	/**
	 * Register plugin auto-update abilities.
	 *
	 * @return void
	 */
	private function register_plugin_auto_updates() {
		wp_register_ability(
			'wordpress/plugin-enable-auto-update',
			array(
				'label'               => __( 'Enable WordPress Plugin Auto-Update', 'wp-ability' ),
				'description'         => __( 'Enables WordPress Core automatic updates for one installed plugin.', 'wp-ability' ),
				'category'            => 'wordpress-admin',
				'input_schema'        => $this->plugin_file_schema(),
				'execute_callback'    => array( $this, 'plugin_enable_auto_update' ),
				'permission_callback' => array( $this, 'can_update_plugins' ),
				'meta'                => $this->meta( false, false, true ),
			)
		);

		wp_register_ability(
			'wordpress/plugin-disable-auto-update',
			array(
				'label'               => __( 'Disable WordPress Plugin Auto-Update', 'wp-ability' ),
				'description'         => __( 'Disables WordPress Core automatic updates for one installed plugin.', 'wp-ability' ),
				'category'            => 'wordpress-admin',
				'input_schema'        => $this->plugin_file_schema(),
				'execute_callback'    => array( $this, 'plugin_disable_auto_update' ),
				'permission_callback' => array( $this, 'can_update_plugins' ),
				'meta'                => $this->meta( false, false, true ),
			)
		);
	}


	/**
	 * Register plugin bulk lifecycle abilities.
	 *
	 * @return void
	 */
	private function register_plugin_bulk_actions() {
		$schema = array(
			'type'                 => 'object',
			'properties'           => array(
				'plugin_files' => array(
					'type'     => 'array',
					'minItems' => 1,
					'items'    => array(
						'type'      => 'string',
						'minLength' => 1,
					),
				),
			),
			'required'             => array( 'plugin_files' ),
			'additionalProperties' => false,
		);

		wp_register_ability(
			'wordpress/plugin-activate-many',
			array(
				'label'               => __( 'Activate WordPress Plugins', 'wp-ability' ),
				'description'         => __( 'Activates multiple installed WordPress plugins using WordPress Core.', 'wp-ability' ),
				'category'            => 'wordpress-admin',
				'input_schema'        => $schema,
				'execute_callback'    => array( $this, 'plugin_activate_many' ),
				'permission_callback' => array( $this, 'can_activate_plugins' ),
				'meta'                => $this->meta( false, false, true ),
			)
		);

		wp_register_ability(
			'wordpress/plugin-deactivate-many',
			array(
				'label'               => __( 'Deactivate WordPress Plugins', 'wp-ability' ),
				'description'         => __( 'Deactivates multiple installed WordPress plugins using WordPress Core.', 'wp-ability' ),
				'category'            => 'wordpress-admin',
				'input_schema'        => $schema,
				'execute_callback'    => array( $this, 'plugin_deactivate_many' ),
				'permission_callback' => array( $this, 'can_activate_plugins' ),
				'meta'                => $this->meta( false, false, true ),
			)
		);

		wp_register_ability(
			'wordpress/plugin-delete-many',
			array(
				'label'               => __( 'Delete WordPress Plugins', 'wp-ability' ),
				'description'         => __( 'Deletes multiple installed inactive WordPress plugins using WordPress Core.', 'wp-ability' ),
				'category'            => 'wordpress-admin',
				'input_schema'        => $schema,
				'execute_callback'    => array( $this, 'plugin_delete_many' ),
				'permission_callback' => array( $this, 'can_delete_plugins' ),
				'meta'                => $this->meta( false, true, true ),
			)
		);
	}


	/**
	 * Register Multisite network plugin abilities.
	 *
	 * @return void
	 */
	private function register_plugin_network_actions() {
		wp_register_ability(
			'wordpress/plugin-network-activate',
			array(
				'label'               => __( 'Network Activate WordPress Plugin', 'wp-ability' ),
				'description'         => __( 'Network-activates one installed plugin using WordPress Core Multisite behavior.', 'wp-ability' ),
				'category'            => 'wordpress-admin',
				'input_schema'        => $this->plugin_file_schema(),
				'execute_callback'    => array( $this, 'plugin_network_activate' ),
				'permission_callback' => array( $this, 'can_manage_network_plugins' ),
				'meta'                => $this->meta( false, false, true ),
			)
		);

		wp_register_ability(
			'wordpress/plugin-network-deactivate',
			array(
				'label'               => __( 'Network Deactivate WordPress Plugin', 'wp-ability' ),
				'description'         => __( 'Network-deactivates one installed plugin using WordPress Core Multisite behavior.', 'wp-ability' ),
				'category'            => 'wordpress-admin',
				'input_schema'        => $this->plugin_file_schema(),
				'execute_callback'    => array( $this, 'plugin_network_deactivate' ),
				'permission_callback' => array( $this, 'can_manage_network_plugins' ),
				'meta'                => $this->meta( false, false, true ),
			)
		);
	}


	/**
	 * Register WordPress.org plugin discovery abilities.
	 *
	 * @return void
	 */
	private function register_plugin_discovery() {
		wp_register_ability(
			'wordpress/plugin-search',
			array(
				'label'               => __( 'Search WordPress Plugins', 'wp-ability' ),
				'description'         => __( 'Searches WordPress.org plugins through the WordPress Core Plugins API.', 'wp-ability' ),
				'category'            => 'wordpress-admin',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'search'   => array(
							'type'      => 'string',
							'minLength' => 1,
						),
						'page'     => array(
							'type'    => 'integer',
							'minimum' => 1,
							'default' => 1,
						),
						'per_page' => array(
							'type'    => 'integer',
							'minimum' => 1,
							'default' => 24,
						),
					),
					'required'             => array( 'search' ),
					'additionalProperties' => false,
				),
				'execute_callback'    => array( $this, 'plugin_search' ),
				'permission_callback' => array( $this, 'can_install_plugins' ),
				'meta'                => $this->meta( true, false, true, true ),
			)
		);

		wp_register_ability(
			'wordpress/plugin-get-information',
			array(
				'label'               => __( 'Get WordPress.org Plugin Information', 'wp-ability' ),
				'description'         => __( 'Gets WordPress.org plugin information through the WordPress Core Plugins API.', 'wp-ability' ),
				'category'            => 'wordpress-admin',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'slug' => array(
							'type'      => 'string',
							'minLength' => 1,
						),
					),
					'required'             => array( 'slug' ),
					'additionalProperties' => false,
				),
				'execute_callback'    => array( $this, 'plugin_get_information' ),
				'permission_callback' => array( $this, 'can_install_plugins' ),
				'meta'                => $this->meta( true, false, true, true ),
			)
		);
	}

	/**
	 * Register plugin inventory and lifecycle abilities.
	 *
	 * @return void
	 */
	private function register_plugin_management() {
		wp_register_ability(
			'wordpress/plugin-list',
			array(
				'label'               => __( 'List WordPress Plugins', 'wp-ability' ),
				'description'         => __( 'Lists installed plugins and their activation and update status.', 'wp-ability' ),
				'category'            => 'wordpress-admin',
				'input_schema'        => $this->empty_schema(),
				'execute_callback'    => array( $this, 'plugin_list' ),
				'permission_callback' => array( $this, 'can_activate_plugins' ),
				'meta'                => $this->meta( true, false, true ),
			)
		);
		wp_register_ability(
			'wordpress/plugin-activate',
			array(
				'label'               => __( 'Activate WordPress Plugin', 'wp-ability' ),
				'description'         => __( 'Activates one installed WordPress plugin.', 'wp-ability' ),
				'category'            => 'wordpress-admin',
				'input_schema'        => $this->plugin_file_schema(),
				'execute_callback'    => array( $this, 'plugin_activate' ),
				'permission_callback' => array( $this, 'can_activate_plugins' ),
				'meta'                => $this->meta( false, false, true ),
			)
		);
		wp_register_ability(
			'wordpress/plugin-deactivate',
			array(
				'label'               => __( 'Deactivate WordPress Plugin', 'wp-ability' ),
				'description'         => __( 'Deactivates one installed WordPress plugin.', 'wp-ability' ),
				'category'            => 'wordpress-admin',
				'input_schema'        => $this->plugin_file_schema(),
				'execute_callback'    => array( $this, 'plugin_deactivate' ),
				'permission_callback' => array( $this, 'can_activate_plugins' ),
				'meta'                => $this->meta( false, false, true ),
			)
		);
		wp_register_ability(
			'wordpress/plugin-get',
			array(
				'label'               => __( 'Get WordPress Plugin', 'wp-ability' ),
				'description'         => __( 'Returns metadata and current state for one installed WordPress plugin.', 'wp-ability' ),
				'category'            => 'wordpress-admin',
				'input_schema'        => $this->plugin_file_schema(),
				'execute_callback'    => array( $this, 'plugin_get' ),
				'permission_callback' => array( $this, 'can_activate_plugins' ),
				'meta'                => $this->meta( true, false, true ),
			)
		);
		wp_register_ability(
			'wordpress/plugin-mu-list',
			array(
				'label'               => __( 'List WordPress Must-Use Plugins', 'wp-ability' ),
				'description'         => __( 'Lists WordPress must-use plugins using the Core must-use plugin inventory.', 'wp-ability' ),
				'category'            => 'wordpress-admin',
				'input_schema'        => $this->empty_schema(),
				'execute_callback'    => array( $this, 'plugin_mu_list' ),
				'permission_callback' => array( $this, 'can_activate_plugins' ),
				'meta'                => $this->meta( true, false, true ),
			)
		);
		wp_register_ability(
			'wordpress/plugin-delete',
			array(
				'label'               => __( 'Delete WordPress Plugin', 'wp-ability' ),
				'description'         => __( 'Deletes one installed inactive WordPress plugin.', 'wp-ability' ),
				'category'            => 'wordpress-admin',
				'input_schema'        => $this->plugin_file_schema(),
				'execute_callback'    => array( $this, 'plugin_delete' ),
				'permission_callback' => array( $this, 'can_delete_plugins' ),
				'meta'                => $this->meta( false, true, true ),
			)
		);
	}

	/**
	 * Empty object schema.
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
	 * Plugin file input schema.
	 *
	 * @return array
	 */
	private function plugin_file_schema() {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'plugin_file' => array(
					'type'        => 'string',
					'minLength'   => 1,
					'description' => __( 'Installed plugin file path, for example "akismet/akismet.php".', 'wp-ability' ),
				),
			),
			'required'             => array( 'plugin_file' ),
			'additionalProperties' => false,
		);
	}

	/**
	 * Check plugin package installation permissions.
	 *
	 * @param array $input Ability input.
	 * @return bool
	 */
	public function can_install_plugin_package( $input = array() ) {
		if ( ! current_user_can( 'install_plugins' ) ) {
			return false;
		}
		if ( ! empty( $input['overwrite'] ) && ! current_user_can( 'update_plugins' ) ) {
			return false;
		}
		if ( ! empty( $input['activate'] ) && ! current_user_can( 'activate_plugins' ) ) {
			return false;
		}
		return true;
	}

	/**
	 * Check plugin installation permissions.
	 *
	 * @param array $input Ability input.
	 * @return bool
	 */
	public function can_install_plugins( $input = array() ) {
		if ( ! current_user_can( 'install_plugins' ) ) {
			return false;
		}
		if ( ! empty( $input['activate'] ) && ! current_user_can( 'activate_plugins' ) ) {
			return false;
		}
		return true;
	}

	/**
	 * Check plugin update permissions.
	 *
	 * @return bool
	 */
	public function can_update_plugins() {
		return current_user_can( 'update_plugins' );
	}

	/**
	 * Check plugin activation permissions.
	 *
	 * @return bool
	 */
	public function can_activate_plugins() {
		return current_user_can( 'activate_plugins' );
	}

	/**
	 * Check plugin deletion permissions.
	 *
	 * @return bool
	 */
	public function can_delete_plugins() {
		return current_user_can( 'delete_plugins' );
	}

	/**
	 * Check Multisite network plugin management permissions.
	 *
	 * @return bool
	 */
	public function can_manage_network_plugins() {
		return is_multisite() && current_user_can( 'manage_network_plugins' );
	}

	/**
	 * Install or overwrite a plugin from an HTTPS package URL.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public function plugin_install_package( array $input ) {
		if ( ! $this->plugin_package_installer ) {
			$this->plugin_package_installer = new Plugin_Package_Installer();
		}
		return $this->plugin_package_installer->install( $input );
	}

	/**
	 * Install a plugin from WordPress.org.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public function plugin_install( array $input ) {
		$slug = sanitize_key( $input['slug'] );
		if ( '' === $slug ) {
			return new \WP_Error( 'wp_ability_invalid_plugin_slug', __( 'A valid plugin slug is required.', 'wp-ability' ) );
		}

		require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		$info = plugins_api(
			'plugin_information',
			array(
				'slug'   => $slug,
				'fields' => array( 'sections' => false ),
			)
		);
		if ( is_wp_error( $info ) ) {
			return $info;
		}
		if ( empty( $info->download_link ) ) {
			return new \WP_Error( 'wp_ability_plugin_download_unavailable', __( 'The plugin download URL is unavailable.', 'wp-ability' ) );
		}

		$skin     = new \Automatic_Upgrader_Skin();
		$upgrader = new \Plugin_Upgrader( $skin );
		$result   = $upgrader->install( $info->download_link );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( false === $result ) {
			return new \WP_Error( 'wp_ability_plugin_install_failed', __( 'Plugin installation failed.', 'wp-ability' ) );
		}

		$plugin_file = $upgrader->plugin_info();
		if ( ! $plugin_file ) {
			return new \WP_Error( 'wp_ability_plugin_file_unknown', __( 'The installed plugin file could not be determined.', 'wp-ability' ) );
		}

		$activated = false;
		if ( ! empty( $input['activate'] ) ) {
			$activation = activate_plugin( $plugin_file );
			if ( is_wp_error( $activation ) ) {
				return $activation;
			}
			$activated = true;
		}

		return array(
			'slug'        => $slug,
			'plugin_file' => $plugin_file,
			'installed'   => true,
			'activated'   => $activated,
		);
	}

	/**
	 * Update an installed plugin.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public function plugin_update( array $input ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

		$plugin_file = plugin_basename( sanitize_text_field( $input['plugin_file'] ) );
		$plugins     = get_plugins();
		if ( ! isset( $plugins[ $plugin_file ] ) ) {
			return new \WP_Error( 'wp_ability_plugin_not_found', __( 'The requested plugin is not installed.', 'wp-ability' ) );
		}

		wp_update_plugins();
		$skin     = new \WP_Ajax_Upgrader_Skin();
		$upgrader = new \Plugin_Upgrader( $skin );
		$result   = $upgrader->bulk_upgrade( array( $plugin_file ) );

		if ( is_wp_error( $skin->result ) ) {
			return $skin->result;
		}

		if ( false === $result || empty( $result[ $plugin_file ] ) || true === $result[ $plugin_file ] ) {
			return new \WP_Error( 'wp_ability_plugin_update_failed', __( 'Plugin update failed or no update was available.', 'wp-ability' ) );
		}

		if ( is_wp_error( $result[ $plugin_file ] ) ) {
			return $result[ $plugin_file ];
		}

		return array(
			'plugin_file' => $plugin_file,
			'updated'     => true,
		);
	}


	/**
	 * Refresh and return WordPress Core plugin update metadata.
	 *
	 * @return array
	 */
	public function plugin_check_updates() {
		require_once ABSPATH . 'wp-admin/includes/update.php';

		wp_update_plugins();

		return array(
			'updates' => json_decode( wp_json_encode( get_site_transient( 'update_plugins' ) ), true ),
		);
	}

	/**
	 * Update multiple installed plugins using the Core bulk upgrader.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public function plugin_update_many( array $input ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

		$installed    = get_plugins();
		$plugin_files = array();

		foreach ( $input['plugin_files'] as $plugin_file ) {
			$plugin_file = plugin_basename( sanitize_text_field( $plugin_file ) );

			if ( ! isset( $installed[ $plugin_file ] ) ) {
				return new \WP_Error(
					'wp_ability_plugin_not_found',
					__( 'One or more requested plugins are not installed.', 'wp-ability' )
				);
			}

			$plugin_files[] = $plugin_file;
		}

		$plugin_files = array_values( array_unique( $plugin_files ) );

		wp_update_plugins();

		$skin     = new \Automatic_Upgrader_Skin();
		$upgrader = new \Plugin_Upgrader( $skin );
		$result   = $upgrader->bulk_upgrade( $plugin_files );

		if ( false === $result ) {
			return new \WP_Error(
				'wp_ability_plugin_bulk_update_failed',
				__( 'Plugin bulk update failed.', 'wp-ability' )
			);
		}

		return array(
			'plugin_files' => $plugin_files,
			'results'      => json_decode( wp_json_encode( $result ), true ),
		);
	}


	/**
	 * Resolve an installed plugin file for auto-update controls.
	 *
	 * @param array $input Ability input.
	 * @return string|\WP_Error
	 */
	private function installed_plugin_file( array $input ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		$plugin_file = plugin_basename( sanitize_text_field( $input['plugin_file'] ) );
		if ( ! isset( get_plugins()[ $plugin_file ] ) ) {
			return new \WP_Error( 'wp_ability_plugin_not_found', __( 'Plugin not found.', 'wp-ability' ) );
		}

		return $plugin_file;
	}

	/**
	 * Enable WordPress Core auto-updates for one plugin.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public function plugin_enable_auto_update( array $input ) {
		$plugin_file = $this->installed_plugin_file( $input );
		if ( is_wp_error( $plugin_file ) ) {
			return $plugin_file;
		}

		$auto_updates = (array) get_site_option( 'auto_update_plugins', array() );
		if ( ! in_array( $plugin_file, $auto_updates, true ) ) {
			$auto_updates[] = $plugin_file;
			update_site_option( 'auto_update_plugins', array_values( $auto_updates ) );
		}

		return array(
			'plugin_file'         => $plugin_file,
			'auto_update_enabled' => true,
		);
	}

	/**
	 * Disable WordPress Core auto-updates for one plugin.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public function plugin_disable_auto_update( array $input ) {
		$plugin_file = $this->installed_plugin_file( $input );
		if ( is_wp_error( $plugin_file ) ) {
			return $plugin_file;
		}

		$auto_updates = array_values(
			array_diff(
				(array) get_site_option( 'auto_update_plugins', array() ),
				array( $plugin_file )
			)
		);
		update_site_option( 'auto_update_plugins', $auto_updates );

		return array(
			'plugin_file'         => $plugin_file,
			'auto_update_enabled' => false,
		);
	}


	/**
	 * Normalize and validate installed plugin files.
	 *
	 * @param array $plugin_files Plugin files.
	 * @return array|\WP_Error
	 */
	private function installed_plugin_files( array $plugin_files ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		$installed = get_plugins();
		$files     = array();

		foreach ( $plugin_files as $plugin_file ) {
			$plugin_file = plugin_basename( sanitize_text_field( $plugin_file ) );
			if ( ! isset( $installed[ $plugin_file ] ) ) {
				return new \WP_Error(
					'wp_ability_plugin_not_found',
					__( 'One or more requested plugins are not installed.', 'wp-ability' )
				);
			}
			$files[] = $plugin_file;
		}

		return array_values( array_unique( $files ) );
	}

	/**
	 * Activate multiple plugins using WordPress Core.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public function plugin_activate_many( array $input ) {
		$plugin_files = $this->installed_plugin_files( $input['plugin_files'] );
		if ( is_wp_error( $plugin_files ) ) {
			return $plugin_files;
		}

		$result = activate_plugins( $plugin_files );

		return array(
			'plugin_files' => $plugin_files,
			'results'      => json_decode( wp_json_encode( $result ), true ),
		);
	}

	/**
	 * Deactivate multiple plugins using WordPress Core.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public function plugin_deactivate_many( array $input ) {
		$plugin_files = $this->installed_plugin_files( $input['plugin_files'] );
		if ( is_wp_error( $plugin_files ) ) {
			return $plugin_files;
		}

		deactivate_plugins( $plugin_files );

		return array(
			'plugin_files' => $plugin_files,
			'deactivated'  => true,
		);
	}

	/**
	 * Delete multiple inactive plugins using WordPress Core.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public function plugin_delete_many( array $input ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		$plugin_files = $this->installed_plugin_files( $input['plugin_files'] );
		if ( is_wp_error( $plugin_files ) ) {
			return $plugin_files;
		}

		foreach ( $plugin_files as $plugin_file ) {
			if ( is_plugin_active( $plugin_file ) || ( is_multisite() && is_plugin_active_for_network( $plugin_file ) ) ) {
				return new \WP_Error(
					'wp_ability_plugin_active',
					__( 'Deactivate all selected plugins before deleting them.', 'wp-ability' )
				);
			}
		}

		$result = delete_plugins( $plugin_files );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array(
			'plugin_files' => $plugin_files,
			'deleted'      => true,
		);
	}


	/**
	 * Return a Multisite-required error.
	 *
	 * @return \WP_Error
	 */
	private function multisite_required_error() {
		return new \WP_Error(
			'wp_ability_multisite_required',
			__( 'This plugin operation requires WordPress Multisite.', 'wp-ability' )
		);
	}

	/**
	 * Network activate one installed plugin using WordPress Core.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public function plugin_network_activate( array $input ) {
		if ( ! is_multisite() ) {
			return $this->multisite_required_error();
		}

		$plugin_file = $this->installed_plugin_file( $input );
		if ( is_wp_error( $plugin_file ) ) {
			return $plugin_file;
		}

		$result = activate_plugin( $plugin_file, '', true );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array(
			'plugin_file'    => $plugin_file,
			'network_active' => is_plugin_active_for_network( $plugin_file ),
		);
	}

	/**
	 * Network deactivate one installed plugin using WordPress Core.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public function plugin_network_deactivate( array $input ) {
		if ( ! is_multisite() ) {
			return $this->multisite_required_error();
		}

		$plugin_file = $this->installed_plugin_file( $input );
		if ( is_wp_error( $plugin_file ) ) {
			return $plugin_file;
		}

		deactivate_plugins( $plugin_file, false, true );

		return array(
			'plugin_file'    => $plugin_file,
			'network_active' => is_plugin_active_for_network( $plugin_file ),
		);
	}


	/**
	 * Normalize a WordPress Plugins API result for ability output.
	 *
	 * @param mixed $result Plugins API result.
	 * @return mixed
	 */
	private function normalize_plugins_api_result( $result ) {
		return json_decode( wp_json_encode( $result ), true );
	}

	/**
	 * Search WordPress.org plugins through Core.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public function plugin_search( array $input ) {
		require_once ABSPATH . 'wp-admin/includes/plugin-install.php';

		$result = plugins_api(
			'query_plugins',
			array(
				'search'   => sanitize_text_field( $input['search'] ),
				'page'     => isset( $input['page'] ) ? max( 1, (int) $input['page'] ) : 1,
				'per_page' => isset( $input['per_page'] ) ? max( 1, (int) $input['per_page'] ) : 24,
			)
		);

		return is_wp_error( $result ) ? $result : $this->normalize_plugins_api_result( $result );
	}

	/**
	 * Get WordPress.org plugin information through Core.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public function plugin_get_information( array $input ) {
		require_once ABSPATH . 'wp-admin/includes/plugin-install.php';

		$slug = sanitize_key( $input['slug'] );
		if ( '' === $slug ) {
			return new \WP_Error( 'wp_ability_invalid_plugin_slug', __( 'A valid plugin slug is required.', 'wp-ability' ) );
		}

		$result = plugins_api(
			'plugin_information',
			array(
				'slug'   => $slug,
				'fields' => array(
					'sections' => false,
				),
			)
		);

		return is_wp_error( $result ) ? $result : $this->normalize_plugins_api_result( $result );
	}

	/**
	 * Normalize installed plugin metadata using WordPress Core data.
	 *
	 * @param string      $file Plugin file.
	 * @param array       $data Plugin header data.
	 * @param object|bool $updates Plugin update transient.
	 * @return array
	 */
	private function plugin_payload( $file, array $data, $updates ) {
		\WP_Plugin_Dependencies::initialize();
		$auto_updates = (array) get_site_option( 'auto_update_plugins', array() );

		return array(
			'plugin_file'              => $file,
			'name'                     => $data['Name'],
			'version'                  => $data['Version'],
			'author'                   => $data['Author'],
			'description'              => $data['Description'],
			'active'                   => is_plugin_active( $file ),
			'network_active'           => is_multisite() && is_plugin_active_for_network( $file ),
			'update_available'         => is_object( $updates ) && isset( $updates->response[ $file ] ),
			'auto_update_enabled'      => in_array( $file, $auto_updates, true ),
			'dependencies'             => \WP_Plugin_Dependencies::get_dependencies( $file ),
			'has_unmet_dependencies'   => \WP_Plugin_Dependencies::has_unmet_dependencies( $file ),
			'has_dependents'           => \WP_Plugin_Dependencies::has_dependents( $file ),
			'has_active_dependents'    => \WP_Plugin_Dependencies::has_active_dependents( $file ),
		);
	}

	/**
	 * List installed plugins.
	 *
	 * @return array
	 */
	public function plugin_list() {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		$updates = get_site_transient( 'update_plugins' );
		$result  = array();

		foreach ( get_plugins() as $file => $data ) {
			$result[] = $this->plugin_payload( $file, $data, $updates );
		}

		return array( 'plugins' => $result );
	}

	/**
	 * Get one installed plugin.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public function plugin_get( array $input ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		$file    = plugin_basename( sanitize_text_field( $input['plugin_file'] ) );
		$plugins = get_plugins();

		if ( ! isset( $plugins[ $file ] ) ) {
			return new \WP_Error( 'wp_ability_plugin_not_found', __( 'Plugin not found.', 'wp-ability' ) );
		}

		return $this->plugin_payload( $file, $plugins[ $file ], get_site_transient( 'update_plugins' ) );
	}

	/**
	 * List must-use plugins.
	 *
	 * @return array
	 */
	public function plugin_mu_list() {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		$result = array();

		foreach ( get_mu_plugins() as $file => $data ) {
			$result[] = array(
				'plugin_file' => $file,
				'name'        => $data['Name'],
				'version'     => $data['Version'],
				'author'      => $data['Author'],
				'description' => $data['Description'],
				'must_use'    => true,
			);
		}

		return array( 'plugins' => $result );
	}

	/**
	 * Activate one installed plugin.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public function plugin_activate( array $input ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		$file = plugin_basename( sanitize_text_field( $input['plugin_file'] ) );
		if ( ! isset( get_plugins()[ $file ] ) ) {
			return new \WP_Error( 'wp_ability_plugin_not_found', __( 'Plugin not found.', 'wp-ability' ) );
		}
		$result = activate_plugin( $file );
		return is_wp_error( $result ) ? $result : array(
			'plugin_file' => $file,
			'active'      => true,
		);
	}

	/**
	 * Deactivate one installed plugin.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public function plugin_deactivate( array $input ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		$file = plugin_basename( sanitize_text_field( $input['plugin_file'] ) );
		if ( ! isset( get_plugins()[ $file ] ) ) {
			return new \WP_Error( 'wp_ability_plugin_not_found', __( 'Plugin not found.', 'wp-ability' ) );
		}
		deactivate_plugins( $file );
		return array(
			'plugin_file' => $file,
			'active'      => false,
		);
	}

	/**
	 * Delete one installed inactive plugin.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public function plugin_delete( array $input ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		$file = plugin_basename( sanitize_text_field( $input['plugin_file'] ) );
		if ( ! isset( get_plugins()[ $file ] ) ) {
			return new \WP_Error( 'wp_ability_plugin_not_found', __( 'Plugin not found.', 'wp-ability' ) );
		}
		if ( is_plugin_active( $file ) ) {
			return new \WP_Error( 'wp_ability_plugin_active', __( 'Deactivate the plugin before deleting it.', 'wp-ability' ) );
		}
		$result = delete_plugins( array( $file ) );
		return is_wp_error( $result ) ? $result : array(
			'plugin_file' => $file,
			'deleted'     => true,
		);
	}
}
