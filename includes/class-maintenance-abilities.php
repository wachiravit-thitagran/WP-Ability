<?php
/**
 * Maintenance Abilities.
 *
 * @package WordPress_Abilities_Bridge
 */

namespace WP_Ability;

defined( 'ABSPATH' ) || exit;

/**
 * Focused WordPress ability domain.
 */
final class Maintenance_Abilities extends Domain_Abilities_Base {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'wp_abilities_api_init', array( $this, 'register_abilities' ) );
	}

	/**
	 * Register this domain's abilities.
	 *
	 * @return void
	 */
	public function register_abilities() {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		$this->register_cache_flush();
		$this->register_database_optimize();
		$this->register_maintenance();
	}

	/**
	 * Register cache flush ability.
	 *
	 * @return void
	 */
	private function register_cache_flush() {
		Ability_Registrar::register(
			'wordpress/cache-flush',
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
		Ability_Registrar::register(
			'wordpress/database-optimize',
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

	/**
	 * Check whether the current user can perform this ability.
	 *
	 * @return bool
	 */
	public function can_manage_options() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Register maintenance abilities.
	 *
	 * @return void
	 */
	private function register_maintenance() {
		$this->register(
			'wordpress/rewrite-flush',
			__( 'Flush WordPress Rewrite Rules', 'wp-ability' ),
			__( 'Regenerates WordPress rewrite rules.', 'wp-ability' ),
			array(
				'type' => 'object',
				'properties' => array(
					'hard' => array(
						'type' => 'boolean',
						'default' => true,
					),
				),
				'additionalProperties' => false,
			),
			array( $this, 'rewrite_flush' ),
			array( $this, 'can_manage_options' ),
			false,
			false,
			true
		);
		$this->register( 'wordpress/update-check', __( 'Check WordPress Updates', 'wp-ability' ), __( 'Refreshes WordPress core, plugin, and theme update information.', 'wp-ability' ), $this->empty_schema(), array( $this, 'update_check' ), array( $this, 'can_update_core' ), false, false, true, true );
	}

	/**
	 * Check whether the current user can perform this ability.
	 *
	 * @return bool
	 */
	public function can_update_core() {
		return current_user_can( 'update_core' );
	}

	/**
	 * Execute the rewrite flush ability.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public function rewrite_flush( array $input ) {
		$hard = ! array_key_exists( 'hard', $input ) || ! empty( $input['hard'] );
		flush_rewrite_rules( $hard );
		return array(
			'flushed' => true,
			'hard' => $hard,
		);
	}

	/**
	 * Refresh and return WordPress update information.
	 *
	 * @return array
	 */
	public function update_check() {
		require_once ABSPATH . 'wp-admin/includes/update.php';
		wp_version_check();
		wp_update_plugins();
		wp_update_themes();
		return array(
			'core'    => get_site_transient( 'update_core' ),
			'plugins' => get_site_transient( 'update_plugins' ),
			'themes'  => get_site_transient( 'update_themes' ),
		);
	}
}
