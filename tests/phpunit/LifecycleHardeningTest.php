<?php
/**
 * Update, integrity, compatibility, and audit hardening tests.
 *
 * @package WordPress_Abilities_Bridge
 */

use WP_Ability\Audit_Context;
use WP_Ability\GitHub_Self_Updater;
use WP_Ability\Package_Integrity_Verifier;

/**
 * Tests release lifecycle hardening.
 */
class LifecycleHardeningTest extends WP_UnitTestCase {

	/**
	 * Package abilities accept an optional SHA-256 digest.
	 *
	 * @return void
	 */
	public function test_package_abilities_accept_expected_sha256(): void {
		foreach ( array( 'wordpress/plugin-install-package', 'wordpress/theme-install-package' ) as $name ) {
			$schema = wp_get_ability( $name )->get_input_schema();

			$this->assertArrayHasKey( 'expected_sha256', $schema['properties'], $name );
			$this->assertSame( 64, $schema['properties']['expected_sha256']['minLength'] );
			$this->assertSame( 64, $schema['properties']['expected_sha256']['maxLength'] );
		}
	}

	/**
	 * Package integrity rejects a mismatched digest.
	 *
	 * @return void
	 */
	public function test_package_integrity_rejects_sha256_mismatch(): void {
		$temp = wp_tempnam( 'wp-ability-integrity' );
		file_put_contents( $temp, 'verified package bytes' );

		$verifier = new Package_Integrity_Verifier(
			str_repeat( '0', 64 ),
			static function () use ( $temp ) {
				return $temp;
			}
		);

		$result = $verifier->verify( false, 'https://example.com/package.zip', null, array() );

		$this->assertWPError( $result );
		$this->assertSame( 'wp_ability_package_checksum_mismatch', $result->get_error_code() );
	}

	/**
	 * Package integrity returns a verified temporary file.
	 *
	 * @return void
	 */
	public function test_package_integrity_accepts_matching_sha256(): void {
		$temp = wp_tempnam( 'wp-ability-integrity' );
		file_put_contents( $temp, 'verified package bytes' );
		$expected = hash_file( 'sha256', $temp );

		$verifier = new Package_Integrity_Verifier(
			$expected,
			static function () use ( $temp ) {
				return $temp;
			}
		);

		$this->assertSame(
			$temp,
			$verifier->verify( false, 'https://example.com/package.zip', null, array() )
		);

		wp_delete_file( $temp );
	}

	/**
	 * GitHub self-update is disabled by default so blocked hosts make no request.
	 *
	 * @return void
	 */
	public function test_self_updater_is_opt_in_and_skips_outbound_by_default(): void {
		$calls   = 0;
		$updater = new GitHub_Self_Updater(
			static function () use ( &$calls ) {
				++$calls;
				return new WP_Error( 'should_not_run', 'HTTP should not run.' );
			}
		);

		$transient = (object) array(
			'checked'  => array( 'wp-ability/wp-ability.php' => '0.4.0' ),
			'response' => array(),
		);
		$result = $updater->inject_update( $transient );

		$this->assertSame( 0, $calls );
		$this->assertSame( $transient, $result );
	}

	/**
	 * Enabled GitHub self-update injects a newer release through Core update metadata.
	 *
	 * @return void
	 */
	public function test_self_updater_injects_newer_release_when_enabled(): void {
		add_filter( 'wp_ability_github_updates_enabled', '__return_true' );

		$updater = new GitHub_Self_Updater(
			static function () {
				return array(
					'response' => array( 'code' => 200 ),
					'body'     => wp_json_encode(
						array(
							'tag_name' => 'v9.9.9',
							'html_url' => 'https://github.com/example/release',
							'assets'   => array(
								array(
									'name'               => 'wp-ability-9.9.9.zip',
									'browser_download_url' => 'https://github.com/example/wp-ability-9.9.9.zip',
									'digest'             => 'sha256:' . str_repeat( 'a', 64 ),
								),
							),
						)
					),
				);
			}
		);

		$transient = (object) array(
			'checked'  => array( 'wp-ability/wp-ability.php' => '0.4.0' ),
			'response' => array(),
		);
		$result = $updater->inject_update( $transient );

		remove_filter( 'wp_ability_github_updates_enabled', '__return_true' );

		$this->assertArrayHasKey( 'wp-ability/wp-ability.php', $result->response );
		$this->assertSame( '9.9.9', $result->response['wp-ability/wp-ability.php']->new_version );
		$this->assertSame( str_repeat( 'a', 64 ), $result->response['wp-ability/wp-ability.php']->wp_ability_sha256 );
	}

	/**
	 * Failed outbound self-update checks leave the Core transient untouched.
	 *
	 * @return void
	 */
	public function test_self_updater_fails_open_when_github_is_unreachable(): void {
		add_filter( 'wp_ability_github_updates_enabled', '__return_true' );

		$updater = new GitHub_Self_Updater(
			static function () {
				return new WP_Error( 'http_request_failed', 'Outbound blocked.' );
			}
		);

		$transient = (object) array(
			'checked'  => array( 'wp-ability/wp-ability.php' => '0.4.0' ),
			'response' => array(),
		);
		$result = $updater->inject_update( $transient );

		remove_filter( 'wp_ability_github_updates_enabled', '__return_true' );

		$this->assertSame( $transient, $result );
	}

	/**
	 * Known bridge abilities expose their official WordPress Core equivalents.
	 *
	 * @return void
	 */
	public function test_wordpress_71_core_equivalent_metadata_is_exposed(): void {
		$this->assertSame(
			'core/read-users',
			wp_get_ability( 'wordpress/user-list' )->get_meta_item( 'coreEquivalent' )
		);
		$this->assertSame(
			'core/read-content',
			wp_get_ability( 'wordpress/post-list' )->get_meta_item( 'coreEquivalent' )
		);
		$this->assertSame(
			'core/read-settings',
			wp_get_ability( 'wordpress/option-get' )->get_meta_item( 'coreEquivalent' )
		);
	}

	/**
	 * Audit context republishes native lifecycle hooks without persisting logs.
	 *
	 * @return void
	 */
	public function test_audit_context_emits_normalized_before_and_after_events(): void {
		$audit  = new Audit_Context();
		$events = array();

		$listener = static function ( $event ) use ( &$events ) {
			$events[] = $event;
		};
		add_action( 'wp_ability_audit_event', $listener );

		$ability = wp_get_ability( 'wordpress/user-list' );
		$audit->capture_before( 'wordpress/user-list', array(), $ability );
		$audit->capture_after( 'wordpress/user-list', array(), array( 'users' => array() ), $ability );

		remove_action( 'wp_ability_audit_event', $listener );

		$this->assertCount( 2, $events );
		$this->assertSame( 'before', $events[0]['phase'] );
		$this->assertSame( 'after', $events[1]['phase'] );
		$this->assertSame( 'wordpress/user-list', $events[0]['ability'] );
		$this->assertArrayHasKey( 'user_id', $events[0] );
		$this->assertArrayHasKey( 'result_type', $events[1] );
	}
}
