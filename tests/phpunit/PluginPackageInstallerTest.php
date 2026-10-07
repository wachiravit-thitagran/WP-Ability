<?php
/**
 * Plugin package installer tests.
 *
 * @package WP_Ability
 */

use WP_Ability\Plugin_Package_Installer;

/**
 * Test package source validation.
 */
class PluginPackageInstallerTest extends WP_UnitTestCase {

	/**
	 * Empty package URLs are rejected.
	 *
	 * @return void
	 */
	public function test_rejects_empty_package_url(): void {
		$installer = new Plugin_Package_Installer();
		$result    = $installer->validate_package_url( '' );

		$this->assertWPError( $result );
		$this->assertSame( 'wp_ability_invalid_plugin_package_url', $result->get_error_code() );
	}

	/**
	 * Plain HTTP package URLs are rejected.
	 *
	 * @return void
	 */
	public function test_rejects_http_package_url(): void {
		$installer = new Plugin_Package_Installer();
		$result    = $installer->validate_package_url( 'http://example.com/plugin.zip' );

		$this->assertWPError( $result );
		$this->assertSame( 'wp_ability_invalid_plugin_package_url', $result->get_error_code() );
	}

	/**
	 * Local file paths are rejected.
	 *
	 * @return void
	 */
	public function test_rejects_local_file_path(): void {
		$installer = new Plugin_Package_Installer();
		$result    = $installer->validate_package_url( 'file:///tmp/plugin.zip' );

		$this->assertWPError( $result );
		$this->assertSame( 'wp_ability_invalid_plugin_package_url', $result->get_error_code() );
	}

	/**
	 * WordPress unsafe URLs are rejected even when they use HTTPS.
	 *
	 * @return void
	 */
	public function test_rejects_https_url_that_wordpress_marks_unsafe(): void {
		$installer = new Plugin_Package_Installer();
		$result    = $installer->validate_package_url( 'https://127.0.0.1/plugin.zip' );

		$this->assertWPError( $result );
		$this->assertSame( 'wp_ability_invalid_plugin_package_url', $result->get_error_code() );
	}

	/**
	 * Valid HTTPS package URLs are normalized and accepted.
	 *
	 * @return void
	 */
	public function test_accepts_valid_https_package_url(): void {
		$installer = new Plugin_Package_Installer();
		$result    = $installer->validate_package_url( ' https://example.com/plugin.zip ' );

		$this->assertSame( 'https://example.com/plugin.zip', $result );
	}
}
