<?php
/**
 * Media and comment ability behavior tests.
 *
 * @package WordPress_Abilities_Bridge
 */

use WP_Ability\Comment_Abilities;
use WP_Ability\Media_Abilities;

/**
 * Tests focused media and comment domains.
 */
class MediaCommentAbilitiesTest extends WP_UnitTestCase {

	/**
	 * Media update edits Core attachment fields and alt text.
	 *
	 * @return void
	 */
	public function test_media_update_edits_attachment_metadata(): void {
		$administrator = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $administrator );

		$attachment_id = wp_insert_attachment(
			array(
				'post_title'     => 'Before',
				'post_mime_type' => 'image/jpeg',
				'post_status'    => 'inherit',
			)
		);

		$media  = new Media_Abilities();
		$result = $media->media_update(
			array(
				'attachment_id' => $attachment_id,
				'title'         => 'After',
				'caption'       => 'Caption',
				'description'   => 'Description',
				'alt_text'      => 'Alternative text',
			)
		);

		$this->assertIsArray( $result );
		$this->assertSame( 'After', get_post( $attachment_id )->post_title );
		$this->assertSame( 'Caption', get_post( $attachment_id )->post_excerpt );
		$this->assertSame( 'Description', get_post( $attachment_id )->post_content );
		$this->assertSame( 'Alternative text', get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) );
	}

	/**
	 * Media upload rejects non-HTTPS URLs before making a download.
	 *
	 * @return void
	 */
	public function test_media_upload_rejects_non_https_url(): void {
		$media  = new Media_Abilities();
		$result = $media->media_upload(
			array(
				'source_url' => 'http://example.com/image.jpg',
			)
		);

		$this->assertWPError( $result );
		$this->assertSame( 'wp_ability_invalid_media_url', $result->get_error_code() );
	}

	/**
	 * Media input permission follows Core capabilities.
	 *
	 * @return void
	 */
	public function test_media_permissions_follow_core_capabilities(): void {
		$media      = new Media_Abilities();
		$subscriber = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber );
		$this->assertFalse( $media->can_upload_media_input() );

		$administrator = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $administrator );
		$this->assertTrue( $media->can_upload_media_input() );
	}

	/**
	 * Comment create, update, status, list, and delete use Core state.
	 *
	 * @return void
	 */
	public function test_comment_management_flow_uses_core_comment_state(): void {
		$administrator = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $administrator );
		$post_id = self::factory()->post->create();

		$comments = new Comment_Abilities();
		$created  = $comments->comment_create(
			array(
				'post_id' => $post_id,
				'content' => 'Initial comment',
			)
		);

		$this->assertIsArray( $created );
		$comment_id = $created['comment_id'];
		$this->assertSame( 'Initial comment', get_comment( $comment_id )->comment_content );

		$updated = $comments->comment_update(
			array(
				'comment_id' => $comment_id,
				'content'    => 'Updated comment',
			)
		);
		$this->assertIsArray( $updated );
		$this->assertSame( 'Updated comment', get_comment( $comment_id )->comment_content );

		$spam = $comments->comment_spam( array( 'comment_id' => $comment_id ) );
		$this->assertIsArray( $spam );
		$this->assertSame( 'spam', wp_get_comment_status( $comment_id ) );

		$approved = $comments->comment_approve( array( 'comment_id' => $comment_id ) );
		$this->assertIsArray( $approved );
		$this->assertSame( 'approved', wp_get_comment_status( $comment_id ) );

		$list = $comments->comment_list(
			array(
				'post_id' => $post_id,
				'status'  => 'all',
			)
		);
		$this->assertNotEmpty( $list['comments'] );

		$deleted = $comments->comment_delete(
			array(
				'comment_id' => $comment_id,
				'force'      => true,
			)
		);
		$this->assertTrue( $deleted['deleted'] );
		$this->assertNull( get_comment( $comment_id ) );
	}

	/**
	 * Comment moderation permissions are denied to subscribers.
	 *
	 * @return void
	 */
	public function test_comment_moderation_requires_core_capability(): void {
		$comments   = new Comment_Abilities();
		$subscriber = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber );
		$this->assertFalse( $comments->can_moderate_comments() );

		$administrator = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $administrator );
		$this->assertTrue( $comments->can_moderate_comments() );
	}
}
