<?php
/**
 * WordPress comment management abilities.
 *
 * @package WordPress_Abilities_Bridge
 */

namespace WP_Ability;

defined( 'ABSPATH' ) || exit;

/**
 * Core-backed WordPress comment management.
 */
final class Comment_Abilities extends Domain_Abilities_Base {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'wp_abilities_api_init', array( $this, 'register_abilities' ) );
	}

	/**
	 * Register comment abilities.
	 *
	 * @return void
	 */
	public function register_abilities() {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		$list_schema = array(
			'type'                 => 'object',
			'properties'           => array(
				'post_id' => array(
					'type'    => 'integer',
					'minimum' => 1,
				),
				'status'  => array(
					'type'    => 'string',
					'default' => 'all',
				),
				'number'  => array(
					'type'    => 'integer',
					'minimum' => 1,
					'maximum' => 100,
					'default' => 20,
				),
				'offset'  => array(
					'type'    => 'integer',
					'minimum' => 0,
					'default' => 0,
				),
			),
			'additionalProperties' => false,
		);

		$id_schema = array(
			'type'                 => 'object',
			'properties'           => array(
				'comment_id' => array(
					'type'    => 'integer',
					'minimum' => 1,
				),
			),
			'required'             => array( 'comment_id' ),
			'additionalProperties' => false,
		);

		$this->register( 'wordpress/comment-list', __( 'List WordPress Comments', 'wp-ability' ), __( 'Lists WordPress comments visible to a moderator using WP_Comment_Query.', 'wp-ability' ), $list_schema, array( $this, 'comment_list' ), array( $this, 'can_moderate_comments' ), true, false, true );
		$this->register( 'wordpress/comment-get', __( 'Get WordPress Comment', 'wp-ability' ), __( 'Returns one WordPress comment by ID.', 'wp-ability' ), $id_schema, array( $this, 'comment_get' ), array( $this, 'can_moderate_comments' ), true, false, true );

		$this->register(
			'wordpress/comment-create',
			__( 'Create WordPress Comment', 'wp-ability' ),
			__( 'Creates a comment on an editable post as the current WordPress user.', 'wp-ability' ),
			array(
				'type'                 => 'object',
				'properties'           => array(
					'post_id'   => array(
						'type'    => 'integer',
						'minimum' => 1,
					),
					'content'   => array(
						'type'      => 'string',
						'minLength' => 1,
					),
					'parent_id' => array(
						'type'    => 'integer',
						'minimum' => 0,
						'default' => 0,
					),
				),
				'required'             => array( 'post_id', 'content' ),
				'additionalProperties' => false,
			),
			array( $this, 'comment_create' ),
			array( $this, 'can_create_comment_input' ),
			false,
			false,
			false
		);

		$update_schema = $id_schema;
		$update_schema['properties']['content'] = array(
			'type'      => 'string',
			'minLength' => 1,
		);
		$update_schema['required'][] = 'content';
		$this->register( 'wordpress/comment-update', __( 'Update WordPress Comment', 'wp-ability' ), __( 'Updates the content of an editable WordPress comment.', 'wp-ability' ), $update_schema, array( $this, 'comment_update' ), array( $this, 'can_edit_comment_input' ), false, false, true );

		$delete_schema = $id_schema;
		$delete_schema['properties']['force'] = array(
			'type'    => 'boolean',
			'default' => false,
		);
		$this->register( 'wordpress/comment-delete', __( 'Delete WordPress Comment', 'wp-ability' ), __( 'Trashes or permanently deletes a WordPress comment using Core comment deletion.', 'wp-ability' ), $delete_schema, array( $this, 'comment_delete' ), array( $this, 'can_moderate_comments' ), false, true, true );
		$this->register( 'wordpress/comment-approve', __( 'Approve WordPress Comment', 'wp-ability' ), __( 'Marks a WordPress comment as approved.', 'wp-ability' ), $id_schema, array( $this, 'comment_approve' ), array( $this, 'can_moderate_comments' ), false, false, true );
		$this->register( 'wordpress/comment-spam', __( 'Mark WordPress Comment as Spam', 'wp-ability' ), __( 'Marks a WordPress comment as spam.', 'wp-ability' ), $id_schema, array( $this, 'comment_spam' ), array( $this, 'can_moderate_comments' ), false, true, true );
		$this->register( 'wordpress/comment-trash', __( 'Trash WordPress Comment', 'wp-ability' ), __( 'Moves a WordPress comment to Trash.', 'wp-ability' ), $id_schema, array( $this, 'comment_trash' ), array( $this, 'can_moderate_comments' ), false, true, true );
	}

	/**
	 * Check comment moderation permission.
	 *
	 * @return bool
	 */
	public function can_moderate_comments() {
		return current_user_can( 'moderate_comments' );
	}

	/**
	 * Check comment creation permission for the target post.
	 *
	 * @param array $input Ability input.
	 * @return bool
	 */
	public function can_create_comment_input( $input = array() ) {
		$post_id = isset( $input['post_id'] ) ? (int) $input['post_id'] : 0;
		return $post_id > 0 && current_user_can( 'edit_post', $post_id );
	}

	/**
	 * Check comment edit permission.
	 *
	 * @param array $input Ability input.
	 * @return bool
	 */
	public function can_edit_comment_input( $input = array() ) {
		$comment_id = isset( $input['comment_id'] ) ? (int) $input['comment_id'] : 0;
		return $comment_id > 0 && current_user_can( 'edit_comment', $comment_id );
	}

	/**
	 * List comments.
	 *
	 * @param array $input Ability input.
	 * @return array
	 */
	public function comment_list( array $input ) {
		$args = array(
			'number' => isset( $input['number'] ) ? min( 100, (int) $input['number'] ) : 20,
			'offset' => isset( $input['offset'] ) ? max( 0, (int) $input['offset'] ) : 0,
			'status' => isset( $input['status'] ) ? sanitize_key( $input['status'] ) : 'all',
		);
		if ( ! empty( $input['post_id'] ) ) {
			$args['post_id'] = (int) $input['post_id'];
		}

		$query = new \WP_Comment_Query();
		$items = $query->query( $args );

		return array(
			'comments' => array_map( array( $this, 'comment_payload' ), $items ),
			'total'    => count( $items ),
		);
	}

	/**
	 * Get one comment.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public function comment_get( array $input ) {
		$comment = get_comment( (int) $input['comment_id'] );
		return $comment instanceof \WP_Comment
			? $this->comment_payload( $comment )
			: new \WP_Error( 'wp_ability_comment_not_found', __( 'Comment not found.', 'wp-ability' ) );
	}

	/**
	 * Create a comment as the current user.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public function comment_create( array $input ) {
		$user = wp_get_current_user();
		$id   = wp_insert_comment(
			array(
				'comment_post_ID'      => (int) $input['post_id'],
				'comment_content'      => wp_kses_post( $input['content'] ),
				'comment_parent'       => isset( $input['parent_id'] ) ? (int) $input['parent_id'] : 0,
				'user_id'              => (int) $user->ID,
				'comment_author'       => $user->display_name,
				'comment_author_email' => $user->user_email,
				'comment_approved'     => 1,
			)
		);

		if ( ! $id ) {
			return new \WP_Error( 'wp_ability_comment_create_failed', __( 'Comment creation failed.', 'wp-ability' ) );
		}

		return $this->comment_get( array( 'comment_id' => $id ) );
	}

	/**
	 * Update comment content.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public function comment_update( array $input ) {
		$result = wp_update_comment(
			array(
				'comment_ID'      => (int) $input['comment_id'],
				'comment_content' => wp_kses_post( $input['content'] ),
			),
			true
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( false === $result ) {
			return new \WP_Error( 'wp_ability_comment_update_failed', __( 'Comment update failed.', 'wp-ability' ) );
		}

		return $this->comment_get( array( 'comment_id' => (int) $input['comment_id'] ) );
	}

	/**
	 * Delete or trash a comment.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public function comment_delete( array $input ) {
		$id     = (int) $input['comment_id'];
		$result = wp_delete_comment( $id, ! empty( $input['force'] ) );
		if ( ! $result ) {
			return new \WP_Error( 'wp_ability_comment_delete_failed', __( 'Comment deletion failed.', 'wp-ability' ) );
		}
		return array(
			'comment_id' => $id,
			'deleted'    => true,
			'force'      => ! empty( $input['force'] ),
		);
	}

	/**
	 * Approve a comment.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public function comment_approve( array $input ) {
		return $this->set_comment_status( (int) $input['comment_id'], 'approve' );
	}

	/**
	 * Mark a comment as spam.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public function comment_spam( array $input ) {
		return $this->set_comment_status( (int) $input['comment_id'], 'spam' );
	}

	/**
	 * Trash a comment.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public function comment_trash( array $input ) {
		return $this->set_comment_status( (int) $input['comment_id'], 'trash' );
	}

	/**
	 * Set comment status using Core.
	 *
	 * @param int    $comment_id Comment ID.
	 * @param string $status Status action.
	 * @return array|\WP_Error
	 */
	private function set_comment_status( $comment_id, $status ) {
		$comment = get_comment( $comment_id );
		if ( ! $comment ) {
			return new \WP_Error( 'wp_ability_comment_not_found', __( 'Comment not found.', 'wp-ability' ) );
		}

		$result = wp_set_comment_status( $comment_id, $status, true );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( ! $result ) {
			return new \WP_Error( 'wp_ability_comment_status_failed', __( 'Comment status update failed.', 'wp-ability' ) );
		}

		return $this->comment_get( array( 'comment_id' => $comment_id ) );
	}

	/**
	 * Normalize comment output.
	 *
	 * @param \WP_Comment $comment Comment.
	 * @return array
	 */
	private function comment_payload( \WP_Comment $comment ) {
		return array(
			'comment_id' => (int) $comment->comment_ID,
			'post_id'    => (int) $comment->comment_post_ID,
			'parent_id'  => (int) $comment->comment_parent,
			'user_id'    => (int) $comment->user_id,
			'author'     => $comment->comment_author,
			'content'    => $comment->comment_content,
			'status'     => wp_get_comment_status( $comment ),
			'date'       => mysql_to_rfc3339( $comment->comment_date_gmt ),
		);
	}
}
