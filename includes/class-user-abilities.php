<?php
/**
 * User Abilities.
 *
 * @package WordPress_Abilities_Bridge
 */

namespace WP_Ability;

defined( 'ABSPATH' ) || exit;

/**
 * Focused WordPress ability domain.
 */
final class User_Abilities extends Domain_Abilities_Base {

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

		$this->register_user_create();
		$this->register_users();
	}

	/**
	 * Register user creation ability.
	 *
	 * @return void
	 */
	private function register_user_create() {
		Ability_Registrar::register(
			'wordpress/user-create',
			array(
				'label'               => __( 'Create WordPress User', 'wp-ability' ),
				'description'         => __( 'Creates a WordPress user with a username, email address, optional profile fields, and an editable site role.', 'wp-ability' ),
				'category'            => 'wordpress-admin',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'username'     => array(
							'type'      => 'string',
							'minLength' => 1,
						),
						'email'        => array(
							'type'   => 'string',
							'format' => 'email',
						),
						'password'     => array(
							'type'        => 'string',
							'minLength'   => 8,
							'description' => __( 'Optional password. When omitted, WordPress generates a strong password. Passwords are never returned by this ability.', 'wp-ability' ),
						),
						'role'         => array(
							'type'    => 'string',
							'default' => 'subscriber',
						),
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
	 * Register user abilities.
	 *
	 * @return void
	 */
	private function register_users() {
		$list_schema = array(
			'type' => 'object',
			'properties' => array(
				'role' => array( 'type' => 'string' ),
				'search' => array( 'type' => 'string' ),
				'number' => array(
					'type' => 'integer',
					'minimum' => 1,
					'maximum' => 100,
					'default' => 20,
				),
				'offset' => array(
					'type' => 'integer',
					'minimum' => 0,
					'default' => 0,
				),
			),
			'additionalProperties' => false,
		);
		$id_schema = array(
			'type' => 'object',
			'properties' => array(
				'user_id' => array(
					'type' => 'integer',
					'minimum' => 1,
				),
			),
			'required' => array( 'user_id' ),
			'additionalProperties' => false,
		);
		$this->register( 'wordpress/user-list', __( 'List WordPress Users', 'wp-ability' ), __( 'Lists WordPress users visible to the current user.', 'wp-ability' ), $list_schema, array( $this, 'user_list' ), array( $this, 'can_list_users' ), true, false, true );
		$this->register( 'wordpress/user-get', __( 'Get WordPress User', 'wp-ability' ), __( 'Returns a WordPress user profile without password or authentication secrets.', 'wp-ability' ), $id_schema, array( $this, 'user_get' ), array( $this, 'can_list_users' ), true, false, true );
		$update_schema = $id_schema;
		$update_schema['properties']['email'] = array(
			'type' => 'string',
			'format' => 'email',
		);
		$update_schema['properties']['display_name'] = array( 'type' => 'string' );
		$update_schema['properties']['first_name'] = array( 'type' => 'string' );
		$update_schema['properties']['last_name'] = array( 'type' => 'string' );
		$update_schema['properties']['role'] = array( 'type' => 'string' );
		$this->register( 'wordpress/user-update', __( 'Update WordPress User', 'wp-ability' ), __( 'Updates editable WordPress user profile fields and optionally role.', 'wp-ability' ), $update_schema, array( $this, 'user_update' ), array( $this, 'can_edit_user_input' ), false, false, true );
		$this->register(
			'wordpress/user-delete',
			__( 'Delete WordPress User', 'wp-ability' ),
			__( 'Deletes a WordPress user and optionally reassigns authored content.', 'wp-ability' ),
			array(
				'type' => 'object',
				'properties' => array(
					'user_id' => array(
						'type' => 'integer',
						'minimum' => 1,
					),
					'reassign' => array(
						'type' => array( 'integer', 'null' ),
						'minimum' => 1,
					),
				),
				'required' => array( 'user_id' ),
				'additionalProperties' => false,
			),
			array( $this, 'user_delete' ),
			array( $this, 'can_delete_user_input' ),
			false,
			true,
			true
		);
	}

	/**
	 * Check whether the current user can perform this ability.
	 *
	 * @return bool
	 */
	public function can_list_users() {
		return current_user_can( 'list_users' );
	}

	/**
	 * Check whether the current user can perform this ability.
	 *
	 * @param array $input Ability input.
	 * @return bool
	 */
	public function can_edit_user_input( $input = array() ) {
		$id = isset( $input['user_id'] ) ? (int) $input['user_id'] : 0;
		return $id > 0 && current_user_can( 'edit_user', $id );
	}

	/**
	 * Check whether the current user can perform this ability.
	 *
	 * @param array $input Ability input.
	 * @return bool
	 */
	public function can_delete_user_input( $input = array() ) {
		$id = isset( $input['user_id'] ) ? (int) $input['user_id'] : 0;
		return $id > 0 && current_user_can( 'delete_user', $id );
	}

	/**
	 * Build a normalized user payload.
	 *
	 * @param \WP_User $user User object.
	 * @return array
	 */
	private function user_payload( \WP_User $user ) {
		return array(
			'user_id'      => (int) $user->ID,
			'username'     => $user->user_login,
			'email'        => $user->user_email,
			'display_name' => $user->display_name,
			'first_name'   => $user->first_name,
			'last_name'    => $user->last_name,
			'roles'        => array_values( $user->roles ),
		);
	}

	/**
	 * Execute the user list ability.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public function user_list( array $input ) {
		$args = array(
			'number' => isset( $input['number'] ) ? min( 100, (int) $input['number'] ) : 20,
			'offset' => isset( $input['offset'] ) ? max( 0, (int) $input['offset'] ) : 0,
		);
		if ( ! empty( $input['role'] ) ) {
			$args['role'] = sanitize_key( $input['role'] );
		}
		if ( ! empty( $input['search'] ) ) {
			$args['search'] = '*' . sanitize_text_field( $input['search'] ) . '*';
		}
		$query = new \WP_User_Query( $args );
		return array(
			'users' => array_map( array( $this, 'user_payload' ), $query->get_results() ),
			'total' => (int) $query->get_total(),
		);
	}

	/**
	 * Execute the user get ability.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public function user_get( array $input ) {
		$user = get_user_by( 'id', (int) $input['user_id'] );
		return $user ? $this->user_payload( $user ) : new \WP_Error( 'wp_ability_user_not_found', __( 'User not found.', 'wp-ability' ) );
	}

	/**
	 * Execute the user update ability.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public function user_update( array $input ) {
		$id   = (int) $input['user_id'];
		$data = array( 'ID' => $id );
		foreach ( array( 'display_name', 'first_name', 'last_name' ) as $field ) {
			if ( array_key_exists( $field, $input ) ) {
				$data[ $field ] = sanitize_text_field( $input[ $field ] );
			}
		}
		if ( isset( $input['email'] ) ) {
			$email = sanitize_email( $input['email'] );
			if ( ! is_email( $email ) ) {
				return new \WP_Error( 'wp_ability_invalid_email', __( 'Invalid email address.', 'wp-ability' ) );
			}
			$data['user_email'] = $email;
		}
		if ( isset( $input['role'] ) ) {
			if ( ! current_user_can( 'promote_user', $id ) ) {
				return new \WP_Error( 'wp_ability_cannot_promote_user', __( 'Current user cannot change this role.', 'wp-ability' ) );
			}
			$role = sanitize_key( $input['role'] );
			if ( ! isset( get_editable_roles()[ $role ] ) ) {
				return new \WP_Error( 'wp_ability_role_not_allowed', __( 'Role is not editable.', 'wp-ability' ) );
			}
			$data['role'] = $role;
		}
		$result = wp_update_user( $data );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return $this->user_get( array( 'user_id' => $id ) );
	}

	/**
	 * Execute the user delete ability.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public function user_delete( array $input ) {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		$id = (int) $input['user_id'];
		if ( get_current_user_id() === $id ) {
			return new \WP_Error( 'wp_ability_cannot_delete_self', __( 'The current user cannot delete itself through this ability.', 'wp-ability' ) );
		}
		$reassign = isset( $input['reassign'] ) && null !== $input['reassign'] ? (int) $input['reassign'] : null;
		$result   = wp_delete_user( $id, $reassign );
		return $result ? array(
			'user_id' => $id,
			'deleted' => true,
			'reassigned_to' => $reassign,
		) : new \WP_Error( 'wp_ability_user_delete_failed', __( 'User could not be deleted.', 'wp-ability' ) );
	}
}
