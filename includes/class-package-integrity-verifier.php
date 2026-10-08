<?php
/**
 * Package integrity verification.
 *
 * @package WordPress_Abilities_Bridge
 */

namespace WP_Ability;

defined( 'ABSPATH' ) || exit;

/**
 * Verifies SHA-256 before Core upgraders unpack remote packages.
 */
final class Package_Integrity_Verifier {

	/**
	 * Expected digest.
	 *
	 * @var string
	 */
	private $expected_sha256;

	/**
	 * Optional downloader for tests.
	 *
	 * @var callable|null
	 */
	private $downloader;

	/**
	 * Constructor.
	 *
	 * @param string        $expected_sha256 Expected SHA-256.
	 * @param callable|null $downloader Optional downloader.
	 */
	public function __construct( $expected_sha256, $downloader = null ) {
		$this->expected_sha256 = strtolower( trim( (string) $expected_sha256 ) );
		$this->downloader      = $downloader;
	}

	/**
	 * Verify an upgrader package download.
	 *
	 * @param mixed  $reply Existing pre-download result.
	 * @param string $package Package URL.
	 * @param mixed  $upgrader Upgrader.
	 * @param array  $hook_extra Hook context.
	 * @return mixed
	 */
	public function verify( $reply, $package, $upgrader, $hook_extra ) {
		unset( $upgrader, $hook_extra );

		if ( false !== $reply ) {
			return $reply;
		}

		if ( 1 !== preg_match( '/^[a-f0-9]{64}$/', $this->expected_sha256 ) ) {
			return new \WP_Error(
				'wp_ability_invalid_package_checksum',
				__( 'Expected SHA-256 must contain exactly 64 hexadecimal characters.', 'wp-ability' )
			);
		}

		$temp_file = is_callable( $this->downloader )
			? call_user_func( $this->downloader, $package )
			: download_url( $package );

		if ( is_wp_error( $temp_file ) ) {
			return $temp_file;
		}

		$actual = hash_file( 'sha256', $temp_file );
		if ( ! is_string( $actual ) || ! hash_equals( $this->expected_sha256, strtolower( $actual ) ) ) {
			wp_delete_file( $temp_file );
			return new \WP_Error(
				'wp_ability_package_checksum_mismatch',
				__( 'Downloaded package SHA-256 does not match the expected digest.', 'wp-ability' )
			);
		}

		return $temp_file;
	}
}
