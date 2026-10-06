<?php
/**
 * WordPress Abilities API registrations and executors.
 *
 * @package WP_Ability
 */

namespace WP_Ability;

defined( 'ABSPATH' ) || exit;

/**
 * Registers administrative WordPress abilities.
 */
final class Abilities {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'wp_abilities_api_categories_init', array( $this, 'register_category' ) );
		add_action( 'wp_abilities_api_init', array( $this, 'register_abilities' ) );
		add_action( 'admin_notices', array( $this, 'maybe_show_dependency_notice' ) );
	}

	/**
	 * Show an admin notice when the Abilities API is unavailable.
	 *
	 * @return void
	 */
	public function maybe_show_dependency_notice() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		if ( function_exists( 'wp_register_ability' ) && function_exists( 'wp_register_ability_category' ) ) {
			return;
		}

		echo '<div class="notice notice-warning"><p>';
		echo esc_html__( 'WP Ability requires a WordPress version that provides the WordPress Abilities API. The plugin remains inactive until the API is available.', 'wp-ability' );
		echo '</p></div>';
	}

	/**
	 * Register the ability category.
	 *
	 * @return void
	 */
	public function register_category() {
		if ( ! function_exists( 'wp_register_ability_category' ) ) {
			return;
		}

		wp_register_ability_category(
			'wordpress-admin',
			array(
				'label'       => __( 'WordPress Administration', 'wp-ability' ),
				'description' => __( 'Administrative operations for plugins, users, options, media, scheduled tasks, caches, and database maintenance.', 'wp-ability' ),
			)
		);
	}

	/**
	 * Register all abilities.
	 *
	 * @return void
	 */
	public function register_abilities() {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		$this->register_plugin_install();
		$this->register_plugin_update();
		$this->register_user_create();
		$this->register_option_update();
		$this->register_media_delete();
		$this->register_cron_run();
		$this->register_cache_flush();
		$this->register_database_optimize();
	}

	/**
	 * Metadata shared by abilities.
	 *
	 * @param bool $readonly    Whether the operation is read-only.
	 * @param bool $destructive Whether the operation can delete or irreversibly alter data.
	 * @param bool $idempotent  Whether repeating the same request is expected to be safe.
	 * @param bool $open_world  Whether the operation may interact with external systems.
	 * @return array
	 */
	private function meta( $readonly, $destructive, $idempotent, $open_world = false ) {
		return array(
			'public'      => true,
			'annotations' => array(
				'readonly'      => (bool) $readonly,
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
			'wordpress/plugins/install',
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
	 * Check plugin-install permissions.
	 *
	 * @param array $input Ability input.
	 * @return bool
	 */
	public function can_install_plugins( $input = array() ) {
		$activate = ! empty( $input['activate'] );

		if ( ! current_user_can( 'install_plugins' ) ) {
			return false;
		}

		if ( $activate && ! current_user_can( 'activate_plugins' ) ) {
			return false;
		}

		return true;
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
				'fields' => array(
					'sections' => false,
				),
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
	 * Register plugin update ability.
	 *
	 * @return void
	 */
	private function register_plugin_update() {
		wp_register_ability(
			'wordpress/plugins/update',
			array(
				'label'               => __( 'Update WordPress Plugin', 'wp-ability' ),
				'description'         => __( 'Updates one installed WordPress plugin using its registered plugin file path.', 'wp-ability' ),
				'category'            => 'wordpress-admin',
				'input_schema'        => array(
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
				),
				'execute_callback'    => array( $this, 'plugin_update' ),
				'permission_callback' => array( $this, 'can_update_plugins' ),
				'meta'                => $this->meta( false, false, false, true ),
			)
		);
	}

	/**
	 * Check plugin-update permissions.
	 *
	 * @return bool
	 */
	public function can_update_plugins() {
		return current_user_can( 'update_plugins' );
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

		$skin     = new \Automatic_Upgrader_Skin();
		$upgrader = new \Plugin_Upgrader( $skin );
		$result   = $upgrader->upgrade( $plugin_file );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		if ( false === $result ) {
			return new \WP_Error( 'wp_ability_plugin_update_failed', __( 'Plugin update failed or no update was available.', 'wp-ability' ) );
		}

		return array(
			'plugin_file' => $plugin_file,
			'updated'     => true,
		);
	}

	/**
	 * Register user creation ability.
	 *
	 * @return void
	 */
	private function register_user_create() {
		wp_register_ability(
			'wordpress/users/create',
			array(
				'label'               => __( 'Create WordPress User', 'wp-ability' ),
				'description'         => __( 'Creates a WordPress user with a username, email address, optional profile fields, and an editable site role.', 'wp-ability' ),
				'category'            => 'wordpress-admin',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'username'     => array( 'type' => 'string', 'minLength' => 1 ),
						'email'        => array( 'type' => 'string', 'format' => 'email' ),
						'password'     => array(
							'type'        => 'string',
							'minLength'   => 8,
							'description' => __( 'Optional password. When omitted, WordPress generates a strong password. Passwords are never returned by this ability.', 'wp-ability' ),
						),
						'role'         => array( 'type' => 'string', 'default' => 'subscriber' ),
						'display_name' => array( 'type' => 'string' ),
						'first_name'   => array( 'type' => 'string' ),
						'last_name'    => array( 'type' => 'string' ),
					),
					'required'             => array( 'username', 'email' ),
					'additionalProperties' => false,
				),
				'execute_callback'    => array( $this, 'user_create' ),
				'permission_callback' => array( $this, 'can_create_users' ),
				'meta'                => $this->meta( false, false, false, false ),
			)
		);
	}

	/**
	 * Check user-create permissions.
	 *
	 * @return bool
	 */
	public function can_create_users() {
		return current_user_can( 'create_users' );
	}

	/**
	 * Create a WordPress user.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public function user_create( array $input ) {
		require_once ABSPATH . 'wp-admin/includes/user.php';

		$username = sanitize_user( $input['username'], true );
		$email    = sanitize_email( $input['email'] );
		$role     = isset( $input['role'] ) ? sanitize_key( $input['role'] ) : 'subscriber';

		if ( '' === $username || ! is_email( $email ) ) {
			return new \WP_Error( 'wp_ability_invalid_user', __( 'A valid username and email address are required.', 'wp-ability' ) );
		}

		$editable_roles = get_editable_roles();

		if ( ! isset( $editable_roles[ $role ] ) ) {
			return new \WP_Error( 'wp_ability_role_not_allowed', __( 'The requested role is not editable by the current user.', 'wp-ability' ) );
		}

		if ( 'subscriber' !== $role && ! current_user_can( 'promote_users' ) ) {
			return new \WP_Error( 'wp_ability_cannot_assign_role', __( 'The current user cannot assign the requested role.', 'wp-ability' ) );
		}

		$user_data = array(
			'user_login'   => $username,
			'user_email'   => $email,
			'user_pass'    => isset( $input['password'] ) && '' !== $input['password'] ? $input['password'] : wp_generate_password( 24, true, true ),
			'role'         => $role,
			'display_name' => isset( $input['display_name'] ) ? sanitize_text_field( $input['display_name'] ) : $username,
			'first_name'   => isset( $input['first_name'] ) ? sanitize_text_field( $input['first_name'] ) : '',
			'last_name'    => isset( $input['last_name'] ) ? sanitize_text_field( $input['last_name'] ) : '',
		);

		$user_id = wp_insert_user( $user_data );

		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}

		return array(
			'user_id'      => (int) $user_id,
			'username'     => $username,
			'email'        => $email,
			'role'         => $role,
			'display_name' => $user_data['display_name'],
		);
	}

	/**
	 * Register option update ability.
	 *
	 * @return void
	 */
	private function register_option_update() {
		wp_register_ability(
			'wordpress/options/update',
			array(
				'label'               => __( 'Update WordPress Option', 'wp-ability' ),
				'description'         => __( 'Updates a WordPress option with a JSON-compatible scalar, array, object, or null value without returning the previous option value.', 'wp-ability' ),
				'category'            => 'wordpress-admin',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'option_name' => array( 'type' => 'string', 'minLength' => 1 ),
						'value'       => array(),
						'autoload'    => array(
							'type'        => array( 'boolean', 'null' ),
							'description' => __( 'Optional autoload setting. Omit or set null to keep WordPress default behavior.', 'wp-ability' ),
						),
					),
					'required'             => array( 'option_name', 'value' ),
					'additionalProperties' => false,
				),
				'execute_callback'    => array( $this, 'option_update' ),
				'permission_callback' => array( $this, 'can_manage_options' ),
				'meta'                => $this->meta( false, true, true, false ),
			)
		);
	}

	/**
	 * Check manage-options permission.
	 *
	 * @return bool
	 */
	public function can_manage_options() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Update a WordPress option.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public function option_update( array $input ) {
		$option_name = sanitize_key( $input['option_name'] );

		if ( '' === $option_name ) {
			return new \WP_Error( 'wp_ability_invalid_option_name', __( 'A valid option name is required.', 'wp-ability' ) );
		}

		$blocked = apply_filters(
			'wp_ability_protected_options',
			array(
				'active_plugins',
				'cron',
				'stylesheet',
				'template',
			)
		);

		if ( in_array( $option_name, $blocked, true ) ) {
			return new \WP_Error( 'wp_ability_protected_option', __( 'This option must be managed through a dedicated WordPress operation instead of direct option updates.', 'wp-ability' ) );
		}

		$value = $this->sanitize_json_value( $input['value'] );

		if ( is_wp_error( $value ) ) {
			return $value;
		}

		$autoload = isset( $input['autoload'] ) && is_bool( $input['autoload'] ) ? $input['autoload'] : null;
		$updated  = null === $autoload ? update_option( $option_name, $value ) : update_option( $option_name, $value, $autoload );

		return array(
			'option_name' => $option_name,
			'updated'     => (bool) $updated,
			'value_type'  => gettype( $value ),
		);
	}

	/**
	 * Sanitize JSON-compatible option data recursively.
	 *
	 * @param mixed $value Value supplied by the caller.
	 * @return mixed|\WP_Error
	 */
	private function sanitize_json_value( $value ) {
		if ( is_null( $value ) || is_bool( $value ) || is_int( $value ) || is_float( $value ) ) {
			return $value;
		}

		if ( is_string( $value ) ) {
			return wp_kses_post( $value );
		}

		if ( is_array( $value ) ) {
			$sanitized = array();

			foreach ( $value as $key => $child ) {
				$sanitized_key = is_int( $key ) ? $key : sanitize_key( $key );
				$result        = $this->sanitize_json_value( $child );

				if ( is_wp_error( $result ) ) {
					return $result;
				}

				$sanitized[ $sanitized_key ] = $result;
			}

			return $sanitized;
		}

		return new \WP_Error( 'wp_ability_invalid_option_value', __( 'Option values must be JSON-compatible and cannot contain PHP objects or resources.', 'wp-ability' ) );
	}

	/**
	 * Register media deletion ability.
	 *
	 * @return void
	 */
	private function register_media_delete() {
		wp_register_ability(
			'wordpress/media/delete',
			array(
				'label'               => __( 'Delete WordPress Media', 'wp-ability' ),
				'description'         => __( 'Deletes a media attachment, optionally bypassing the Trash and permanently removing its files.', 'wp-ability' ),
				'category'            => 'wordpress-admin',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'attachment_id' => array( 'type' => 'integer', 'minimum' => 1 ),
						'force'         => array( 'type' => 'boolean', 'default' => false ),
					),
					'required'             => array( 'attachment_id' ),
					'additionalProperties' => false,
				),
				'execute_callback'    => array( $this, 'media_delete' ),
				'permission_callback' => array( $this, 'can_delete_media' ),
				'meta'                => $this->meta( false, true, true, false ),
			)
		);
	}

	/**
	 * Check media deletion permission.
	 *
	 * @param array $input Ability input.
	 * @return bool
	 */
	public function can_delete_media( $input = array() ) {
		$attachment_id = isset( $input['attachment_id'] ) ? (int) $input['attachment_id'] : 0;

		return $attachment_id > 0 && current_user_can( 'delete_post', $attachment_id );
	}

	/**
	 * Delete a media attachment.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public function media_delete( array $input ) {
		$attachment_id = (int) $input['attachment_id'];
		$force         = ! empty( $input['force'] );
		$post          = get_post( $attachment_id );

		if ( ! $post || 'attachment' !== $post->post_type ) {
			return new \WP_Error( 'wp_ability_attachment_not_found', __( 'The requested media attachment was not found.', 'wp-ability' ) );
		}

		$result = wp_delete_attachment( $attachment_id, $force );

		if ( false === $result || null === $result ) {
			return new \WP_Error( 'wp_ability_media_delete_failed', __( 'The media attachment could not be deleted.', 'wp-ability' ) );
		}

		return array(
			'attachment_id' => $attachment_id,
			'deleted'       => true,
			'force'         => $force,
		);
	}

	/**
	 * Register cron run ability.
	 *
	 * @return void
	 */
	private function register_cron_run() {
		wp_register_ability(
			'wordpress/cron/run',
			array(
				'label'               => __( 'Run Scheduled WordPress Event', 'wp-ability' ),
				'description'         => __( 'Runs one existing WordPress cron event identified by hook, timestamp, and optional arguments, then updates its schedule consistently with WordPress cron behavior.', 'wp-ability' ),
				'category'            => 'wordpress-admin',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'hook'      => array( 'type' => 'string', 'minLength' => 1 ),
						'timestamp' => array( 'type' => 'integer', 'minimum' => 1 ),
						'args'      => array( 'type' => 'array', 'default' => array() ),
					),
					'required'             => array( 'hook', 'timestamp' ),
					'additionalProperties' => false,
				),
				'execute_callback'    => array( $this, 'cron_run' ),
				'permission_callback' => array( $this, 'can_manage_options' ),
				'meta'                => $this->meta( false, true, false, false ),
			)
		);
	}

	/**
	 * Run one scheduled event.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public function cron_run( array $input ) {
		$hook      = sanitize_key( $input['hook'] );
		$timestamp = (int) $input['timestamp'];
		$args      = isset( $input['args'] ) && is_array( $input['args'] ) ? $input['args'] : array();

		$event = wp_get_scheduled_event( $hook, $args, $timestamp );

		if ( ! $event ) {
			return new \WP_Error( 'wp_ability_cron_event_not_found', __( 'The requested scheduled event was not found.', 'wp-ability' ) );
		}

		if ( ! empty( $event->schedule ) ) {
			$rescheduled = wp_reschedule_event( $event->timestamp, $event->schedule, $event->hook, $event->args, true );

			if ( is_wp_error( $rescheduled ) ) {
				return $rescheduled;
			}
		}

		$unscheduled = wp_unschedule_event( $event->timestamp, $event->hook, $event->args, true );

		if ( is_wp_error( $unscheduled ) ) {
			return $unscheduled;
		}

		do_action_ref_array( $event->hook, $event->args );

		return array(
			'hook'      => $event->hook,
			'timestamp' => (int) $event->timestamp,
			'ran'       => true,
			'recurring' => ! empty( $event->schedule ),
		);
	}

	/**
	 * Register cache flush ability.
	 *
	 * @return void
	 */
	private function register_cache_flush() {
		wp_register_ability(
			'wordpress/cache/flush',
			array(
				'label'               => __( 'Flush WordPress Object Cache', 'wp-ability' ),
				'description'         => __( 'Flushes the WordPress object cache using the active object-cache implementation.', 'wp-ability' ),
				'category'            => 'wordpress-admin',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(),
					'additionalProperties' => false,
				),
				'execute_callback'    => array( $this, 'cache_flush' ),
				'permission_callback' => array( $this, 'can_manage_options' ),
				'meta'                => $this->meta( false, false, true, false ),
			)
		);
	}

	/**
	 * Flush the WordPress object cache.
	 *
	 * @return array
	 */
	public function cache_flush() {
		$result = wp_cache_flush();

		return array(
			'flushed' => (bool) $result,
		);
	}

	/**
	 * Register database optimization ability.
	 *
	 * @return void
	 */
	private function register_database_optimize() {
		wp_register_ability(
			'wordpress/database/optimize',
			array(
				'label'               => __( 'Optimize WordPress Database Tables', 'wp-ability' ),
				'description'         => __( 'Runs database table optimization for selected WordPress-managed tables or all WordPress-managed tables on the current site.', 'wp-ability' ),
				'category'            => 'wordpress-admin',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'tables' => array(
							'type'        => 'array',
							'items'       => array( 'type' => 'string' ),
							'uniqueItems' => true,
							'description' => __( 'Optional WordPress table names. When omitted, all WordPress-managed tables are optimized.', 'wp-ability' ),
						),
					),
					'additionalProperties' => false,
				),
				'execute_callback'    => array( $this, 'database_optimize' ),
				'permission_callback' => array( $this, 'can_manage_options' ),
				'meta'                => $this->meta( false, false, true, false ),
			)
		);
	}

	/**
	 * Optimize selected WordPress database tables.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public function database_optimize( array $input ) {
		global $wpdb;

		$allowed_tables = array_values( array_unique( array_filter( $wpdb->tables( 'all', true ) ) ) );
		$requested      = isset( $input['tables'] ) && is_array( $input['tables'] ) ? array_values( array_unique( $input['tables'] ) ) : $allowed_tables;

		foreach ( $requested as $table ) {
			if ( ! in_array( $table, $allowed_tables, true ) ) {
				return new \WP_Error(
					'wp_ability_database_table_not_allowed',
					sprintf(
						/* translators: %s is a database table name. */
						__( 'The table "%s" is not a WordPress-managed table for this site.', 'wp-ability' ),
						$table
					)
				);
			}
		}

		$results = array();

		foreach ( $requested as $table ) {
			$query = 'OPTIMIZE TABLE `' . str_replace( '`', '``', $table ) . '`'; // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$rows  = $wpdb->get_results( $query, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

			if ( null === $rows ) {
				$results[] = array(
					'table'   => $table,
					'success' => false,
					'message' => $wpdb->last_error,
				);
				continue;
			}

			$results[] = array(
				'table'   => $table,
				'success' => true,
				'message' => isset( $rows[0]['Msg_text'] ) ? $rows[0]['Msg_text'] : '',
			);
		}

		return array(
			'optimized' => $results,
		);
	}
}
