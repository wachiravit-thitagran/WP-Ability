<?php
/**
 * Option Abilities.
 *
 * @package WordPress_Abilities_Bridge
 */

namespace WP_Ability;

defined( 'ABSPATH' ) || exit;

/**
 * Focused WordPress ability domain.
 */
final class Option_Abilities extends Domain_Abilities_Base {

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

		$this->register_option_update();
		$this->register_options();
	}

	/**
	 * Register option update ability.
	 *
	 * @return void
	 */
	private function register_option_update() {
		Ability_Registrar::register(
			'wordpress/option-update',
			array(
				'label'               => __( 'Update WordPress Option', 'wp-ability' ),
				'description'         => __( 'Updates a WordPress option with a JSON-compatible scalar, array, object, or null value without returning the previous option value.', 'wp-ability' ),
				'category'            => 'wordpress-admin',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'option_name' => array(
							'type'      => 'string',
							'minLength' => 1,
						),
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
	 * Check whether the current user can perform this ability.
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
	 * Register option abilities.
	 *
	 * @return void
	 */
	private function register_options() {
		$name_schema = array(
			'type' => 'object',
			'properties' => array(
				'option_name' => array(
					'type' => 'string',
					'minLength' => 1,
				),
			),
			'required' => array( 'option_name' ),
			'additionalProperties' => false,
		);
		$this->register( 'wordpress/option-get', __( 'Get WordPress Option', 'wp-ability' ), __( 'Gets one non-protected WordPress option.', 'wp-ability' ), $name_schema, array( $this, 'option_get' ), array( $this, 'can_manage_options' ), true, false, true );
		$this->register( 'wordpress/option-delete', __( 'Delete WordPress Option', 'wp-ability' ), __( 'Deletes one non-protected WordPress option.', 'wp-ability' ), $name_schema, array( $this, 'option_delete' ), array( $this, 'can_manage_options' ), false, true, true );
	}

	/**
	 * Return protected WordPress option names.
	 *
	 * @return array
	 */
	private function protected_options() {
		return apply_filters( 'wp_ability_protected_options', array( 'active_plugins', 'cron', 'stylesheet', 'template', 'siteurl', 'home' ) );
	}

	/**
	 * Execute the option get ability.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public function option_get( array $input ) {
		$name = sanitize_key( $input['option_name'] );
		if ( in_array( $name, $this->protected_options(), true ) ) {
			return new \WP_Error( 'wp_ability_protected_option', __( 'This option is protected and must be managed through a dedicated ability.', 'wp-ability' ) );
		}
		$value = get_option( $name, null );
		return array(
			'option_name' => $name,
			'exists' => null !== $value,
			'value' => $value,
		);
	}

	/**
	 * Execute the option delete ability.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public function option_delete( array $input ) {
		$name = sanitize_key( $input['option_name'] );
		if ( in_array( $name, $this->protected_options(), true ) ) {
			return new \WP_Error( 'wp_ability_protected_option', __( 'This option is protected and must be managed through a dedicated ability.', 'wp-ability' ) );
		}
		return array(
			'option_name' => $name,
			'deleted' => (bool) delete_option( $name ),
		);
	}
}
