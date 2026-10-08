<?php
/**
 * Content Abilities.
 *
 * @package WordPress_Abilities_Bridge
 */

namespace WP_Ability;

defined( 'ABSPATH' ) || exit;

/**
 * Focused WordPress ability domain.
 */
final class Content_Abilities extends Domain_Abilities_Base {

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

		$this->register_posts();
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
}
