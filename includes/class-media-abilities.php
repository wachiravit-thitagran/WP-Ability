<?php
/**
 * Media Abilities.
 *
 * @package WordPress_Abilities_Bridge
 */

namespace WP_Ability;

defined( 'ABSPATH' ) || exit;

/**
 * Focused WordPress ability domain.
 */
final class Media_Abilities extends Domain_Abilities_Base {

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

		$this->register_media();
		$this->register_media_upload();
		$this->register_media_update();
		$this->register_media_delete();
	}

	/**
	 * Register media abilities.
	 *
	 * @return void
	 */
	private function register_media() {
		$this->register(
			'wordpress/media-list',
			__( 'List WordPress Media', 'wp-ability' ),
			__( 'Lists media attachments.', 'wp-ability' ),
			array(
				'type' => 'object',
				'properties' => array(
					'mime_type' => array( 'type' => 'string' ),
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
			array( $this, 'media_list' ),
			array( $this, 'can_upload_files' ),
			true,
			false,
			true
		);
		$this->register(
			'wordpress/media-get',
			__( 'Get WordPress Media', 'wp-ability' ),
			__( 'Returns metadata for one media attachment.', 'wp-ability' ),
			array(
				'type' => 'object',
				'properties' => array(
					'attachment_id' => array(
						'type' => 'integer',
						'minimum' => 1,
					),
				),
				'required' => array( 'attachment_id' ),
				'additionalProperties' => false,
			),
			array( $this, 'media_get' ),
			array( $this, 'can_upload_files' ),
			true,
			false,
			true
		);
	}

	/**
	 * Check whether the current user can perform this ability.
	 *
	 * @return bool
	 */
	public function can_upload_files() {
		return current_user_can( 'upload_files' );
	}

	/**
	 * Build a normalized media payload.
	 *
	 * @param \WP_Post $post Attachment post object.
	 * @return array
	 */
	private function media_payload( \WP_Post $post ) {
		return array(
			'attachment_id' => (int) $post->ID,
			'title'         => $post->post_title,
			'caption'       => $post->post_excerpt,
			'description'   => $post->post_content,
			'mime_type'     => $post->post_mime_type,
			'url'           => wp_get_attachment_url( $post->ID ),
			'alt_text'      => get_post_meta( $post->ID, '_wp_attachment_image_alt', true ),
		);
	}

	/**
	 * Execute the media list ability.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public function media_list( array $input ) {
		$args = array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => isset( $input['per_page'] ) ? min( 100, (int) $input['per_page'] ) : 20,
			'paged'          => isset( $input['page'] ) ? max( 1, (int) $input['page'] ) : 1,
		);
		if ( ! empty( $input['mime_type'] ) ) {
			$args['post_mime_type'] = sanitize_mime_type( $input['mime_type'] );
		}
		if ( ! empty( $input['search'] ) ) {
			$args['s'] = sanitize_text_field( $input['search'] );
		}
		$query = new \WP_Query( $args );
		return array(
			'media' => array_map( array( $this, 'media_payload' ), $query->posts ),
			'total' => (int) $query->found_posts,
		);
	}

	/**
	 * Execute the media get ability.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public function media_get( array $input ) {
		$post = get_post( (int) $input['attachment_id'] );
		if ( ! $post || 'attachment' !== $post->post_type ) {
			return new \WP_Error( 'wp_ability_attachment_not_found', __( 'Attachment not found.', 'wp-ability' ) );
		}
		return $this->media_payload( $post );
	}

	/**
	 * Register media upload.
	 *
	 * @return void
	 */
	private function register_media_upload() {
		Ability_Registrar::register(
			'wordpress/media-upload',
			array(
				'label'               => __( 'Upload WordPress Media', 'wp-ability' ),
				'description'         => __( 'Downloads one validated HTTPS media file and creates a WordPress attachment with optional attachment metadata.', 'wp-ability' ),
				'category'            => 'wordpress-admin',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'source_url'  => array(
							'type'      => 'string',
							'format'    => 'uri',
							'minLength' => 1,
						),
						'post_id'     => array(
							'type'    => 'integer',
							'minimum' => 0,
							'default' => 0,
						),
						'title'       => array( 'type' => 'string' ),
						'caption'     => array( 'type' => 'string' ),
						'description' => array( 'type' => 'string' ),
						'alt_text'    => array( 'type' => 'string' ),
					),
					'required'             => array( 'source_url' ),
					'additionalProperties' => false,
				),
				'execute_callback'    => array( $this, 'media_upload' ),
				'permission_callback' => array( $this, 'can_upload_media_input' ),
				'meta'                => $this->meta( false, false, false, true ),
			)
		);
	}

	/**
	 * Register media update.
	 *
	 * @return void
	 */
	private function register_media_update() {
		Ability_Registrar::register(
			'wordpress/media-update',
			array(
				'label'               => __( 'Update WordPress Media', 'wp-ability' ),
				'description'         => __( 'Updates editable attachment fields and image alternative text for an existing WordPress media item.', 'wp-ability' ),
				'category'            => 'wordpress-admin',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'attachment_id' => array(
							'type'    => 'integer',
							'minimum' => 1,
						),
						'title'         => array( 'type' => 'string' ),
						'caption'       => array( 'type' => 'string' ),
						'description'   => array( 'type' => 'string' ),
						'alt_text'      => array( 'type' => 'string' ),
					),
					'required'             => array( 'attachment_id' ),
					'additionalProperties' => false,
				),
				'execute_callback'    => array( $this, 'media_update' ),
				'permission_callback' => array( $this, 'can_edit_media_input' ),
				'meta'                => $this->meta( false, false, true, false ),
			)
		);
	}

	/**
	 * Check media upload permission and optional parent access.
	 *
	 * @param array $input Ability input.
	 * @return bool
	 */
	public function can_upload_media_input( $input = array() ) {
		if ( ! current_user_can( 'upload_files' ) ) {
			return false;
		}

		$post_id = isset( $input['post_id'] ) ? (int) $input['post_id'] : 0;
		return 0 === $post_id || current_user_can( 'edit_post', $post_id );
	}

	/**
	 * Check media edit permission.
	 *
	 * @param array $input Ability input.
	 * @return bool
	 */
	public function can_edit_media_input( $input = array() ) {
		$attachment_id = isset( $input['attachment_id'] ) ? (int) $input['attachment_id'] : 0;
		return $attachment_id > 0 && current_user_can( 'edit_post', $attachment_id );
	}

	/**
	 * Upload media from a validated HTTPS URL using WordPress Core.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public function media_upload( array $input ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$source_url = trim( (string) $input['source_url'] );
		$parts      = wp_parse_url( $source_url );
		if ( ! is_array( $parts ) || empty( $parts['scheme'] ) || 'https' !== strtolower( $parts['scheme'] ) || false === wp_http_validate_url( $source_url ) ) {
			return new \WP_Error( 'wp_ability_invalid_media_url', __( 'A valid HTTPS media URL is required.', 'wp-ability' ) );
		}

		$temp_file = download_url( $source_url );
		if ( is_wp_error( $temp_file ) ) {
			return $temp_file;
		}

		$path = wp_parse_url( $source_url, PHP_URL_PATH );
		$name = is_string( $path ) ? wp_basename( $path ) : '';
		if ( '' === $name ) {
			$name = 'remote-media';
		}

		$file_array = array(
			'name'     => sanitize_file_name( $name ),
			'tmp_name' => $temp_file,
		);
		$post_data  = array();
		foreach (
			array(
				'title'       => 'post_title',
				'caption'     => 'post_excerpt',
				'description' => 'post_content',
			) as $input_key => $post_key
		) {
			if ( array_key_exists( $input_key, $input ) ) {
				$post_data[ $post_key ] = sanitize_textarea_field( $input[ $input_key ] );
			}
		}

		$attachment_id = media_handle_sideload( $file_array, isset( $input['post_id'] ) ? (int) $input['post_id'] : 0, null, $post_data );
		if ( is_wp_error( $attachment_id ) ) {
			@unlink( $temp_file );
			return $attachment_id;
		}

		if ( array_key_exists( 'alt_text', $input ) ) {
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( $input['alt_text'] ) );
		}

		return $this->media_get( array( 'attachment_id' => $attachment_id ) );
	}

	/**
	 * Update attachment metadata using WordPress Core.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public function media_update( array $input ) {
		$attachment_id = (int) $input['attachment_id'];
		$post          = get_post( $attachment_id );
		if ( ! $post || 'attachment' !== $post->post_type ) {
			return new \WP_Error( 'wp_ability_attachment_not_found', __( 'The requested media attachment was not found.', 'wp-ability' ) );
		}

		$update = array( 'ID' => $attachment_id );
		foreach (
			array(
				'title'       => 'post_title',
				'caption'     => 'post_excerpt',
				'description' => 'post_content',
			) as $input_key => $post_key
		) {
			if ( array_key_exists( $input_key, $input ) ) {
				$update[ $post_key ] = sanitize_textarea_field( $input[ $input_key ] );
			}
		}

		if ( count( $update ) > 1 ) {
			$result = wp_update_post( wp_slash( $update ), true );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		if ( array_key_exists( 'alt_text', $input ) ) {
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( $input['alt_text'] ) );
		}

		return $this->media_get( array( 'attachment_id' => $attachment_id ) );
	}

	/**
	 * Register media deletion ability.
	 *
	 * @return void
	 */
	private function register_media_delete() {
		Ability_Registrar::register(
			'wordpress/media-delete',
			array(
				'label'               => __( 'Delete WordPress Media', 'wp-ability' ),
				'description'         => __( 'Deletes a media attachment, optionally bypassing the Trash and permanently removing its files.', 'wp-ability' ),
				'category'            => 'wordpress-admin',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'attachment_id' => array(
							'type'    => 'integer',
							'minimum' => 1,
						),
						'force'         => array(
							'type'    => 'boolean',
							'default' => false,
						),
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
}
