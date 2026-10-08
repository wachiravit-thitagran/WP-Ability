<?php
/**
 * Taxonomy Abilities.
 *
 * @package WordPress_Abilities_Bridge
 */

namespace WP_Ability;

defined( 'ABSPATH' ) || exit;

/**
 * Focused WordPress ability domain.
 */
final class Taxonomy_Abilities extends Domain_Abilities_Base {

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

		$this->register_terms();
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'wp_abilities_api_init', array( $this, 'register_abilities' ) );
	}

	/**
	 * Register all additional Core abilities.
	 *
	 * @return void
	 */
	public function register_abilities() {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		$this->register_users();
		$this->register_posts();
		$this->register_terms();
		$this->register_media();
		$this->register_options();
		$this->register_cron();
		$this->register_transients();
		$this->register_maintenance();
	}

	/**
	 * Register an ability with shared defaults.
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

	/**
	 * Register post abilities.
	 *
	 * @return void
	 */
	private function register_posts() {
		$this->register(
			'wordpress/post-list',
			__( 'List WordPress Posts', 'wp-ability' ),
			__( 'Lists posts for a requested public or registered post type.', 'wp-ability' ),
			array(
				'type' => 'object',
				'properties' => array(
					'post_type' => array(
						'type' => 'string',
						'default' => 'post',
					),
					'post_status' => array(
						'type' => 'string',
						'default' => 'any',
					),
					'search' => array( 'type' => 'string' ),
					'per_page' => array(
						'type' => 'integer',
						'minimum' => 1,
						'maximum' => 100,
						'default' => 20,
					),
					'page' => array(
						'type' => 'integer',
						'minimum' => 1,
						'default' => 1,
					),
				),
				'additionalProperties' => false,
			),
			array( $this, 'post_list' ),
			array( $this, 'can_edit_posts' ),
			true,
			false,
			true
		);
		$id_schema = array(
			'type' => 'object',
			'properties' => array(
				'post_id' => array(
					'type' => 'integer',
					'minimum' => 1,
				),
			),
			'required' => array( 'post_id' ),
			'additionalProperties' => false,
		);
		$this->register( 'wordpress/post-get', __( 'Get WordPress Post', 'wp-ability' ), __( 'Returns one WordPress post or custom post type item.', 'wp-ability' ), $id_schema, array( $this, 'post_get' ), array( $this, 'can_read_post_input' ), true, false, true );
		$write_schema = array(
			'type' => 'object',
			'properties' => array(
				'post_type' => array(
					'type' => 'string',
					'default' => 'post',
				),
				'post_status' => array(
					'type' => 'string',
					'default' => 'draft',
				),
				'post_title' => array( 'type' => 'string' ),
				'post_content' => array( 'type' => 'string' ),
				'post_excerpt' => array( 'type' => 'string' ),
				'post_author' => array(
					'type' => 'integer',
					'minimum' => 1,
				),
				'post_parent' => array(
					'type' => 'integer',
					'minimum' => 0,
				),
				'menu_order' => array( 'type' => 'integer' ),
			),
			'additionalProperties' => false,
		);
		$this->register( 'wordpress/post-create', __( 'Create WordPress Post', 'wp-ability' ), __( 'Creates a post, page, or registered custom post type item.', 'wp-ability' ), $write_schema, array( $this, 'post_create' ), array( $this, 'can_edit_posts' ), false, false, false );
		$update_schema = $write_schema;
		$update_schema['properties']['post_id'] = array(
			'type' => 'integer',
			'minimum' => 1,
		);
		$update_schema['required'] = array( 'post_id' );
		$this->register( 'wordpress/post-update', __( 'Update WordPress Post', 'wp-ability' ), __( 'Updates editable fields on an existing WordPress post.', 'wp-ability' ), $update_schema, array( $this, 'post_update' ), array( $this, 'can_edit_post_input' ), false, false, true );
		$this->register(
			'wordpress/post-delete',
			__( 'Delete WordPress Post', 'wp-ability' ),
			__( 'Moves a post to Trash or permanently deletes it.', 'wp-ability' ),
			array(
				'type' => 'object',
				'properties' => array(
					'post_id' => array(
						'type' => 'integer',
						'minimum' => 1,
					),
					'force' => array(
						'type' => 'boolean',
						'default' => false,
					),
				),
				'required' => array( 'post_id' ),
				'additionalProperties' => false,
			),
			array( $this, 'post_delete' ),
			array( $this, 'can_delete_post_input' ),
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
	public function can_edit_posts() {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Check whether the current user can perform this ability.
	 *
	 * @param array $input Ability input.
	 * @return bool
	 */
	public function can_read_post_input( $input = array() ) {
		$id = isset( $input['post_id'] ) ? (int) $input['post_id'] : 0;
		return $id > 0 && current_user_can( 'read_post', $id );
	}

	/**
	 * Check whether the current user can perform this ability.
	 *
	 * @param array $input Ability input.
	 * @return bool
	 */
	public function can_edit_post_input( $input = array() ) {
		$id = isset( $input['post_id'] ) ? (int) $input['post_id'] : 0;
		return $id > 0 && current_user_can( 'edit_post', $id );
	}

	/**
	 * Check whether the current user can perform this ability.
	 *
	 * @param array $input Ability input.
	 * @return bool
	 */
	public function can_delete_post_input( $input = array() ) {
		$id = isset( $input['post_id'] ) ? (int) $input['post_id'] : 0;
		return $id > 0 && current_user_can( 'delete_post', $id );
	}

	/**
	 * Build a normalized post payload.
	 *
	 * @param \WP_Post $post Post object.
	 * @return array
	 */
	private function post_payload( \WP_Post $post ) {
		return array(
			'post_id'      => (int) $post->ID,
			'post_type'    => $post->post_type,
			'post_status'  => $post->post_status,
			'post_title'   => $post->post_title,
			'post_content' => $post->post_content,
			'post_excerpt' => $post->post_excerpt,
			'post_author'  => (int) $post->post_author,
			'post_parent'  => (int) $post->post_parent,
			'menu_order'   => (int) $post->menu_order,
			'date_gmt'     => $post->post_date_gmt,
			'modified_gmt' => $post->post_modified_gmt,
		);
	}

	/**
	 * Execute the post list ability.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public function post_list( array $input ) {
		$type = isset( $input['post_type'] ) ? sanitize_key( $input['post_type'] ) : 'post';
		if ( ! post_type_exists( $type ) ) {
			return new \WP_Error( 'wp_ability_post_type_not_found', __( 'Post type not found.', 'wp-ability' ) );
		}
		$args = array(
			'post_type'      => $type,
			'post_status'    => isset( $input['post_status'] ) ? sanitize_key( $input['post_status'] ) : 'any',
			'posts_per_page' => isset( $input['per_page'] ) ? min( 100, (int) $input['per_page'] ) : 20,
			'paged'          => isset( $input['page'] ) ? max( 1, (int) $input['page'] ) : 1,
		);
		if ( ! empty( $input['search'] ) ) {
			$args['s'] = sanitize_text_field( $input['search'] );
		}
		$query = new \WP_Query( $args );
		return array(
			'posts' => array_map( array( $this, 'post_payload' ), $query->posts ),
			'total' => (int) $query->found_posts,
			'pages' => (int) $query->max_num_pages,
		);
	}

	/**
	 * Execute the post get ability.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public function post_get( array $input ) {
		$post = get_post( (int) $input['post_id'] );
		return $post ? $this->post_payload( $post ) : new \WP_Error( 'wp_ability_post_not_found', __( 'Post not found.', 'wp-ability' ) );
	}

	/**
	 * Sanitize post ability input.
	 *
	 * @param array $input Ability input.
	 * @return array
	 */
	private function sanitize_post_input( array $input ) {
		$data = array();
		if ( isset( $input['post_type'] ) ) {
			$data['post_type'] = sanitize_key( $input['post_type'] );
		}
		if ( isset( $input['post_status'] ) ) {
			$data['post_status'] = sanitize_key( $input['post_status'] );
		}
		foreach ( array( 'post_title', 'post_excerpt' ) as $field ) {
			if ( array_key_exists( $field, $input ) ) {
				$data[ $field ] = sanitize_text_field( $input[ $field ] );
			}
		}
		if ( array_key_exists( 'post_content', $input ) ) {
			$data['post_content'] = wp_kses_post( $input['post_content'] );
		}
		foreach ( array( 'post_author', 'post_parent', 'menu_order' ) as $field ) {
			if ( array_key_exists( $field, $input ) ) {
				$data[ $field ] = (int) $input[ $field ];
			}
		}
		return $data;
	}

	/**
	 * Execute the post create ability.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public function post_create( array $input ) {
		$data = $this->sanitize_post_input( $input );
		if ( empty( $data['post_type'] ) ) {
			$data['post_type'] = 'post';
		}
		if ( ! post_type_exists( $data['post_type'] ) ) {
			return new \WP_Error( 'wp_ability_post_type_not_found', __( 'Post type not found.', 'wp-ability' ) );
		}
		$obj = get_post_type_object( $data['post_type'] );
		if ( ! $obj || ! current_user_can( $obj->cap->create_posts ) ) {
			return new \WP_Error( 'wp_ability_cannot_create_post', __( 'Current user cannot create this post type.', 'wp-ability' ) );
		}
		$id = wp_insert_post( $data, true );
		return is_wp_error( $id ) ? $id : $this->post_get( array( 'post_id' => $id ) );
	}

	/**
	 * Execute the post update ability.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public function post_update( array $input ) {
		$data       = $this->sanitize_post_input( $input );
		$data['ID'] = (int) $input['post_id'];
		$id         = wp_update_post( $data, true );
		return is_wp_error( $id ) ? $id : $this->post_get( array( 'post_id' => $id ) );
	}

	/**
	 * Execute the post delete ability.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public function post_delete( array $input ) {
		$id    = (int) $input['post_id'];
		$force = ! empty( $input['force'] );
		$post  = wp_delete_post( $id, $force );
		return $post ? array(
			'post_id' => $id,
			'deleted' => true,
			'force' => $force,
		) : new \WP_Error( 'wp_ability_post_delete_failed', __( 'Post could not be deleted.', 'wp-ability' ) );
	}

	/**
	 * Register taxonomy term abilities.
	 *
	 * @return void
	 */
	private function register_terms() {
		$this->register(
			'wordpress/term-list',
			__( 'List WordPress Terms', 'wp-ability' ),
			__( 'Lists terms from a registered taxonomy.', 'wp-ability' ),
			array(
				'type' => 'object',
				'properties' => array(
					'taxonomy' => array(
						'type' => 'string',
						'minLength' => 1,
					),
					'search' => array( 'type' => 'string' ),
					'hide_empty' => array(
						'type' => 'boolean',
						'default' => false,
					),
				),
				'required' => array( 'taxonomy' ),
				'additionalProperties' => false,
			),
			array( $this, 'term_list' ),
			array( $this, 'can_manage_terms_input' ),
			true,
			false,
			true
		);
		$write = array(
			'type' => 'object',
			'properties' => array(
				'taxonomy' => array(
					'type' => 'string',
					'minLength' => 1,
				),
				'name' => array(
					'type' => 'string',
					'minLength' => 1,
				),
				'slug' => array( 'type' => 'string' ),
				'description' => array( 'type' => 'string' ),
				'parent' => array(
					'type' => 'integer',
					'minimum' => 0,
				),
			),
			'required' => array( 'taxonomy', 'name' ),
			'additionalProperties' => false,
		);
		$this->register( 'wordpress/term-create', __( 'Create WordPress Term', 'wp-ability' ), __( 'Creates a term in a registered taxonomy.', 'wp-ability' ), $write, array( $this, 'term_create' ), array( $this, 'can_manage_terms_input' ), false, false, false );
		$update = $write;
		$update['properties']['term_id'] = array(
			'type' => 'integer',
			'minimum' => 1,
		);
		$update['required'] = array( 'taxonomy', 'term_id' );
		$this->register( 'wordpress/term-update', __( 'Update WordPress Term', 'wp-ability' ), __( 'Updates a term in a registered taxonomy.', 'wp-ability' ), $update, array( $this, 'term_update' ), array( $this, 'can_manage_terms_input' ), false, false, true );
		$this->register(
			'wordpress/term-delete',
			__( 'Delete WordPress Term', 'wp-ability' ),
			__( 'Deletes a term from a registered taxonomy.', 'wp-ability' ),
			array(
				'type' => 'object',
				'properties' => array(
					'taxonomy' => array(
						'type' => 'string',
						'minLength' => 1,
					),
					'term_id' => array(
						'type' => 'integer',
						'minimum' => 1,
					),
				),
				'required' => array( 'taxonomy', 'term_id' ),
				'additionalProperties' => false,
			),
			array( $this, 'term_delete' ),
			array( $this, 'can_manage_terms_input' ),
			false,
			true,
			true
		);
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'wp_abilities_api_init', array( $this, 'register_abilities' ) );
	}

	/**
	 * Register all additional Core abilities.
	 *
	 * @return void
	 */
	public function register_abilities() {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		$this->register_users();
		$this->register_posts();
		$this->register_terms();
		$this->register_media();
		$this->register_options();
		$this->register_cron();
		$this->register_transients();
		$this->register_maintenance();
	}

	/**
	 * Register an ability with shared defaults.
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

	/**
	 * Register post abilities.
	 *
	 * @return void
	 */
	private function register_posts() {
		$this->register(
			'wordpress/post-list',
			__( 'List WordPress Posts', 'wp-ability' ),
			__( 'Lists posts for a requested public or registered post type.', 'wp-ability' ),
			array(
				'type' => 'object',
				'properties' => array(
					'post_type' => array(
						'type' => 'string',
						'default' => 'post',
					),
					'post_status' => array(
						'type' => 'string',
						'default' => 'any',
					),
					'search' => array( 'type' => 'string' ),
					'per_page' => array(
						'type' => 'integer',
						'minimum' => 1,
						'maximum' => 100,
						'default' => 20,
					),
					'page' => array(
						'type' => 'integer',
						'minimum' => 1,
						'default' => 1,
					),
				),
				'additionalProperties' => false,
			),
			array( $this, 'post_list' ),
			array( $this, 'can_edit_posts' ),
			true,
			false,
			true
		);
		$id_schema = array(
			'type' => 'object',
			'properties' => array(
				'post_id' => array(
					'type' => 'integer',
					'minimum' => 1,
				),
			),
			'required' => array( 'post_id' ),
			'additionalProperties' => false,
		);
		$this->register( 'wordpress/post-get', __( 'Get WordPress Post', 'wp-ability' ), __( 'Returns one WordPress post or custom post type item.', 'wp-ability' ), $id_schema, array( $this, 'post_get' ), array( $this, 'can_read_post_input' ), true, false, true );
		$write_schema = array(
			'type' => 'object',
			'properties' => array(
				'post_type' => array(
					'type' => 'string',
					'default' => 'post',
				),
				'post_status' => array(
					'type' => 'string',
					'default' => 'draft',
				),
				'post_title' => array( 'type' => 'string' ),
				'post_content' => array( 'type' => 'string' ),
				'post_excerpt' => array( 'type' => 'string' ),
				'post_author' => array(
					'type' => 'integer',
					'minimum' => 1,
				),
				'post_parent' => array(
					'type' => 'integer',
					'minimum' => 0,
				),
				'menu_order' => array( 'type' => 'integer' ),
			),
			'additionalProperties' => false,
		);
		$this->register( 'wordpress/post-create', __( 'Create WordPress Post', 'wp-ability' ), __( 'Creates a post, page, or registered custom post type item.', 'wp-ability' ), $write_schema, array( $this, 'post_create' ), array( $this, 'can_edit_posts' ), false, false, false );
		$update_schema = $write_schema;
		$update_schema['properties']['post_id'] = array(
			'type' => 'integer',
			'minimum' => 1,
		);
		$update_schema['required'] = array( 'post_id' );
		$this->register( 'wordpress/post-update', __( 'Update WordPress Post', 'wp-ability' ), __( 'Updates editable fields on an existing WordPress post.', 'wp-ability' ), $update_schema, array( $this, 'post_update' ), array( $this, 'can_edit_post_input' ), false, false, true );
		$this->register(
			'wordpress/post-delete',
			__( 'Delete WordPress Post', 'wp-ability' ),
			__( 'Moves a post to Trash or permanently deletes it.', 'wp-ability' ),
			array(
				'type' => 'object',
				'properties' => array(
					'post_id' => array(
						'type' => 'integer',
						'minimum' => 1,
					),
					'force' => array(
						'type' => 'boolean',
						'default' => false,
					),
				),
				'required' => array( 'post_id' ),
				'additionalProperties' => false,
			),
			array( $this, 'post_delete' ),
			array( $this, 'can_delete_post_input' ),
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
	public function can_edit_posts() {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Check whether the current user can perform this ability.
	 *
	 * @param array $input Ability input.
	 * @return bool
	 */
	public function can_read_post_input( $input = array() ) {
		$id = isset( $input['post_id'] ) ? (int) $input['post_id'] : 0;
		return $id > 0 && current_user_can( 'read_post', $id );
	}

	/**
	 * Check whether the current user can perform this ability.
	 *
	 * @param array $input Ability input.
	 * @return bool
	 */
	public function can_edit_post_input( $input = array() ) {
		$id = isset( $input['post_id'] ) ? (int) $input['post_id'] : 0;
		return $id > 0 && current_user_can( 'edit_post', $id );
	}

	/**
	 * Check whether the current user can perform this ability.
	 *
	 * @param array $input Ability input.
	 * @return bool
	 */
	public function can_delete_post_input( $input = array() ) {
		$id = isset( $input['post_id'] ) ? (int) $input['post_id'] : 0;
		return $id > 0 && current_user_can( 'delete_post', $id );
	}

	/**
	 * Build a normalized post payload.
	 *
	 * @param \WP_Post $post Post object.
	 * @return array
	 */
	private function post_payload( \WP_Post $post ) {
		return array(
			'post_id'      => (int) $post->ID,
			'post_type'    => $post->post_type,
			'post_status'  => $post->post_status,
			'post_title'   => $post->post_title,
			'post_content' => $post->post_content,
			'post_excerpt' => $post->post_excerpt,
			'post_author'  => (int) $post->post_author,
			'post_parent'  => (int) $post->post_parent,
			'menu_order'   => (int) $post->menu_order,
			'date_gmt'     => $post->post_date_gmt,
			'modified_gmt' => $post->post_modified_gmt,
		);
	}

	/**
	 * Execute the post list ability.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public function post_list( array $input ) {
		$type = isset( $input['post_type'] ) ? sanitize_key( $input['post_type'] ) : 'post';
		if ( ! post_type_exists( $type ) ) {
			return new \WP_Error( 'wp_ability_post_type_not_found', __( 'Post type not found.', 'wp-ability' ) );
		}
		$args = array(
			'post_type'      => $type,
			'post_status'    => isset( $input['post_status'] ) ? sanitize_key( $input['post_status'] ) : 'any',
			'posts_per_page' => isset( $input['per_page'] ) ? min( 100, (int) $input['per_page'] ) : 20,
			'paged'          => isset( $input['page'] ) ? max( 1, (int) $input['page'] ) : 1,
		);
		if ( ! empty( $input['search'] ) ) {
			$args['s'] = sanitize_text_field( $input['search'] );
		}
		$query = new \WP_Query( $args );
		return array(
			'posts' => array_map( array( $this, 'post_payload' ), $query->posts ),
			'total' => (int) $query->found_posts,
			'pages' => (int) $query->max_num_pages,
		);
	}

	/**
	 * Execute the post get ability.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public function post_get( array $input ) {
		$post = get_post( (int) $input['post_id'] );
		return $post ? $this->post_payload( $post ) : new \WP_Error( 'wp_ability_post_not_found', __( 'Post not found.', 'wp-ability' ) );
	}

	/**
	 * Sanitize post ability input.
	 *
	 * @param array $input Ability input.
	 * @return array
	 */
	private function sanitize_post_input( array $input ) {
		$data = array();
		if ( isset( $input['post_type'] ) ) {
			$data['post_type'] = sanitize_key( $input['post_type'] );
		}
		if ( isset( $input['post_status'] ) ) {
			$data['post_status'] = sanitize_key( $input['post_status'] );
		}
		foreach ( array( 'post_title', 'post_excerpt' ) as $field ) {
			if ( array_key_exists( $field, $input ) ) {
				$data[ $field ] = sanitize_text_field( $input[ $field ] );
			}
		}
		if ( array_key_exists( 'post_content', $input ) ) {
			$data['post_content'] = wp_kses_post( $input['post_content'] );
		}
		foreach ( array( 'post_author', 'post_parent', 'menu_order' ) as $field ) {
			if ( array_key_exists( $field, $input ) ) {
				$data[ $field ] = (int) $input[ $field ];
			}
		}
		return $data;
	}

	/**
	 * Execute the post create ability.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public function post_create( array $input ) {
		$data = $this->sanitize_post_input( $input );
		if ( empty( $data['post_type'] ) ) {
			$data['post_type'] = 'post';
		}
		if ( ! post_type_exists( $data['post_type'] ) ) {
			return new \WP_Error( 'wp_ability_post_type_not_found', __( 'Post type not found.', 'wp-ability' ) );
		}
		$obj = get_post_type_object( $data['post_type'] );
		if ( ! $obj || ! current_user_can( $obj->cap->create_posts ) ) {
			return new \WP_Error( 'wp_ability_cannot_create_post', __( 'Current user cannot create this post type.', 'wp-ability' ) );
		}
		$id = wp_insert_post( $data, true );
		return is_wp_error( $id ) ? $id : $this->post_get( array( 'post_id' => $id ) );
	}

	/**
	 * Execute the post update ability.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public function post_update( array $input ) {
		$data       = $this->sanitize_post_input( $input );
		$data['ID'] = (int) $input['post_id'];
		$id         = wp_update_post( $data, true );
		return is_wp_error( $id ) ? $id : $this->post_get( array( 'post_id' => $id ) );
	}

	/**
	 * Execute the post delete ability.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public function post_delete( array $input ) {
		$id    = (int) $input['post_id'];
		$force = ! empty( $input['force'] );
		$post  = wp_delete_post( $id, $force );
		return $post ? array(
			'post_id' => $id,
			'deleted' => true,
			'force' => $force,
		) : new \WP_Error( 'wp_ability_post_delete_failed', __( 'Post could not be deleted.', 'wp-ability' ) );
	}

	/**
	 * Register taxonomy term abilities.
	 *
	 * @return void
	 */
	private function register_terms() {
		$this->register(
			'wordpress/term-list',
			__( 'List WordPress Terms', 'wp-ability' ),
			__( 'Lists terms from a registered taxonomy.', 'wp-ability' ),
			array(
				'type' => 'object',
				'properties' => array(
					'taxonomy' => array(
						'type' => 'string',
						'minLength' => 1,
					),
					'search' => array( 'type' => 'string' ),
					'hide_empty' => array(
						'type' => 'boolean',
						'default' => false,
					),
				),
				'required' => array( 'taxonomy' ),
				'additionalProperties' => false,
			),
			array( $this, 'term_list' ),
			array( $this, 'can_manage_terms_input' ),
			true,
			false,
			true
		);
		$write = array(
			'type' => 'object',
			'properties' => array(
				'taxonomy' => array(
					'type' => 'string',
					'minLength' => 1,
				),
				'name' => array(
					'type' => 'string',
					'minLength' => 1,
				),
				'slug' => array( 'type' => 'string' ),
				'description' => array( 'type' => 'string' ),
				'parent' => array(
					'type' => 'integer',
					'minimum' => 0,
				),
			),
			'required' => array( 'taxonomy', 'name' ),
			'additionalProperties' => false,
		);
		$this->register( 'wordpress/term-create', __( 'Create WordPress Term', 'wp-ability' ), __( 'Creates a term in a registered taxonomy.', 'wp-ability' ), $write, array( $this, 'term_create' ), array( $this, 'can_manage_terms_input' ), false, false, false );
		$update = $write;
		$update['properties']['term_id'] = array(
			'type' => 'integer',
			'minimum' => 1,
		);
		$update['required'] = array( 'taxonomy', 'term_id' );
		$this->register( 'wordpress/term-update', __( 'Update WordPress Term', 'wp-ability' ), __( 'Updates a term in a registered taxonomy.', 'wp-ability' ), $update, array( $this, 'term_update' ), array( $this, 'can_manage_terms_input' ), false, false, true );
		$this->register(
			'wordpress/term-delete',
			__( 'Delete WordPress Term', 'wp-ability' ),
			__( 'Deletes a term from a registered taxonomy.', 'wp-ability' ),
			array(
				'type' => 'object',
				'properties' => array(
					'taxonomy' => array(
						'type' => 'string',
						'minLength' => 1,
					),
					'term_id' => array(
						'type' => 'integer',
						'minimum' => 1,
					),
				),
				'required' => array( 'taxonomy', 'term_id' ),
				'additionalProperties' => false,
			),
			array( $this, 'term_delete' ),
			array( $this, 'can_manage_terms_input' ),
			false,
			true,
			true
		);
	}

	/**
	 * Check whether the current user can perform this ability.
	 *
	 * @param array $input Ability input.
	 * @return bool
	 */
	public function can_manage_terms_input( $input = array() ) {
		$taxonomy = isset( $input['taxonomy'] ) ? sanitize_key( $input['taxonomy'] ) : '';
		$obj      = $taxonomy ? get_taxonomy( $taxonomy ) : false;
		return $obj && current_user_can( $obj->cap->manage_terms );
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'wp_abilities_api_init', array( $this, 'register_abilities' ) );
	}

	/**
	 * Register all additional Core abilities.
	 *
	 * @return void
	 */
	public function register_abilities() {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		$this->register_users();
		$this->register_posts();
		$this->register_terms();
		$this->register_media();
		$this->register_options();
		$this->register_cron();
		$this->register_transients();
		$this->register_maintenance();
	}

	/**
	 * Register an ability with shared defaults.
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

	/**
	 * Register post abilities.
	 *
	 * @return void
	 */
	private function register_posts() {
		$this->register(
			'wordpress/post-list',
			__( 'List WordPress Posts', 'wp-ability' ),
			__( 'Lists posts for a requested public or registered post type.', 'wp-ability' ),
			array(
				'type' => 'object',
				'properties' => array(
					'post_type' => array(
						'type' => 'string',
						'default' => 'post',
					),
					'post_status' => array(
						'type' => 'string',
						'default' => 'any',
					),
					'search' => array( 'type' => 'string' ),
					'per_page' => array(
						'type' => 'integer',
						'minimum' => 1,
						'maximum' => 100,
						'default' => 20,
					),
					'page' => array(
						'type' => 'integer',
						'minimum' => 1,
						'default' => 1,
					),
				),
				'additionalProperties' => false,
			),
			array( $this, 'post_list' ),
			array( $this, 'can_edit_posts' ),
			true,
			false,
			true
		);
		$id_schema = array(
			'type' => 'object',
			'properties' => array(
				'post_id' => array(
					'type' => 'integer',
					'minimum' => 1,
				),
			),
			'required' => array( 'post_id' ),
			'additionalProperties' => false,
		);
		$this->register( 'wordpress/post-get', __( 'Get WordPress Post', 'wp-ability' ), __( 'Returns one WordPress post or custom post type item.', 'wp-ability' ), $id_schema, array( $this, 'post_get' ), array( $this, 'can_read_post_input' ), true, false, true );
		$write_schema = array(
			'type' => 'object',
			'properties' => array(
				'post_type' => array(
					'type' => 'string',
					'default' => 'post',
				),
				'post_status' => array(
					'type' => 'string',
					'default' => 'draft',
				),
				'post_title' => array( 'type' => 'string' ),
				'post_content' => array( 'type' => 'string' ),
				'post_excerpt' => array( 'type' => 'string' ),
				'post_author' => array(
					'type' => 'integer',
					'minimum' => 1,
				),
				'post_parent' => array(
					'type' => 'integer',
					'minimum' => 0,
				),
				'menu_order' => array( 'type' => 'integer' ),
			),
			'additionalProperties' => false,
		);
		$this->register( 'wordpress/post-create', __( 'Create WordPress Post', 'wp-ability' ), __( 'Creates a post, page, or registered custom post type item.', 'wp-ability' ), $write_schema, array( $this, 'post_create' ), array( $this, 'can_edit_posts' ), false, false, false );
		$update_schema = $write_schema;
		$update_schema['properties']['post_id'] = array(
			'type' => 'integer',
			'minimum' => 1,
		);
		$update_schema['required'] = array( 'post_id' );
		$this->register( 'wordpress/post-update', __( 'Update WordPress Post', 'wp-ability' ), __( 'Updates editable fields on an existing WordPress post.', 'wp-ability' ), $update_schema, array( $this, 'post_update' ), array( $this, 'can_edit_post_input' ), false, false, true );
		$this->register(
			'wordpress/post-delete',
			__( 'Delete WordPress Post', 'wp-ability' ),
			__( 'Moves a post to Trash or permanently deletes it.', 'wp-ability' ),
			array(
				'type' => 'object',
				'properties' => array(
					'post_id' => array(
						'type' => 'integer',
						'minimum' => 1,
					),
					'force' => array(
						'type' => 'boolean',
						'default' => false,
					),
				),
				'required' => array( 'post_id' ),
				'additionalProperties' => false,
			),
			array( $this, 'post_delete' ),
			array( $this, 'can_delete_post_input' ),
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
	public function can_edit_posts() {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Check whether the current user can perform this ability.
	 *
	 * @param array $input Ability input.
	 * @return bool
	 */
	public function can_read_post_input( $input = array() ) {
		$id = isset( $input['post_id'] ) ? (int) $input['post_id'] : 0;
		return $id > 0 && current_user_can( 'read_post', $id );
	}

	/**
	 * Check whether the current user can perform this ability.
	 *
	 * @param array $input Ability input.
	 * @return bool
	 */
	public function can_edit_post_input( $input = array() ) {
		$id = isset( $input['post_id'] ) ? (int) $input['post_id'] : 0;
		return $id > 0 && current_user_can( 'edit_post', $id );
	}

	/**
	 * Check whether the current user can perform this ability.
	 *
	 * @param array $input Ability input.
	 * @return bool
	 */
	public function can_delete_post_input( $input = array() ) {
		$id = isset( $input['post_id'] ) ? (int) $input['post_id'] : 0;
		return $id > 0 && current_user_can( 'delete_post', $id );
	}

	/**
	 * Build a normalized post payload.
	 *
	 * @param \WP_Post $post Post object.
	 * @return array
	 */
	private function post_payload( \WP_Post $post ) {
		return array(
			'post_id'      => (int) $post->ID,
			'post_type'    => $post->post_type,
			'post_status'  => $post->post_status,
			'post_title'   => $post->post_title,
			'post_content' => $post->post_content,
			'post_excerpt' => $post->post_excerpt,
			'post_author'  => (int) $post->post_author,
			'post_parent'  => (int) $post->post_parent,
			'menu_order'   => (int) $post->menu_order,
			'date_gmt'     => $post->post_date_gmt,
			'modified_gmt' => $post->post_modified_gmt,
		);
	}

	/**
	 * Execute the post list ability.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public function post_list( array $input ) {
		$type = isset( $input['post_type'] ) ? sanitize_key( $input['post_type'] ) : 'post';
		if ( ! post_type_exists( $type ) ) {
			return new \WP_Error( 'wp_ability_post_type_not_found', __( 'Post type not found.', 'wp-ability' ) );
		}
		$args = array(
			'post_type'      => $type,
			'post_status'    => isset( $input['post_status'] ) ? sanitize_key( $input['post_status'] ) : 'any',
			'posts_per_page' => isset( $input['per_page'] ) ? min( 100, (int) $input['per_page'] ) : 20,
			'paged'          => isset( $input['page'] ) ? max( 1, (int) $input['page'] ) : 1,
		);
		if ( ! empty( $input['search'] ) ) {
			$args['s'] = sanitize_text_field( $input['search'] );
		}
		$query = new \WP_Query( $args );
		return array(
			'posts' => array_map( array( $this, 'post_payload' ), $query->posts ),
			'total' => (int) $query->found_posts,
			'pages' => (int) $query->max_num_pages,
		);
	}

	/**
	 * Execute the post get ability.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public function post_get( array $input ) {
		$post = get_post( (int) $input['post_id'] );
		return $post ? $this->post_payload( $post ) : new \WP_Error( 'wp_ability_post_not_found', __( 'Post not found.', 'wp-ability' ) );
	}

	/**
	 * Sanitize post ability input.
	 *
	 * @param array $input Ability input.
	 * @return array
	 */
	private function sanitize_post_input( array $input ) {
		$data = array();
		if ( isset( $input['post_type'] ) ) {
			$data['post_type'] = sanitize_key( $input['post_type'] );
		}
		if ( isset( $input['post_status'] ) ) {
			$data['post_status'] = sanitize_key( $input['post_status'] );
		}
		foreach ( array( 'post_title', 'post_excerpt' ) as $field ) {
			if ( array_key_exists( $field, $input ) ) {
				$data[ $field ] = sanitize_text_field( $input[ $field ] );
			}
		}
		if ( array_key_exists( 'post_content', $input ) ) {
			$data['post_content'] = wp_kses_post( $input['post_content'] );
		}
		foreach ( array( 'post_author', 'post_parent', 'menu_order' ) as $field ) {
			if ( array_key_exists( $field, $input ) ) {
				$data[ $field ] = (int) $input[ $field ];
			}
		}
		return $data;
	}

	/**
	 * Execute the post create ability.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public function post_create( array $input ) {
		$data = $this->sanitize_post_input( $input );
		if ( empty( $data['post_type'] ) ) {
			$data['post_type'] = 'post';
		}
		if ( ! post_type_exists( $data['post_type'] ) ) {
			return new \WP_Error( 'wp_ability_post_type_not_found', __( 'Post type not found.', 'wp-ability' ) );
		}
		$obj = get_post_type_object( $data['post_type'] );
		if ( ! $obj || ! current_user_can( $obj->cap->create_posts ) ) {
			return new \WP_Error( 'wp_ability_cannot_create_post', __( 'Current user cannot create this post type.', 'wp-ability' ) );
		}
		$id = wp_insert_post( $data, true );
		return is_wp_error( $id ) ? $id : $this->post_get( array( 'post_id' => $id ) );
	}

	/**
	 * Execute the post update ability.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public function post_update( array $input ) {
		$data       = $this->sanitize_post_input( $input );
		$data['ID'] = (int) $input['post_id'];
		$id         = wp_update_post( $data, true );
		return is_wp_error( $id ) ? $id : $this->post_get( array( 'post_id' => $id ) );
	}

	/**
	 * Execute the post delete ability.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public function post_delete( array $input ) {
		$id    = (int) $input['post_id'];
		$force = ! empty( $input['force'] );
		$post  = wp_delete_post( $id, $force );
		return $post ? array(
			'post_id' => $id,
			'deleted' => true,
			'force' => $force,
		) : new \WP_Error( 'wp_ability_post_delete_failed', __( 'Post could not be deleted.', 'wp-ability' ) );
	}

	/**
	 * Register taxonomy term abilities.
	 *
	 * @return void
	 */
	private function register_terms() {
		$this->register(
			'wordpress/term-list',
			__( 'List WordPress Terms', 'wp-ability' ),
			__( 'Lists terms from a registered taxonomy.', 'wp-ability' ),
			array(
				'type' => 'object',
				'properties' => array(
					'taxonomy' => array(
						'type' => 'string',
						'minLength' => 1,
					),
					'search' => array( 'type' => 'string' ),
					'hide_empty' => array(
						'type' => 'boolean',
						'default' => false,
					),
				),
				'required' => array( 'taxonomy' ),
				'additionalProperties' => false,
			),
			array( $this, 'term_list' ),
			array( $this, 'can_manage_terms_input' ),
			true,
			false,
			true
		);
		$write = array(
			'type' => 'object',
			'properties' => array(
				'taxonomy' => array(
					'type' => 'string',
					'minLength' => 1,
				),
				'name' => array(
					'type' => 'string',
					'minLength' => 1,
				),
				'slug' => array( 'type' => 'string' ),
				'description' => array( 'type' => 'string' ),
				'parent' => array(
					'type' => 'integer',
					'minimum' => 0,
				),
			),
			'required' => array( 'taxonomy', 'name' ),
			'additionalProperties' => false,
		);
		$this->register( 'wordpress/term-create', __( 'Create WordPress Term', 'wp-ability' ), __( 'Creates a term in a registered taxonomy.', 'wp-ability' ), $write, array( $this, 'term_create' ), array( $this, 'can_manage_terms_input' ), false, false, false );
		$update = $write;
		$update['properties']['term_id'] = array(
			'type' => 'integer',
			'minimum' => 1,
		);
		$update['required'] = array( 'taxonomy', 'term_id' );
		$this->register( 'wordpress/term-update', __( 'Update WordPress Term', 'wp-ability' ), __( 'Updates a term in a registered taxonomy.', 'wp-ability' ), $update, array( $this, 'term_update' ), array( $this, 'can_manage_terms_input' ), false, false, true );
		$this->register(
			'wordpress/term-delete',
			__( 'Delete WordPress Term', 'wp-ability' ),
			__( 'Deletes a term from a registered taxonomy.', 'wp-ability' ),
			array(
				'type' => 'object',
				'properties' => array(
					'taxonomy' => array(
						'type' => 'string',
						'minLength' => 1,
					),
					'term_id' => array(
						'type' => 'integer',
						'minimum' => 1,
					),
				),
				'required' => array( 'taxonomy', 'term_id' ),
				'additionalProperties' => false,
			),
			array( $this, 'term_delete' ),
			array( $this, 'can_manage_terms_input' ),
			false,
			true,
			true
		);
	}

	/**
	 * Check whether the current user can perform this ability.
	 *
	 * @param array $input Ability input.
	 * @return bool
	 */
	public function can_manage_terms_input( $input = array() ) {
		$taxonomy = isset( $input['taxonomy'] ) ? sanitize_key( $input['taxonomy'] ) : '';
		$obj      = $taxonomy ? get_taxonomy( $taxonomy ) : false;
		return $obj && current_user_can( $obj->cap->manage_terms );
	}

	/**
	 * Build a normalized term payload.
	 *
	 * @param \WP_Term $term Term object.
	 * @return array
	 */
	private function term_payload( \WP_Term $term ) {
		return array(
			'term_id'     => (int) $term->term_id,
			'taxonomy'    => $term->taxonomy,
			'name'        => $term->name,
			'slug'        => $term->slug,
			'description' => $term->description,
			'parent'      => (int) $term->parent,
			'count'       => (int) $term->count,
		);
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'wp_abilities_api_init', array( $this, 'register_abilities' ) );
	}

	/**
	 * Register all additional Core abilities.
	 *
	 * @return void
	 */
	public function register_abilities() {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		$this->register_users();
		$this->register_posts();
		$this->register_terms();
		$this->register_media();
		$this->register_options();
		$this->register_cron();
		$this->register_transients();
		$this->register_maintenance();
	}

	/**
	 * Register an ability with shared defaults.
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

	/**
	 * Register post abilities.
	 *
	 * @return void
	 */
	private function register_posts() {
		$this->register(
			'wordpress/post-list',
			__( 'List WordPress Posts', 'wp-ability' ),
			__( 'Lists posts for a requested public or registered post type.', 'wp-ability' ),
			array(
				'type' => 'object',
				'properties' => array(
					'post_type' => array(
						'type' => 'string',
						'default' => 'post',
					),
					'post_status' => array(
						'type' => 'string',
						'default' => 'any',
					),
					'search' => array( 'type' => 'string' ),
					'per_page' => array(
						'type' => 'integer',
						'minimum' => 1,
						'maximum' => 100,
						'default' => 20,
					),
					'page' => array(
						'type' => 'integer',
						'minimum' => 1,
						'default' => 1,
					),
				),
				'additionalProperties' => false,
			),
			array( $this, 'post_list' ),
			array( $this, 'can_edit_posts' ),
			true,
			false,
			true
		);
		$id_schema = array(
			'type' => 'object',
			'properties' => array(
				'post_id' => array(
					'type' => 'integer',
					'minimum' => 1,
				),
			),
			'required' => array( 'post_id' ),
			'additionalProperties' => false,
		);
		$this->register( 'wordpress/post-get', __( 'Get WordPress Post', 'wp-ability' ), __( 'Returns one WordPress post or custom post type item.', 'wp-ability' ), $id_schema, array( $this, 'post_get' ), array( $this, 'can_read_post_input' ), true, false, true );
		$write_schema = array(
			'type' => 'object',
			'properties' => array(
				'post_type' => array(
					'type' => 'string',
					'default' => 'post',
				),
				'post_status' => array(
					'type' => 'string',
					'default' => 'draft',
				),
				'post_title' => array( 'type' => 'string' ),
				'post_content' => array( 'type' => 'string' ),
				'post_excerpt' => array( 'type' => 'string' ),
				'post_author' => array(
					'type' => 'integer',
					'minimum' => 1,
				),
				'post_parent' => array(
					'type' => 'integer',
					'minimum' => 0,
				),
				'menu_order' => array( 'type' => 'integer' ),
			),
			'additionalProperties' => false,
		);
		$this->register( 'wordpress/post-create', __( 'Create WordPress Post', 'wp-ability' ), __( 'Creates a post, page, or registered custom post type item.', 'wp-ability' ), $write_schema, array( $this, 'post_create' ), array( $this, 'can_edit_posts' ), false, false, false );
		$update_schema = $write_schema;
		$update_schema['properties']['post_id'] = array(
			'type' => 'integer',
			'minimum' => 1,
		);
		$update_schema['required'] = array( 'post_id' );
		$this->register( 'wordpress/post-update', __( 'Update WordPress Post', 'wp-ability' ), __( 'Updates editable fields on an existing WordPress post.', 'wp-ability' ), $update_schema, array( $this, 'post_update' ), array( $this, 'can_edit_post_input' ), false, false, true );
		$this->register(
			'wordpress/post-delete',
			__( 'Delete WordPress Post', 'wp-ability' ),
			__( 'Moves a post to Trash or permanently deletes it.', 'wp-ability' ),
			array(
				'type' => 'object',
				'properties' => array(
					'post_id' => array(
						'type' => 'integer',
						'minimum' => 1,
					),
					'force' => array(
						'type' => 'boolean',
						'default' => false,
					),
				),
				'required' => array( 'post_id' ),
				'additionalProperties' => false,
			),
			array( $this, 'post_delete' ),
			array( $this, 'can_delete_post_input' ),
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
	public function can_edit_posts() {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Check whether the current user can perform this ability.
	 *
	 * @param array $input Ability input.
	 * @return bool
	 */
	public function can_read_post_input( $input = array() ) {
		$id = isset( $input['post_id'] ) ? (int) $input['post_id'] : 0;
		return $id > 0 && current_user_can( 'read_post', $id );
	}

	/**
	 * Check whether the current user can perform this ability.
	 *
	 * @param array $input Ability input.
	 * @return bool
	 */
	public function can_edit_post_input( $input = array() ) {
		$id = isset( $input['post_id'] ) ? (int) $input['post_id'] : 0;
		return $id > 0 && current_user_can( 'edit_post', $id );
	}

	/**
	 * Check whether the current user can perform this ability.
	 *
	 * @param array $input Ability input.
	 * @return bool
	 */
	public function can_delete_post_input( $input = array() ) {
		$id = isset( $input['post_id'] ) ? (int) $input['post_id'] : 0;
		return $id > 0 && current_user_can( 'delete_post', $id );
	}

	/**
	 * Build a normalized post payload.
	 *
	 * @param \WP_Post $post Post object.
	 * @return array
	 */
	private function post_payload( \WP_Post $post ) {
		return array(
			'post_id'      => (int) $post->ID,
			'post_type'    => $post->post_type,
			'post_status'  => $post->post_status,
			'post_title'   => $post->post_title,
			'post_content' => $post->post_content,
			'post_excerpt' => $post->post_excerpt,
			'post_author'  => (int) $post->post_author,
			'post_parent'  => (int) $post->post_parent,
			'menu_order'   => (int) $post->menu_order,
			'date_gmt'     => $post->post_date_gmt,
			'modified_gmt' => $post->post_modified_gmt,
		);
	}

	/**
	 * Execute the post list ability.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public function post_list( array $input ) {
		$type = isset( $input['post_type'] ) ? sanitize_key( $input['post_type'] ) : 'post';
		if ( ! post_type_exists( $type ) ) {
			return new \WP_Error( 'wp_ability_post_type_not_found', __( 'Post type not found.', 'wp-ability' ) );
		}
		$args = array(
			'post_type'      => $type,
			'post_status'    => isset( $input['post_status'] ) ? sanitize_key( $input['post_status'] ) : 'any',
			'posts_per_page' => isset( $input['per_page'] ) ? min( 100, (int) $input['per_page'] ) : 20,
			'paged'          => isset( $input['page'] ) ? max( 1, (int) $input['page'] ) : 1,
		);
		if ( ! empty( $input['search'] ) ) {
			$args['s'] = sanitize_text_field( $input['search'] );
		}
		$query = new \WP_Query( $args );
		return array(
			'posts' => array_map( array( $this, 'post_payload' ), $query->posts ),
			'total' => (int) $query->found_posts,
			'pages' => (int) $query->max_num_pages,
		);
	}

	/**
	 * Execute the post get ability.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public function post_get( array $input ) {
		$post = get_post( (int) $input['post_id'] );
		return $post ? $this->post_payload( $post ) : new \WP_Error( 'wp_ability_post_not_found', __( 'Post not found.', 'wp-ability' ) );
	}

	/**
	 * Sanitize post ability input.
	 *
	 * @param array $input Ability input.
	 * @return array
	 */
	private function sanitize_post_input( array $input ) {
		$data = array();
		if ( isset( $input['post_type'] ) ) {
			$data['post_type'] = sanitize_key( $input['post_type'] );
		}
		if ( isset( $input['post_status'] ) ) {
			$data['post_status'] = sanitize_key( $input['post_status'] );
		}
		foreach ( array( 'post_title', 'post_excerpt' ) as $field ) {
			if ( array_key_exists( $field, $input ) ) {
				$data[ $field ] = sanitize_text_field( $input[ $field ] );
			}
		}
		if ( array_key_exists( 'post_content', $input ) ) {
			$data['post_content'] = wp_kses_post( $input['post_content'] );
		}
		foreach ( array( 'post_author', 'post_parent', 'menu_order' ) as $field ) {
			if ( array_key_exists( $field, $input ) ) {
				$data[ $field ] = (int) $input[ $field ];
			}
		}
		return $data;
	}

	/**
	 * Execute the post create ability.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public function post_create( array $input ) {
		$data = $this->sanitize_post_input( $input );
		if ( empty( $data['post_type'] ) ) {
			$data['post_type'] = 'post';
		}
		if ( ! post_type_exists( $data['post_type'] ) ) {
			return new \WP_Error( 'wp_ability_post_type_not_found', __( 'Post type not found.', 'wp-ability' ) );
		}
		$obj = get_post_type_object( $data['post_type'] );
		if ( ! $obj || ! current_user_can( $obj->cap->create_posts ) ) {
			return new \WP_Error( 'wp_ability_cannot_create_post', __( 'Current user cannot create this post type.', 'wp-ability' ) );
		}
		$id = wp_insert_post( $data, true );
		return is_wp_error( $id ) ? $id : $this->post_get( array( 'post_id' => $id ) );
	}

	/**
	 * Execute the post update ability.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public function post_update( array $input ) {
		$data       = $this->sanitize_post_input( $input );
		$data['ID'] = (int) $input['post_id'];
		$id         = wp_update_post( $data, true );
		return is_wp_error( $id ) ? $id : $this->post_get( array( 'post_id' => $id ) );
	}

	/**
	 * Execute the post delete ability.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public function post_delete( array $input ) {
		$id    = (int) $input['post_id'];
		$force = ! empty( $input['force'] );
		$post  = wp_delete_post( $id, $force );
		return $post ? array(
			'post_id' => $id,
			'deleted' => true,
			'force' => $force,
		) : new \WP_Error( 'wp_ability_post_delete_failed', __( 'Post could not be deleted.', 'wp-ability' ) );
	}

	/**
	 * Register taxonomy term abilities.
	 *
	 * @return void
	 */
	private function register_terms() {
		$this->register(
			'wordpress/term-list',
			__( 'List WordPress Terms', 'wp-ability' ),
			__( 'Lists terms from a registered taxonomy.', 'wp-ability' ),
			array(
				'type' => 'object',
				'properties' => array(
					'taxonomy' => array(
						'type' => 'string',
						'minLength' => 1,
					),
					'search' => array( 'type' => 'string' ),
					'hide_empty' => array(
						'type' => 'boolean',
						'default' => false,
					),
				),
				'required' => array( 'taxonomy' ),
				'additionalProperties' => false,
			),
			array( $this, 'term_list' ),
			array( $this, 'can_manage_terms_input' ),
			true,
			false,
			true
		);
		$write = array(
			'type' => 'object',
			'properties' => array(
				'taxonomy' => array(
					'type' => 'string',
					'minLength' => 1,
				),
				'name' => array(
					'type' => 'string',
					'minLength' => 1,
				),
				'slug' => array( 'type' => 'string' ),
				'description' => array( 'type' => 'string' ),
				'parent' => array(
					'type' => 'integer',
					'minimum' => 0,
				),
			),
			'required' => array( 'taxonomy', 'name' ),
			'additionalProperties' => false,
		);
		$this->register( 'wordpress/term-create', __( 'Create WordPress Term', 'wp-ability' ), __( 'Creates a term in a registered taxonomy.', 'wp-ability' ), $write, array( $this, 'term_create' ), array( $this, 'can_manage_terms_input' ), false, false, false );
		$update = $write;
		$update['properties']['term_id'] = array(
			'type' => 'integer',
			'minimum' => 1,
		);
		$update['required'] = array( 'taxonomy', 'term_id' );
		$this->register( 'wordpress/term-update', __( 'Update WordPress Term', 'wp-ability' ), __( 'Updates a term in a registered taxonomy.', 'wp-ability' ), $update, array( $this, 'term_update' ), array( $this, 'can_manage_terms_input' ), false, false, true );
		$this->register(
			'wordpress/term-delete',
			__( 'Delete WordPress Term', 'wp-ability' ),
			__( 'Deletes a term from a registered taxonomy.', 'wp-ability' ),
			array(
				'type' => 'object',
				'properties' => array(
					'taxonomy' => array(
						'type' => 'string',
						'minLength' => 1,
					),
					'term_id' => array(
						'type' => 'integer',
						'minimum' => 1,
					),
				),
				'required' => array( 'taxonomy', 'term_id' ),
				'additionalProperties' => false,
			),
			array( $this, 'term_delete' ),
			array( $this, 'can_manage_terms_input' ),
			false,
			true,
			true
		);
	}

	/**
	 * Check whether the current user can perform this ability.
	 *
	 * @param array $input Ability input.
	 * @return bool
	 */
	public function can_manage_terms_input( $input = array() ) {
		$taxonomy = isset( $input['taxonomy'] ) ? sanitize_key( $input['taxonomy'] ) : '';
		$obj      = $taxonomy ? get_taxonomy( $taxonomy ) : false;
		return $obj && current_user_can( $obj->cap->manage_terms );
	}

	/**
	 * Build a normalized term payload.
	 *
	 * @param \WP_Term $term Term object.
	 * @return array
	 */
	private function term_payload( \WP_Term $term ) {
		return array(
			'term_id'     => (int) $term->term_id,
			'taxonomy'    => $term->taxonomy,
			'name'        => $term->name,
			'slug'        => $term->slug,
			'description' => $term->description,
			'parent'      => (int) $term->parent,
			'count'       => (int) $term->count,
		);
	}

	/**
	 * Execute the term list ability.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public function term_list( array $input ) {
		$taxonomy = sanitize_key( $input['taxonomy'] );
		$args = array(
			'taxonomy' => $taxonomy,
			'hide_empty' => ! empty( $input['hide_empty'] ),
		);
		if ( ! empty( $input['search'] ) ) {
			$args['search'] = sanitize_text_field( $input['search'] );
		}
		$terms = get_terms( $args );
		if ( is_wp_error( $terms ) ) {
			return $terms;
		}
		return array( 'terms' => array_map( array( $this, 'term_payload' ), $terms ) );
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'wp_abilities_api_init', array( $this, 'register_abilities' ) );
	}

	/**
	 * Register all additional Core abilities.
	 *
	 * @return void
	 */
	public function register_abilities() {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		$this->register_users();
		$this->register_posts();
		$this->register_terms();
		$this->register_media();
		$this->register_options();
		$this->register_cron();
		$this->register_transients();
		$this->register_maintenance();
	}

	/**
	 * Register an ability with shared defaults.
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

	/**
	 * Register post abilities.
	 *
	 * @return void
	 */
	private function register_posts() {
		$this->register(
			'wordpress/post-list',
			__( 'List WordPress Posts', 'wp-ability' ),
			__( 'Lists posts for a requested public or registered post type.', 'wp-ability' ),
			array(
				'type' => 'object',
				'properties' => array(
					'post_type' => array(
						'type' => 'string',
						'default' => 'post',
					),
					'post_status' => array(
						'type' => 'string',
						'default' => 'any',
					),
					'search' => array( 'type' => 'string' ),
					'per_page' => array(
						'type' => 'integer',
						'minimum' => 1,
						'maximum' => 100,
						'default' => 20,
					),
					'page' => array(
						'type' => 'integer',
						'minimum' => 1,
						'default' => 1,
					),
				),
				'additionalProperties' => false,
			),
			array( $this, 'post_list' ),
			array( $this, 'can_edit_posts' ),
			true,
			false,
			true
		);
		$id_schema = array(
			'type' => 'object',
			'properties' => array(
				'post_id' => array(
					'type' => 'integer',
					'minimum' => 1,
				),
			),
			'required' => array( 'post_id' ),
			'additionalProperties' => false,
		);
		$this->register( 'wordpress/post-get', __( 'Get WordPress Post', 'wp-ability' ), __( 'Returns one WordPress post or custom post type item.', 'wp-ability' ), $id_schema, array( $this, 'post_get' ), array( $this, 'can_read_post_input' ), true, false, true );
		$write_schema = array(
			'type' => 'object',
			'properties' => array(
				'post_type' => array(
					'type' => 'string',
					'default' => 'post',
				),
				'post_status' => array(
					'type' => 'string',
					'default' => 'draft',
				),
				'post_title' => array( 'type' => 'string' ),
				'post_content' => array( 'type' => 'string' ),
				'post_excerpt' => array( 'type' => 'string' ),
				'post_author' => array(
					'type' => 'integer',
					'minimum' => 1,
				),
				'post_parent' => array(
					'type' => 'integer',
					'minimum' => 0,
				),
				'menu_order' => array( 'type' => 'integer' ),
			),
			'additionalProperties' => false,
		);
		$this->register( 'wordpress/post-create', __( 'Create WordPress Post', 'wp-ability' ), __( 'Creates a post, page, or registered custom post type item.', 'wp-ability' ), $write_schema, array( $this, 'post_create' ), array( $this, 'can_edit_posts' ), false, false, false );
		$update_schema = $write_schema;
		$update_schema['properties']['post_id'] = array(
			'type' => 'integer',
			'minimum' => 1,
		);
		$update_schema['required'] = array( 'post_id' );
		$this->register( 'wordpress/post-update', __( 'Update WordPress Post', 'wp-ability' ), __( 'Updates editable fields on an existing WordPress post.', 'wp-ability' ), $update_schema, array( $this, 'post_update' ), array( $this, 'can_edit_post_input' ), false, false, true );
		$this->register(
			'wordpress/post-delete',
			__( 'Delete WordPress Post', 'wp-ability' ),
			__( 'Moves a post to Trash or permanently deletes it.', 'wp-ability' ),
			array(
				'type' => 'object',
				'properties' => array(
					'post_id' => array(
						'type' => 'integer',
						'minimum' => 1,
					),
					'force' => array(
						'type' => 'boolean',
						'default' => false,
					),
				),
				'required' => array( 'post_id' ),
				'additionalProperties' => false,
			),
			array( $this, 'post_delete' ),
			array( $this, 'can_delete_post_input' ),
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
	public function can_edit_posts() {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Check whether the current user can perform this ability.
	 *
	 * @param array $input Ability input.
	 * @return bool
	 */
	public function can_read_post_input( $input = array() ) {
		$id = isset( $input['post_id'] ) ? (int) $input['post_id'] : 0;
		return $id > 0 && current_user_can( 'read_post', $id );
	}

	/**
	 * Check whether the current user can perform this ability.
	 *
	 * @param array $input Ability input.
	 * @return bool
	 */
	public function can_edit_post_input( $input = array() ) {
		$id = isset( $input['post_id'] ) ? (int) $input['post_id'] : 0;
		return $id > 0 && current_user_can( 'edit_post', $id );
	}

	/**
	 * Check whether the current user can perform this ability.
	 *
	 * @param array $input Ability input.
	 * @return bool
	 */
	public function can_delete_post_input( $input = array() ) {
		$id = isset( $input['post_id'] ) ? (int) $input['post_id'] : 0;
		return $id > 0 && current_user_can( 'delete_post', $id );
	}

	/**
	 * Build a normalized post payload.
	 *
	 * @param \WP_Post $post Post object.
	 * @return array
	 */
	private function post_payload( \WP_Post $post ) {
		return array(
			'post_id'      => (int) $post->ID,
			'post_type'    => $post->post_type,
			'post_status'  => $post->post_status,
			'post_title'   => $post->post_title,
			'post_content' => $post->post_content,
			'post_excerpt' => $post->post_excerpt,
			'post_author'  => (int) $post->post_author,
			'post_parent'  => (int) $post->post_parent,
			'menu_order'   => (int) $post->menu_order,
			'date_gmt'     => $post->post_date_gmt,
			'modified_gmt' => $post->post_modified_gmt,
		);
	}

	/**
	 * Execute the post list ability.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public function post_list( array $input ) {
		$type = isset( $input['post_type'] ) ? sanitize_key( $input['post_type'] ) : 'post';
		if ( ! post_type_exists( $type ) ) {
			return new \WP_Error( 'wp_ability_post_type_not_found', __( 'Post type not found.', 'wp-ability' ) );
		}
		$args = array(
			'post_type'      => $type,
			'post_status'    => isset( $input['post_status'] ) ? sanitize_key( $input['post_status'] ) : 'any',
			'posts_per_page' => isset( $input['per_page'] ) ? min( 100, (int) $input['per_page'] ) : 20,
			'paged'          => isset( $input['page'] ) ? max( 1, (int) $input['page'] ) : 1,
		);
		if ( ! empty( $input['search'] ) ) {
			$args['s'] = sanitize_text_field( $input['search'] );
		}
		$query = new \WP_Query( $args );
		return array(
			'posts' => array_map( array( $this, 'post_payload' ), $query->posts ),
			'total' => (int) $query->found_posts,
			'pages' => (int) $query->max_num_pages,
		);
	}

	/**
	 * Execute the post get ability.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public function post_get( array $input ) {
		$post = get_post( (int) $input['post_id'] );
		return $post ? $this->post_payload( $post ) : new \WP_Error( 'wp_ability_post_not_found', __( 'Post not found.', 'wp-ability' ) );
	}

	/**
	 * Sanitize post ability input.
	 *
	 * @param array $input Ability input.
	 * @return array
	 */
	private function sanitize_post_input( array $input ) {
		$data = array();
		if ( isset( $input['post_type'] ) ) {
			$data['post_type'] = sanitize_key( $input['post_type'] );
		}
		if ( isset( $input['post_status'] ) ) {
			$data['post_status'] = sanitize_key( $input['post_status'] );
		}
		foreach ( array( 'post_title', 'post_excerpt' ) as $field ) {
			if ( array_key_exists( $field, $input ) ) {
				$data[ $field ] = sanitize_text_field( $input[ $field ] );
			}
		}
		if ( array_key_exists( 'post_content', $input ) ) {
			$data['post_content'] = wp_kses_post( $input['post_content'] );
		}
		foreach ( array( 'post_author', 'post_parent', 'menu_order' ) as $field ) {
			if ( array_key_exists( $field, $input ) ) {
				$data[ $field ] = (int) $input[ $field ];
			}
		}
		return $data;
	}

	/**
	 * Execute the post create ability.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public function post_create( array $input ) {
		$data = $this->sanitize_post_input( $input );
		if ( empty( $data['post_type'] ) ) {
			$data['post_type'] = 'post';
		}
		if ( ! post_type_exists( $data['post_type'] ) ) {
			return new \WP_Error( 'wp_ability_post_type_not_found', __( 'Post type not found.', 'wp-ability' ) );
		}
		$obj = get_post_type_object( $data['post_type'] );
		if ( ! $obj || ! current_user_can( $obj->cap->create_posts ) ) {
			return new \WP_Error( 'wp_ability_cannot_create_post', __( 'Current user cannot create this post type.', 'wp-ability' ) );
		}
		$id = wp_insert_post( $data, true );
		return is_wp_error( $id ) ? $id : $this->post_get( array( 'post_id' => $id ) );
	}

	/**
	 * Execute the post update ability.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public function post_update( array $input ) {
		$data       = $this->sanitize_post_input( $input );
		$data['ID'] = (int) $input['post_id'];
		$id         = wp_update_post( $data, true );
		return is_wp_error( $id ) ? $id : $this->post_get( array( 'post_id' => $id ) );
	}

	/**
	 * Execute the post delete ability.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public function post_delete( array $input ) {
		$id    = (int) $input['post_id'];
		$force = ! empty( $input['force'] );
		$post  = wp_delete_post( $id, $force );
		return $post ? array(
			'post_id' => $id,
			'deleted' => true,
			'force' => $force,
		) : new \WP_Error( 'wp_ability_post_delete_failed', __( 'Post could not be deleted.', 'wp-ability' ) );
	}

	/**
	 * Register taxonomy term abilities.
	 *
	 * @return void
	 */
	private function register_terms() {
		$this->register(
			'wordpress/term-list',
			__( 'List WordPress Terms', 'wp-ability' ),
			__( 'Lists terms from a registered taxonomy.', 'wp-ability' ),
			array(
				'type' => 'object',
				'properties' => array(
					'taxonomy' => array(
						'type' => 'string',
						'minLength' => 1,
					),
					'search' => array( 'type' => 'string' ),
					'hide_empty' => array(
						'type' => 'boolean',
						'default' => false,
					),
				),
				'required' => array( 'taxonomy' ),
				'additionalProperties' => false,
			),
			array( $this, 'term_list' ),
			array( $this, 'can_manage_terms_input' ),
			true,
			false,
			true
		);
		$write = array(
			'type' => 'object',
			'properties' => array(
				'taxonomy' => array(
					'type' => 'string',
					'minLength' => 1,
				),
				'name' => array(
					'type' => 'string',
					'minLength' => 1,
				),
				'slug' => array( 'type' => 'string' ),
				'description' => array( 'type' => 'string' ),
				'parent' => array(
					'type' => 'integer',
					'minimum' => 0,
				),
			),
			'required' => array( 'taxonomy', 'name' ),
			'additionalProperties' => false,
		);
		$this->register( 'wordpress/term-create', __( 'Create WordPress Term', 'wp-ability' ), __( 'Creates a term in a registered taxonomy.', 'wp-ability' ), $write, array( $this, 'term_create' ), array( $this, 'can_manage_terms_input' ), false, false, false );
		$update = $write;
		$update['properties']['term_id'] = array(
			'type' => 'integer',
			'minimum' => 1,
		);
		$update['required'] = array( 'taxonomy', 'term_id' );
		$this->register( 'wordpress/term-update', __( 'Update WordPress Term', 'wp-ability' ), __( 'Updates a term in a registered taxonomy.', 'wp-ability' ), $update, array( $this, 'term_update' ), array( $this, 'can_manage_terms_input' ), false, false, true );
		$this->register(
			'wordpress/term-delete',
			__( 'Delete WordPress Term', 'wp-ability' ),
			__( 'Deletes a term from a registered taxonomy.', 'wp-ability' ),
			array(
				'type' => 'object',
				'properties' => array(
					'taxonomy' => array(
						'type' => 'string',
						'minLength' => 1,
					),
					'term_id' => array(
						'type' => 'integer',
						'minimum' => 1,
					),
				),
				'required' => array( 'taxonomy', 'term_id' ),
				'additionalProperties' => false,
			),
			array( $this, 'term_delete' ),
			array( $this, 'can_manage_terms_input' ),
			false,
			true,
			true
		);
	}

	/**
	 * Check whether the current user can perform this ability.
	 *
	 * @param array $input Ability input.
	 * @return bool
	 */
	public function can_manage_terms_input( $input = array() ) {
		$taxonomy = isset( $input['taxonomy'] ) ? sanitize_key( $input['taxonomy'] ) : '';
		$obj      = $taxonomy ? get_taxonomy( $taxonomy ) : false;
		return $obj && current_user_can( $obj->cap->manage_terms );
	}

	/**
	 * Build a normalized term payload.
	 *
	 * @param \WP_Term $term Term object.
	 * @return array
	 */
	private function term_payload( \WP_Term $term ) {
		return array(
			'term_id'     => (int) $term->term_id,
			'taxonomy'    => $term->taxonomy,
			'name'        => $term->name,
			'slug'        => $term->slug,
			'description' => $term->description,
			'parent'      => (int) $term->parent,
			'count'       => (int) $term->count,
		);
	}

	/**
	 * Execute the term list ability.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public function term_list( array $input ) {
		$taxonomy = sanitize_key( $input['taxonomy'] );
		$args = array(
			'taxonomy' => $taxonomy,
			'hide_empty' => ! empty( $input['hide_empty'] ),
		);
		if ( ! empty( $input['search'] ) ) {
			$args['search'] = sanitize_text_field( $input['search'] );
		}
		$terms = get_terms( $args );
		if ( is_wp_error( $terms ) ) {
			return $terms;
		}
		return array( 'terms' => array_map( array( $this, 'term_payload' ), $terms ) );
	}

	/**
	 * Build taxonomy term arguments.
	 *
	 * @param array $input Ability input.
	 * @return array
	 */
	private function term_args( array $input ) {
		$args = array();
		if ( isset( $input['slug'] ) ) {
			$args['slug'] = sanitize_title( $input['slug'] );
		}
		if ( isset( $input['description'] ) ) {
			$args['description'] = sanitize_textarea_field( $input['description'] );
		}
		if ( isset( $input['parent'] ) ) {
			$args['parent'] = (int) $input['parent'];
		}
		return $args;
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'wp_abilities_api_init', array( $this, 'register_abilities' ) );
	}

	/**
	 * Register all additional Core abilities.
	 *
	 * @return void
	 */
	public function register_abilities() {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		$this->register_users();
		$this->register_posts();
		$this->register_terms();
		$this->register_media();
		$this->register_options();
		$this->register_cron();
		$this->register_transients();
		$this->register_maintenance();
	}

	/**
	 * Register an ability with shared defaults.
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

	/**
	 * Register post abilities.
	 *
	 * @return void
	 */
	private function register_posts() {
		$this->register(
			'wordpress/post-list',
			__( 'List WordPress Posts', 'wp-ability' ),
			__( 'Lists posts for a requested public or registered post type.', 'wp-ability' ),
			array(
				'type' => 'object',
				'properties' => array(
					'post_type' => array(
						'type' => 'string',
						'default' => 'post',
					),
					'post_status' => array(
						'type' => 'string',
						'default' => 'any',
					),
					'search' => array( 'type' => 'string' ),
					'per_page' => array(
						'type' => 'integer',
						'minimum' => 1,
						'maximum' => 100,
						'default' => 20,
					),
					'page' => array(
						'type' => 'integer',
						'minimum' => 1,
						'default' => 1,
					),
				),
				'additionalProperties' => false,
			),
			array( $this, 'post_list' ),
			array( $this, 'can_edit_posts' ),
			true,
			false,
			true
		);
		$id_schema = array(
			'type' => 'object',
			'properties' => array(
				'post_id' => array(
					'type' => 'integer',
					'minimum' => 1,
				),
			),
			'required' => array( 'post_id' ),
			'additionalProperties' => false,
		);
		$this->register( 'wordpress/post-get', __( 'Get WordPress Post', 'wp-ability' ), __( 'Returns one WordPress post or custom post type item.', 'wp-ability' ), $id_schema, array( $this, 'post_get' ), array( $this, 'can_read_post_input' ), true, false, true );
		$write_schema = array(
			'type' => 'object',
			'properties' => array(
				'post_type' => array(
					'type' => 'string',
					'default' => 'post',
				),
				'post_status' => array(
					'type' => 'string',
					'default' => 'draft',
				),
				'post_title' => array( 'type' => 'string' ),
				'post_content' => array( 'type' => 'string' ),
				'post_excerpt' => array( 'type' => 'string' ),
				'post_author' => array(
					'type' => 'integer',
					'minimum' => 1,
				),
				'post_parent' => array(
					'type' => 'integer',
					'minimum' => 0,
				),
				'menu_order' => array( 'type' => 'integer' ),
			),
			'additionalProperties' => false,
		);
		$this->register( 'wordpress/post-create', __( 'Create WordPress Post', 'wp-ability' ), __( 'Creates a post, page, or registered custom post type item.', 'wp-ability' ), $write_schema, array( $this, 'post_create' ), array( $this, 'can_edit_posts' ), false, false, false );
		$update_schema = $write_schema;
		$update_schema['properties']['post_id'] = array(
			'type' => 'integer',
			'minimum' => 1,
		);
		$update_schema['required'] = array( 'post_id' );
		$this->register( 'wordpress/post-update', __( 'Update WordPress Post', 'wp-ability' ), __( 'Updates editable fields on an existing WordPress post.', 'wp-ability' ), $update_schema, array( $this, 'post_update' ), array( $this, 'can_edit_post_input' ), false, false, true );
		$this->register(
			'wordpress/post-delete',
			__( 'Delete WordPress Post', 'wp-ability' ),
			__( 'Moves a post to Trash or permanently deletes it.', 'wp-ability' ),
			array(
				'type' => 'object',
				'properties' => array(
					'post_id' => array(
						'type' => 'integer',
						'minimum' => 1,
					),
					'force' => array(
						'type' => 'boolean',
						'default' => false,
					),
				),
				'required' => array( 'post_id' ),
				'additionalProperties' => false,
			),
			array( $this, 'post_delete' ),
			array( $this, 'can_delete_post_input' ),
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
	public function can_edit_posts() {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Check whether the current user can perform this ability.
	 *
	 * @param array $input Ability input.
	 * @return bool
	 */
	public function can_read_post_input( $input = array() ) {
		$id = isset( $input['post_id'] ) ? (int) $input['post_id'] : 0;
		return $id > 0 && current_user_can( 'read_post', $id );
	}

	/**
	 * Check whether the current user can perform this ability.
	 *
	 * @param array $input Ability input.
	 * @return bool
	 */
	public function can_edit_post_input( $input = array() ) {
		$id = isset( $input['post_id'] ) ? (int) $input['post_id'] : 0;
		return $id > 0 && current_user_can( 'edit_post', $id );
	}

	/**
	 * Check whether the current user can perform this ability.
	 *
	 * @param array $input Ability input.
	 * @return bool
	 */
	public function can_delete_post_input( $input = array() ) {
		$id = isset( $input['post_id'] ) ? (int) $input['post_id'] : 0;
		return $id > 0 && current_user_can( 'delete_post', $id );
	}

	/**
	 * Build a normalized post payload.
	 *
	 * @param \WP_Post $post Post object.
	 * @return array
	 */
	private function post_payload( \WP_Post $post ) {
		return array(
			'post_id'      => (int) $post->ID,
			'post_type'    => $post->post_type,
			'post_status'  => $post->post_status,
			'post_title'   => $post->post_title,
			'post_content' => $post->post_content,
			'post_excerpt' => $post->post_excerpt,
			'post_author'  => (int) $post->post_author,
			'post_parent'  => (int) $post->post_parent,
			'menu_order'   => (int) $post->menu_order,
			'date_gmt'     => $post->post_date_gmt,
			'modified_gmt' => $post->post_modified_gmt,
		);
	}

	/**
	 * Execute the post list ability.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public function post_list( array $input ) {
		$type = isset( $input['post_type'] ) ? sanitize_key( $input['post_type'] ) : 'post';
		if ( ! post_type_exists( $type ) ) {
			return new \WP_Error( 'wp_ability_post_type_not_found', __( 'Post type not found.', 'wp-ability' ) );
		}
		$args = array(
			'post_type'      => $type,
			'post_status'    => isset( $input['post_status'] ) ? sanitize_key( $input['post_status'] ) : 'any',
			'posts_per_page' => isset( $input['per_page'] ) ? min( 100, (int) $input['per_page'] ) : 20,
			'paged'          => isset( $input['page'] ) ? max( 1, (int) $input['page'] ) : 1,
		);
		if ( ! empty( $input['search'] ) ) {
			$args['s'] = sanitize_text_field( $input['search'] );
		}
		$query = new \WP_Query( $args );
		return array(
			'posts' => array_map( array( $this, 'post_payload' ), $query->posts ),
			'total' => (int) $query->found_posts,
			'pages' => (int) $query->max_num_pages,
		);
	}

	/**
	 * Execute the post get ability.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public function post_get( array $input ) {
		$post = get_post( (int) $input['post_id'] );
		return $post ? $this->post_payload( $post ) : new \WP_Error( 'wp_ability_post_not_found', __( 'Post not found.', 'wp-ability' ) );
	}

	/**
	 * Sanitize post ability input.
	 *
	 * @param array $input Ability input.
	 * @return array
	 */
	private function sanitize_post_input( array $input ) {
		$data = array();
		if ( isset( $input['post_type'] ) ) {
			$data['post_type'] = sanitize_key( $input['post_type'] );
		}
		if ( isset( $input['post_status'] ) ) {
			$data['post_status'] = sanitize_key( $input['post_status'] );
		}
		foreach ( array( 'post_title', 'post_excerpt' ) as $field ) {
			if ( array_key_exists( $field, $input ) ) {
				$data[ $field ] = sanitize_text_field( $input[ $field ] );
			}
		}
		if ( array_key_exists( 'post_content', $input ) ) {
			$data['post_content'] = wp_kses_post( $input['post_content'] );
		}
		foreach ( array( 'post_author', 'post_parent', 'menu_order' ) as $field ) {
			if ( array_key_exists( $field, $input ) ) {
				$data[ $field ] = (int) $input[ $field ];
			}
		}
		return $data;
	}

	/**
	 * Execute the post create ability.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public function post_create( array $input ) {
		$data = $this->sanitize_post_input( $input );
		if ( empty( $data['post_type'] ) ) {
			$data['post_type'] = 'post';
		}
		if ( ! post_type_exists( $data['post_type'] ) ) {
			return new \WP_Error( 'wp_ability_post_type_not_found', __( 'Post type not found.', 'wp-ability' ) );
		}
		$obj = get_post_type_object( $data['post_type'] );
		if ( ! $obj || ! current_user_can( $obj->cap->create_posts ) ) {
			return new \WP_Error( 'wp_ability_cannot_create_post', __( 'Current user cannot create this post type.', 'wp-ability' ) );
		}
		$id = wp_insert_post( $data, true );
		return is_wp_error( $id ) ? $id : $this->post_get( array( 'post_id' => $id ) );
	}

	/**
	 * Execute the post update ability.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public function post_update( array $input ) {
		$data       = $this->sanitize_post_input( $input );
		$data['ID'] = (int) $input['post_id'];
		$id         = wp_update_post( $data, true );
		return is_wp_error( $id ) ? $id : $this->post_get( array( 'post_id' => $id ) );
	}

	/**
	 * Execute the post delete ability.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public function post_delete( array $input ) {
		$id    = (int) $input['post_id'];
		$force = ! empty( $input['force'] );
		$post  = wp_delete_post( $id, $force );
		return $post ? array(
			'post_id' => $id,
			'deleted' => true,
			'force' => $force,
		) : new \WP_Error( 'wp_ability_post_delete_failed', __( 'Post could not be deleted.', 'wp-ability' ) );
	}

	/**
	 * Register taxonomy term abilities.
	 *
	 * @return void
	 */
	private function register_terms() {
		$this->register(
			'wordpress/term-list',
			__( 'List WordPress Terms', 'wp-ability' ),
			__( 'Lists terms from a registered taxonomy.', 'wp-ability' ),
			array(
				'type' => 'object',
				'properties' => array(
					'taxonomy' => array(
						'type' => 'string',
						'minLength' => 1,
					),
					'search' => array( 'type' => 'string' ),
					'hide_empty' => array(
						'type' => 'boolean',
						'default' => false,
					),
				),
				'required' => array( 'taxonomy' ),
				'additionalProperties' => false,
			),
			array( $this, 'term_list' ),
			array( $this, 'can_manage_terms_input' ),
			true,
			false,
			true
		);
		$write = array(
			'type' => 'object',
			'properties' => array(
				'taxonomy' => array(
					'type' => 'string',
					'minLength' => 1,
				),
				'name' => array(
					'type' => 'string',
					'minLength' => 1,
				),
				'slug' => array( 'type' => 'string' ),
				'description' => array( 'type' => 'string' ),
				'parent' => array(
					'type' => 'integer',
					'minimum' => 0,
				),
			),
			'required' => array( 'taxonomy', 'name' ),
			'additionalProperties' => false,
		);
		$this->register( 'wordpress/term-create', __( 'Create WordPress Term', 'wp-ability' ), __( 'Creates a term in a registered taxonomy.', 'wp-ability' ), $write, array( $this, 'term_create' ), array( $this, 'can_manage_terms_input' ), false, false, false );
		$update = $write;
		$update['properties']['term_id'] = array(
			'type' => 'integer',
			'minimum' => 1,
		);
		$update['required'] = array( 'taxonomy', 'term_id' );
		$this->register( 'wordpress/term-update', __( 'Update WordPress Term', 'wp-ability' ), __( 'Updates a term in a registered taxonomy.', 'wp-ability' ), $update, array( $this, 'term_update' ), array( $this, 'can_manage_terms_input' ), false, false, true );
		$this->register(
			'wordpress/term-delete',
			__( 'Delete WordPress Term', 'wp-ability' ),
			__( 'Deletes a term from a registered taxonomy.', 'wp-ability' ),
			array(
				'type' => 'object',
				'properties' => array(
					'taxonomy' => array(
						'type' => 'string',
						'minLength' => 1,
					),
					'term_id' => array(
						'type' => 'integer',
						'minimum' => 1,
					),
				),
				'required' => array( 'taxonomy', 'term_id' ),
				'additionalProperties' => false,
			),
			array( $this, 'term_delete' ),
			array( $this, 'can_manage_terms_input' ),
			false,
			true,
			true
		);
	}

	/**
	 * Check whether the current user can perform this ability.
	 *
	 * @param array $input Ability input.
	 * @return bool
	 */
	public function can_manage_terms_input( $input = array() ) {
		$taxonomy = isset( $input['taxonomy'] ) ? sanitize_key( $input['taxonomy'] ) : '';
		$obj      = $taxonomy ? get_taxonomy( $taxonomy ) : false;
		return $obj && current_user_can( $obj->cap->manage_terms );
	}

	/**
	 * Build a normalized term payload.
	 *
	 * @param \WP_Term $term Term object.
	 * @return array
	 */
	private function term_payload( \WP_Term $term ) {
		return array(
			'term_id'     => (int) $term->term_id,
			'taxonomy'    => $term->taxonomy,
			'name'        => $term->name,
			'slug'        => $term->slug,
			'description' => $term->description,
			'parent'      => (int) $term->parent,
			'count'       => (int) $term->count,
		);
	}

	/**
	 * Execute the term list ability.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public function term_list( array $input ) {
		$taxonomy = sanitize_key( $input['taxonomy'] );
		$args = array(
			'taxonomy' => $taxonomy,
			'hide_empty' => ! empty( $input['hide_empty'] ),
		);
		if ( ! empty( $input['search'] ) ) {
			$args['search'] = sanitize_text_field( $input['search'] );
		}
		$terms = get_terms( $args );
		if ( is_wp_error( $terms ) ) {
			return $terms;
		}
		return array( 'terms' => array_map( array( $this, 'term_payload' ), $terms ) );
	}

	/**
	 * Build taxonomy term arguments.
	 *
	 * @param array $input Ability input.
	 * @return array
	 */
	private function term_args( array $input ) {
		$args = array();
		if ( isset( $input['slug'] ) ) {
			$args['slug'] = sanitize_title( $input['slug'] );
		}
		if ( isset( $input['description'] ) ) {
			$args['description'] = sanitize_textarea_field( $input['description'] );
		}
		if ( isset( $input['parent'] ) ) {
			$args['parent'] = (int) $input['parent'];
		}
		return $args;
	}

	/**
	 * Execute the term create ability.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public function term_create( array $input ) {
		$result = wp_insert_term( sanitize_text_field( $input['name'] ), sanitize_key( $input['taxonomy'] ), $this->term_args( $input ) );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$term = get_term( $result['term_id'], sanitize_key( $input['taxonomy'] ) );
		return $term instanceof \WP_Term ? $this->term_payload( $term ) : new \WP_Error( 'wp_ability_term_create_failed', __( 'Term could not be loaded after creation.', 'wp-ability' ) );
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'wp_abilities_api_init', array( $this, 'register_abilities' ) );
	}

	/**
	 * Register all additional Core abilities.
	 *
	 * @return void
	 */
	public function register_abilities() {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		$this->register_users();
		$this->register_posts();
		$this->register_terms();
		$this->register_media();
		$this->register_options();
		$this->register_cron();
		$this->register_transients();
		$this->register_maintenance();
	}

	/**
	 * Register an ability with shared defaults.
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

	/**
	 * Register post abilities.
	 *
	 * @return void
	 */
	private function register_posts() {
		$this->register(
			'wordpress/post-list',
			__( 'List WordPress Posts', 'wp-ability' ),
			__( 'Lists posts for a requested public or registered post type.', 'wp-ability' ),
			array(
				'type' => 'object',
				'properties' => array(
					'post_type' => array(
						'type' => 'string',
						'default' => 'post',
					),
					'post_status' => array(
						'type' => 'string',
						'default' => 'any',
					),
					'search' => array( 'type' => 'string' ),
					'per_page' => array(
						'type' => 'integer',
						'minimum' => 1,
						'maximum' => 100,
						'default' => 20,
					),
					'page' => array(
						'type' => 'integer',
						'minimum' => 1,
						'default' => 1,
					),
				),
				'additionalProperties' => false,
			),
			array( $this, 'post_list' ),
			array( $this, 'can_edit_posts' ),
			true,
			false,
			true
		);
		$id_schema = array(
			'type' => 'object',
			'properties' => array(
				'post_id' => array(
					'type' => 'integer',
					'minimum' => 1,
				),
			),
			'required' => array( 'post_id' ),
			'additionalProperties' => false,
		);
		$this->register( 'wordpress/post-get', __( 'Get WordPress Post', 'wp-ability' ), __( 'Returns one WordPress post or custom post type item.', 'wp-ability' ), $id_schema, array( $this, 'post_get' ), array( $this, 'can_read_post_input' ), true, false, true );
		$write_schema = array(
			'type' => 'object',
			'properties' => array(
				'post_type' => array(
					'type' => 'string',
					'default' => 'post',
				),
				'post_status' => array(
					'type' => 'string',
					'default' => 'draft',
				),
				'post_title' => array( 'type' => 'string' ),
				'post_content' => array( 'type' => 'string' ),
				'post_excerpt' => array( 'type' => 'string' ),
				'post_author' => array(
					'type' => 'integer',
					'minimum' => 1,
				),
				'post_parent' => array(
					'type' => 'integer',
					'minimum' => 0,
				),
				'menu_order' => array( 'type' => 'integer' ),
			),
			'additionalProperties' => false,
		);
		$this->register( 'wordpress/post-create', __( 'Create WordPress Post', 'wp-ability' ), __( 'Creates a post, page, or registered custom post type item.', 'wp-ability' ), $write_schema, array( $this, 'post_create' ), array( $this, 'can_edit_posts' ), false, false, false );
		$update_schema = $write_schema;
		$update_schema['properties']['post_id'] = array(
			'type' => 'integer',
			'minimum' => 1,
		);
		$update_schema['required'] = array( 'post_id' );
		$this->register( 'wordpress/post-update', __( 'Update WordPress Post', 'wp-ability' ), __( 'Updates editable fields on an existing WordPress post.', 'wp-ability' ), $update_schema, array( $this, 'post_update' ), array( $this, 'can_edit_post_input' ), false, false, true );
		$this->register(
			'wordpress/post-delete',
			__( 'Delete WordPress Post', 'wp-ability' ),
			__( 'Moves a post to Trash or permanently deletes it.', 'wp-ability' ),
			array(
				'type' => 'object',
				'properties' => array(
					'post_id' => array(
						'type' => 'integer',
						'minimum' => 1,
					),
					'force' => array(
						'type' => 'boolean',
						'default' => false,
					),
				),
				'required' => array( 'post_id' ),
				'additionalProperties' => false,
			),
			array( $this, 'post_delete' ),
			array( $this, 'can_delete_post_input' ),
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
	public function can_edit_posts() {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Check whether the current user can perform this ability.
	 *
	 * @param array $input Ability input.
	 * @return bool
	 */
	public function can_read_post_input( $input = array() ) {
		$id = isset( $input['post_id'] ) ? (int) $input['post_id'] : 0;
		return $id > 0 && current_user_can( 'read_post', $id );
	}

	/**
	 * Check whether the current user can perform this ability.
	 *
	 * @param array $input Ability input.
	 * @return bool
	 */
	public function can_edit_post_input( $input = array() ) {
		$id = isset( $input['post_id'] ) ? (int) $input['post_id'] : 0;
		return $id > 0 && current_user_can( 'edit_post', $id );
	}

	/**
	 * Check whether the current user can perform this ability.
	 *
	 * @param array $input Ability input.
	 * @return bool
	 */
	public function can_delete_post_input( $input = array() ) {
		$id = isset( $input['post_id'] ) ? (int) $input['post_id'] : 0;
		return $id > 0 && current_user_can( 'delete_post', $id );
	}

	/**
	 * Build a normalized post payload.
	 *
	 * @param \WP_Post $post Post object.
	 * @return array
	 */
	private function post_payload( \WP_Post $post ) {
		return array(
			'post_id'      => (int) $post->ID,
			'post_type'    => $post->post_type,
			'post_status'  => $post->post_status,
			'post_title'   => $post->post_title,
			'post_content' => $post->post_content,
			'post_excerpt' => $post->post_excerpt,
			'post_author'  => (int) $post->post_author,
			'post_parent'  => (int) $post->post_parent,
			'menu_order'   => (int) $post->menu_order,
			'date_gmt'     => $post->post_date_gmt,
			'modified_gmt' => $post->post_modified_gmt,
		);
	}

	/**
	 * Execute the post list ability.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public function post_list( array $input ) {
		$type = isset( $input['post_type'] ) ? sanitize_key( $input['post_type'] ) : 'post';
		if ( ! post_type_exists( $type ) ) {
			return new \WP_Error( 'wp_ability_post_type_not_found', __( 'Post type not found.', 'wp-ability' ) );
		}
		$args = array(
			'post_type'      => $type,
			'post_status'    => isset( $input['post_status'] ) ? sanitize_key( $input['post_status'] ) : 'any',
			'posts_per_page' => isset( $input['per_page'] ) ? min( 100, (int) $input['per_page'] ) : 20,
			'paged'          => isset( $input['page'] ) ? max( 1, (int) $input['page'] ) : 1,
		);
		if ( ! empty( $input['search'] ) ) {
			$args['s'] = sanitize_text_field( $input['search'] );
		}
		$query = new \WP_Query( $args );
		return array(
			'posts' => array_map( array( $this, 'post_payload' ), $query->posts ),
			'total' => (int) $query->found_posts,
			'pages' => (int) $query->max_num_pages,
		);
	}

	/**
	 * Execute the post get ability.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public function post_get( array $input ) {
		$post = get_post( (int) $input['post_id'] );
		return $post ? $this->post_payload( $post ) : new \WP_Error( 'wp_ability_post_not_found', __( 'Post not found.', 'wp-ability' ) );
	}

	/**
	 * Sanitize post ability input.
	 *
	 * @param array $input Ability input.
	 * @return array
	 */
	private function sanitize_post_input( array $input ) {
		$data = array();
		if ( isset( $input['post_type'] ) ) {
			$data['post_type'] = sanitize_key( $input['post_type'] );
		}
		if ( isset( $input['post_status'] ) ) {
			$data['post_status'] = sanitize_key( $input['post_status'] );
		}
		foreach ( array( 'post_title', 'post_excerpt' ) as $field ) {
			if ( array_key_exists( $field, $input ) ) {
				$data[ $field ] = sanitize_text_field( $input[ $field ] );
			}
		}
		if ( array_key_exists( 'post_content', $input ) ) {
			$data['post_content'] = wp_kses_post( $input['post_content'] );
		}
		foreach ( array( 'post_author', 'post_parent', 'menu_order' ) as $field ) {
			if ( array_key_exists( $field, $input ) ) {
				$data[ $field ] = (int) $input[ $field ];
			}
		}
		return $data;
	}

	/**
	 * Execute the post create ability.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public function post_create( array $input ) {
		$data = $this->sanitize_post_input( $input );
		if ( empty( $data['post_type'] ) ) {
			$data['post_type'] = 'post';
		}
		if ( ! post_type_exists( $data['post_type'] ) ) {
			return new \WP_Error( 'wp_ability_post_type_not_found', __( 'Post type not found.', 'wp-ability' ) );
		}
		$obj = get_post_type_object( $data['post_type'] );
		if ( ! $obj || ! current_user_can( $obj->cap->create_posts ) ) {
			return new \WP_Error( 'wp_ability_cannot_create_post', __( 'Current user cannot create this post type.', 'wp-ability' ) );
		}
		$id = wp_insert_post( $data, true );
		return is_wp_error( $id ) ? $id : $this->post_get( array( 'post_id' => $id ) );
	}

	/**
	 * Execute the post update ability.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public function post_update( array $input ) {
		$data       = $this->sanitize_post_input( $input );
		$data['ID'] = (int) $input['post_id'];
		$id         = wp_update_post( $data, true );
		return is_wp_error( $id ) ? $id : $this->post_get( array( 'post_id' => $id ) );
	}

	/**
	 * Execute the post delete ability.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public function post_delete( array $input ) {
		$id    = (int) $input['post_id'];
		$force = ! empty( $input['force'] );
		$post  = wp_delete_post( $id, $force );
		return $post ? array(
			'post_id' => $id,
			'deleted' => true,
			'force' => $force,
		) : new \WP_Error( 'wp_ability_post_delete_failed', __( 'Post could not be deleted.', 'wp-ability' ) );
	}

	/**
	 * Register taxonomy term abilities.
	 *
	 * @return void
	 */
	private function register_terms() {
		$this->register(
			'wordpress/term-list',
			__( 'List WordPress Terms', 'wp-ability' ),
			__( 'Lists terms from a registered taxonomy.', 'wp-ability' ),
			array(
				'type' => 'object',
				'properties' => array(
					'taxonomy' => array(
						'type' => 'string',
						'minLength' => 1,
					),
					'search' => array( 'type' => 'string' ),
					'hide_empty' => array(
						'type' => 'boolean',
						'default' => false,
					),
				),
				'required' => array( 'taxonomy' ),
				'additionalProperties' => false,
			),
			array( $this, 'term_list' ),
			array( $this, 'can_manage_terms_input' ),
			true,
			false,
			true
		);
		$write = array(
			'type' => 'object',
			'properties' => array(
				'taxonomy' => array(
					'type' => 'string',
					'minLength' => 1,
				),
				'name' => array(
					'type' => 'string',
					'minLength' => 1,
				),
				'slug' => array( 'type' => 'string' ),
				'description' => array( 'type' => 'string' ),
				'parent' => array(
					'type' => 'integer',
					'minimum' => 0,
				),
			),
			'required' => array( 'taxonomy', 'name' ),
			'additionalProperties' => false,
		);
		$this->register( 'wordpress/term-create', __( 'Create WordPress Term', 'wp-ability' ), __( 'Creates a term in a registered taxonomy.', 'wp-ability' ), $write, array( $this, 'term_create' ), array( $this, 'can_manage_terms_input' ), false, false, false );
		$update = $write;
		$update['properties']['term_id'] = array(
			'type' => 'integer',
			'minimum' => 1,
		);
		$update['required'] = array( 'taxonomy', 'term_id' );
		$this->register( 'wordpress/term-update', __( 'Update WordPress Term', 'wp-ability' ), __( 'Updates a term in a registered taxonomy.', 'wp-ability' ), $update, array( $this, 'term_update' ), array( $this, 'can_manage_terms_input' ), false, false, true );
		$this->register(
			'wordpress/term-delete',
			__( 'Delete WordPress Term', 'wp-ability' ),
			__( 'Deletes a term from a registered taxonomy.', 'wp-ability' ),
			array(
				'type' => 'object',
				'properties' => array(
					'taxonomy' => array(
						'type' => 'string',
						'minLength' => 1,
					),
					'term_id' => array(
						'type' => 'integer',
						'minimum' => 1,
					),
				),
				'required' => array( 'taxonomy', 'term_id' ),
				'additionalProperties' => false,
			),
			array( $this, 'term_delete' ),
			array( $this, 'can_manage_terms_input' ),
			false,
			true,
			true
		);
	}

	/**
	 * Check whether the current user can perform this ability.
	 *
	 * @param array $input Ability input.
	 * @return bool
	 */
	public function can_manage_terms_input( $input = array() ) {
		$taxonomy = isset( $input['taxonomy'] ) ? sanitize_key( $input['taxonomy'] ) : '';
		$obj      = $taxonomy ? get_taxonomy( $taxonomy ) : false;
		return $obj && current_user_can( $obj->cap->manage_terms );
	}

	/**
	 * Build a normalized term payload.
	 *
	 * @param \WP_Term $term Term object.
	 * @return array
	 */
	private function term_payload( \WP_Term $term ) {
		return array(
			'term_id'     => (int) $term->term_id,
			'taxonomy'    => $term->taxonomy,
			'name'        => $term->name,
			'slug'        => $term->slug,
			'description' => $term->description,
			'parent'      => (int) $term->parent,
			'count'       => (int) $term->count,
		);
	}

	/**
	 * Execute the term list ability.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public function term_list( array $input ) {
		$taxonomy = sanitize_key( $input['taxonomy'] );
		$args = array(
			'taxonomy' => $taxonomy,
			'hide_empty' => ! empty( $input['hide_empty'] ),
		);
		if ( ! empty( $input['search'] ) ) {
			$args['search'] = sanitize_text_field( $input['search'] );
		}
		$terms = get_terms( $args );
		if ( is_wp_error( $terms ) ) {
			return $terms;
		}
		return array( 'terms' => array_map( array( $this, 'term_payload' ), $terms ) );
	}

	/**
	 * Build taxonomy term arguments.
	 *
	 * @param array $input Ability input.
	 * @return array
	 */
	private function term_args( array $input ) {
		$args = array();
		if ( isset( $input['slug'] ) ) {
			$args['slug'] = sanitize_title( $input['slug'] );
		}
		if ( isset( $input['description'] ) ) {
			$args['description'] = sanitize_textarea_field( $input['description'] );
		}
		if ( isset( $input['parent'] ) ) {
			$args['parent'] = (int) $input['parent'];
		}
		return $args;
	}

	/**
	 * Execute the term create ability.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public function term_create( array $input ) {
		$result = wp_insert_term( sanitize_text_field( $input['name'] ), sanitize_key( $input['taxonomy'] ), $this->term_args( $input ) );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$term = get_term( $result['term_id'], sanitize_key( $input['taxonomy'] ) );
		return $term instanceof \WP_Term ? $this->term_payload( $term ) : new \WP_Error( 'wp_ability_term_create_failed', __( 'Term could not be loaded after creation.', 'wp-ability' ) );
	}

	/**
	 * Execute the term update ability.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public function term_update( array $input ) {
		$args = $this->term_args( $input );
		if ( isset( $input['name'] ) ) {
			$args['name'] = sanitize_text_field( $input['name'] );
		}
		$result = wp_update_term( (int) $input['term_id'], sanitize_key( $input['taxonomy'] ), $args );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$term = get_term( (int) $input['term_id'], sanitize_key( $input['taxonomy'] ) );
		return $term instanceof \WP_Term ? $this->term_payload( $term ) : new \WP_Error( 'wp_ability_term_update_failed', __( 'Term could not be loaded after update.', 'wp-ability' ) );
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'wp_abilities_api_init', array( $this, 'register_abilities' ) );
	}

	/**
	 * Register all additional Core abilities.
	 *
	 * @return void
	 */
	public function register_abilities() {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		$this->register_users();
		$this->register_posts();
		$this->register_terms();
		$this->register_media();
		$this->register_options();
		$this->register_cron();
		$this->register_transients();
		$this->register_maintenance();
	}

	/**
	 * Register an ability with shared defaults.
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

	/**
	 * Register post abilities.
	 *
	 * @return void
	 */
	private function register_posts() {
		$this->register(
			'wordpress/post-list',
			__( 'List WordPress Posts', 'wp-ability' ),
			__( 'Lists posts for a requested public or registered post type.', 'wp-ability' ),
			array(
				'type' => 'object',
				'properties' => array(
					'post_type' => array(
						'type' => 'string',
						'default' => 'post',
					),
					'post_status' => array(
						'type' => 'string',
						'default' => 'any',
					),
					'search' => array( 'type' => 'string' ),
					'per_page' => array(
						'type' => 'integer',
						'minimum' => 1,
						'maximum' => 100,
						'default' => 20,
					),
					'page' => array(
						'type' => 'integer',
						'minimum' => 1,
						'default' => 1,
					),
				),
				'additionalProperties' => false,
			),
			array( $this, 'post_list' ),
			array( $this, 'can_edit_posts' ),
			true,
			false,
			true
		);
		$id_schema = array(
			'type' => 'object',
			'properties' => array(
				'post_id' => array(
					'type' => 'integer',
					'minimum' => 1,
				),
			),
			'required' => array( 'post_id' ),
			'additionalProperties' => false,
		);
		$this->register( 'wordpress/post-get', __( 'Get WordPress Post', 'wp-ability' ), __( 'Returns one WordPress post or custom post type item.', 'wp-ability' ), $id_schema, array( $this, 'post_get' ), array( $this, 'can_read_post_input' ), true, false, true );
		$write_schema = array(
			'type' => 'object',
			'properties' => array(
				'post_type' => array(
					'type' => 'string',
					'default' => 'post',
				),
				'post_status' => array(
					'type' => 'string',
					'default' => 'draft',
				),
				'post_title' => array( 'type' => 'string' ),
				'post_content' => array( 'type' => 'string' ),
				'post_excerpt' => array( 'type' => 'string' ),
				'post_author' => array(
					'type' => 'integer',
					'minimum' => 1,
				),
				'post_parent' => array(
					'type' => 'integer',
					'minimum' => 0,
				),
				'menu_order' => array( 'type' => 'integer' ),
			),
			'additionalProperties' => false,
		);
		$this->register( 'wordpress/post-create', __( 'Create WordPress Post', 'wp-ability' ), __( 'Creates a post, page, or registered custom post type item.', 'wp-ability' ), $write_schema, array( $this, 'post_create' ), array( $this, 'can_edit_posts' ), false, false, false );
		$update_schema = $write_schema;
		$update_schema['properties']['post_id'] = array(
			'type' => 'integer',
			'minimum' => 1,
		);
		$update_schema['required'] = array( 'post_id' );
		$this->register( 'wordpress/post-update', __( 'Update WordPress Post', 'wp-ability' ), __( 'Updates editable fields on an existing WordPress post.', 'wp-ability' ), $update_schema, array( $this, 'post_update' ), array( $this, 'can_edit_post_input' ), false, false, true );
		$this->register(
			'wordpress/post-delete',
			__( 'Delete WordPress Post', 'wp-ability' ),
			__( 'Moves a post to Trash or permanently deletes it.', 'wp-ability' ),
			array(
				'type' => 'object',
				'properties' => array(
					'post_id' => array(
						'type' => 'integer',
						'minimum' => 1,
					),
					'force' => array(
						'type' => 'boolean',
						'default' => false,
					),
				),
				'required' => array( 'post_id' ),
				'additionalProperties' => false,
			),
			array( $this, 'post_delete' ),
			array( $this, 'can_delete_post_input' ),
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
	public function can_edit_posts() {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Check whether the current user can perform this ability.
	 *
	 * @param array $input Ability input.
	 * @return bool
	 */
	public function can_read_post_input( $input = array() ) {
		$id = isset( $input['post_id'] ) ? (int) $input['post_id'] : 0;
		return $id > 0 && current_user_can( 'read_post', $id );
	}

	/**
	 * Check whether the current user can perform this ability.
	 *
	 * @param array $input Ability input.
	 * @return bool
	 */
	public function can_edit_post_input( $input = array() ) {
		$id = isset( $input['post_id'] ) ? (int) $input['post_id'] : 0;
		return $id > 0 && current_user_can( 'edit_post', $id );
	}

	/**
	 * Check whether the current user can perform this ability.
	 *
	 * @param array $input Ability input.
	 * @return bool
	 */
	public function can_delete_post_input( $input = array() ) {
		$id = isset( $input['post_id'] ) ? (int) $input['post_id'] : 0;
		return $id > 0 && current_user_can( 'delete_post', $id );
	}

	/**
	 * Build a normalized post payload.
	 *
	 * @param \WP_Post $post Post object.
	 * @return array
	 */
	private function post_payload( \WP_Post $post ) {
		return array(
			'post_id'      => (int) $post->ID,
			'post_type'    => $post->post_type,
			'post_status'  => $post->post_status,
			'post_title'   => $post->post_title,
			'post_content' => $post->post_content,
			'post_excerpt' => $post->post_excerpt,
			'post_author'  => (int) $post->post_author,
			'post_parent'  => (int) $post->post_parent,
			'menu_order'   => (int) $post->menu_order,
			'date_gmt'     => $post->post_date_gmt,
			'modified_gmt' => $post->post_modified_gmt,
		);
	}

	/**
	 * Execute the post list ability.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public function post_list( array $input ) {
		$type = isset( $input['post_type'] ) ? sanitize_key( $input['post_type'] ) : 'post';
		if ( ! post_type_exists( $type ) ) {
			return new \WP_Error( 'wp_ability_post_type_not_found', __( 'Post type not found.', 'wp-ability' ) );
		}
		$args = array(
			'post_type'      => $type,
			'post_status'    => isset( $input['post_status'] ) ? sanitize_key( $input['post_status'] ) : 'any',
			'posts_per_page' => isset( $input['per_page'] ) ? min( 100, (int) $input['per_page'] ) : 20,
			'paged'          => isset( $input['page'] ) ? max( 1, (int) $input['page'] ) : 1,
		);
		if ( ! empty( $input['search'] ) ) {
			$args['s'] = sanitize_text_field( $input['search'] );
		}
		$query = new \WP_Query( $args );
		return array(
			'posts' => array_map( array( $this, 'post_payload' ), $query->posts ),
			'total' => (int) $query->found_posts,
			'pages' => (int) $query->max_num_pages,
		);
	}

	/**
	 * Execute the post get ability.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public function post_get( array $input ) {
		$post = get_post( (int) $input['post_id'] );
		return $post ? $this->post_payload( $post ) : new \WP_Error( 'wp_ability_post_not_found', __( 'Post not found.', 'wp-ability' ) );
	}

	/**
	 * Sanitize post ability input.
	 *
	 * @param array $input Ability input.
	 * @return array
	 */
	private function sanitize_post_input( array $input ) {
		$data = array();
		if ( isset( $input['post_type'] ) ) {
			$data['post_type'] = sanitize_key( $input['post_type'] );
		}
		if ( isset( $input['post_status'] ) ) {
			$data['post_status'] = sanitize_key( $input['post_status'] );
		}
		foreach ( array( 'post_title', 'post_excerpt' ) as $field ) {
			if ( array_key_exists( $field, $input ) ) {
				$data[ $field ] = sanitize_text_field( $input[ $field ] );
			}
		}
		if ( array_key_exists( 'post_content', $input ) ) {
			$data['post_content'] = wp_kses_post( $input['post_content'] );
		}
		foreach ( array( 'post_author', 'post_parent', 'menu_order' ) as $field ) {
			if ( array_key_exists( $field, $input ) ) {
				$data[ $field ] = (int) $input[ $field ];
			}
		}
		return $data;
	}

	/**
	 * Execute the post create ability.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public function post_create( array $input ) {
		$data = $this->sanitize_post_input( $input );
		if ( empty( $data['post_type'] ) ) {
			$data['post_type'] = 'post';
		}
		if ( ! post_type_exists( $data['post_type'] ) ) {
			return new \WP_Error( 'wp_ability_post_type_not_found', __( 'Post type not found.', 'wp-ability' ) );
		}
		$obj = get_post_type_object( $data['post_type'] );
		if ( ! $obj || ! current_user_can( $obj->cap->create_posts ) ) {
			return new \WP_Error( 'wp_ability_cannot_create_post', __( 'Current user cannot create this post type.', 'wp-ability' ) );
		}
		$id = wp_insert_post( $data, true );
		return is_wp_error( $id ) ? $id : $this->post_get( array( 'post_id' => $id ) );
	}

	/**
	 * Execute the post update ability.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public function post_update( array $input ) {
		$data       = $this->sanitize_post_input( $input );
		$data['ID'] = (int) $input['post_id'];
		$id         = wp_update_post( $data, true );
		return is_wp_error( $id ) ? $id : $this->post_get( array( 'post_id' => $id ) );
	}

	/**
	 * Execute the post delete ability.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public function post_delete( array $input ) {
		$id    = (int) $input['post_id'];
		$force = ! empty( $input['force'] );
		$post  = wp_delete_post( $id, $force );
		return $post ? array(
			'post_id' => $id,
			'deleted' => true,
			'force' => $force,
		) : new \WP_Error( 'wp_ability_post_delete_failed', __( 'Post could not be deleted.', 'wp-ability' ) );
	}

	/**
	 * Register taxonomy term abilities.
	 *
	 * @return void
	 */
	private function register_terms() {
		$this->register(
			'wordpress/term-list',
			__( 'List WordPress Terms', 'wp-ability' ),
			__( 'Lists terms from a registered taxonomy.', 'wp-ability' ),
			array(
				'type' => 'object',
				'properties' => array(
					'taxonomy' => array(
						'type' => 'string',
						'minLength' => 1,
					),
					'search' => array( 'type' => 'string' ),
					'hide_empty' => array(
						'type' => 'boolean',
						'default' => false,
					),
				),
				'required' => array( 'taxonomy' ),
				'additionalProperties' => false,
			),
			array( $this, 'term_list' ),
			array( $this, 'can_manage_terms_input' ),
			true,
			false,
			true
		);
		$write = array(
			'type' => 'object',
			'properties' => array(
				'taxonomy' => array(
					'type' => 'string',
					'minLength' => 1,
				),
				'name' => array(
					'type' => 'string',
					'minLength' => 1,
				),
				'slug' => array( 'type' => 'string' ),
				'description' => array( 'type' => 'string' ),
				'parent' => array(
					'type' => 'integer',
					'minimum' => 0,
				),
			),
			'required' => array( 'taxonomy', 'name' ),
			'additionalProperties' => false,
		);
		$this->register( 'wordpress/term-create', __( 'Create WordPress Term', 'wp-ability' ), __( 'Creates a term in a registered taxonomy.', 'wp-ability' ), $write, array( $this, 'term_create' ), array( $this, 'can_manage_terms_input' ), false, false, false );
		$update = $write;
		$update['properties']['term_id'] = array(
			'type' => 'integer',
			'minimum' => 1,
		);
		$update['required'] = array( 'taxonomy', 'term_id' );
		$this->register( 'wordpress/term-update', __( 'Update WordPress Term', 'wp-ability' ), __( 'Updates a term in a registered taxonomy.', 'wp-ability' ), $update, array( $this, 'term_update' ), array( $this, 'can_manage_terms_input' ), false, false, true );
		$this->register(
			'wordpress/term-delete',
			__( 'Delete WordPress Term', 'wp-ability' ),
			__( 'Deletes a term from a registered taxonomy.', 'wp-ability' ),
			array(
				'type' => 'object',
				'properties' => array(
					'taxonomy' => array(
						'type' => 'string',
						'minLength' => 1,
					),
					'term_id' => array(
						'type' => 'integer',
						'minimum' => 1,
					),
				),
				'required' => array( 'taxonomy', 'term_id' ),
				'additionalProperties' => false,
			),
			array( $this, 'term_delete' ),
			array( $this, 'can_manage_terms_input' ),
			false,
			true,
			true
		);
	}

	/**
	 * Check whether the current user can perform this ability.
	 *
	 * @param array $input Ability input.
	 * @return bool
	 */
	public function can_manage_terms_input( $input = array() ) {
		$taxonomy = isset( $input['taxonomy'] ) ? sanitize_key( $input['taxonomy'] ) : '';
		$obj      = $taxonomy ? get_taxonomy( $taxonomy ) : false;
		return $obj && current_user_can( $obj->cap->manage_terms );
	}

	/**
	 * Build a normalized term payload.
	 *
	 * @param \WP_Term $term Term object.
	 * @return array
	 */
	private function term_payload( \WP_Term $term ) {
		return array(
			'term_id'     => (int) $term->term_id,
			'taxonomy'    => $term->taxonomy,
			'name'        => $term->name,
			'slug'        => $term->slug,
			'description' => $term->description,
			'parent'      => (int) $term->parent,
			'count'       => (int) $term->count,
		);
	}

	/**
	 * Execute the term list ability.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public function term_list( array $input ) {
		$taxonomy = sanitize_key( $input['taxonomy'] );
		$args = array(
			'taxonomy' => $taxonomy,
			'hide_empty' => ! empty( $input['hide_empty'] ),
		);
		if ( ! empty( $input['search'] ) ) {
			$args['search'] = sanitize_text_field( $input['search'] );
		}
		$terms = get_terms( $args );
		if ( is_wp_error( $terms ) ) {
			return $terms;
		}
		return array( 'terms' => array_map( array( $this, 'term_payload' ), $terms ) );
	}

	/**
	 * Build taxonomy term arguments.
	 *
	 * @param array $input Ability input.
	 * @return array
	 */
	private function term_args( array $input ) {
		$args = array();
		if ( isset( $input['slug'] ) ) {
			$args['slug'] = sanitize_title( $input['slug'] );
		}
		if ( isset( $input['description'] ) ) {
			$args['description'] = sanitize_textarea_field( $input['description'] );
		}
		if ( isset( $input['parent'] ) ) {
			$args['parent'] = (int) $input['parent'];
		}
		return $args;
	}

	/**
	 * Execute the term create ability.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public function term_create( array $input ) {
		$result = wp_insert_term( sanitize_text_field( $input['name'] ), sanitize_key( $input['taxonomy'] ), $this->term_args( $input ) );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$term = get_term( $result['term_id'], sanitize_key( $input['taxonomy'] ) );
		return $term instanceof \WP_Term ? $this->term_payload( $term ) : new \WP_Error( 'wp_ability_term_create_failed', __( 'Term could not be loaded after creation.', 'wp-ability' ) );
	}

	/**
	 * Execute the term update ability.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public function term_update( array $input ) {
		$args = $this->term_args( $input );
		if ( isset( $input['name'] ) ) {
			$args['name'] = sanitize_text_field( $input['name'] );
		}
		$result = wp_update_term( (int) $input['term_id'], sanitize_key( $input['taxonomy'] ), $args );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$term = get_term( (int) $input['term_id'], sanitize_key( $input['taxonomy'] ) );
		return $term instanceof \WP_Term ? $this->term_payload( $term ) : new \WP_Error( 'wp_ability_term_update_failed', __( 'Term could not be loaded after update.', 'wp-ability' ) );
	}

	/**
	 * Execute the term delete ability.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public function term_delete( array $input ) {
		$result = wp_delete_term( (int) $input['term_id'], sanitize_key( $input['taxonomy'] ) );
		return is_wp_error( $result ) ? $result : array(
			'term_id' => (int) $input['term_id'],
			'deleted' => (bool) $result,
		);
	}
}
