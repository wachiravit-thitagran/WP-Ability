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
