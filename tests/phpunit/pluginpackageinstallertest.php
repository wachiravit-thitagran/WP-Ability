<?php
/**
 * Plugin package installer tests.
 *
 * @package WP_Ability
 */

use WP_Ability\Plugin_Package_Installer;


require_once __DIR__ . '/class-wpabilityfakepluginupgrader.php';

/**
 * Test package source validation.
 */
class PluginPackageInstallerTest extends WP_UnitTestCase {


	/**
	 * Successful installs delegate to the upgrader without overwrite.
	 *
	 * @return void
	 */
	public function test_installs_package_with_native_upgrader(): void {
		$fake      = new WPAbilityFakePluginUpgrader();
		$installer = new Plugin_Package_Installer(
			static function () use ( $fake ) {
				return $fake;
			}
		);

		$result = $installer->install(
			array(
				'package_url' => 'https://example.com/plugin.zip',
				'overwrite'   => false,
				'activate'    => false,
			)
		);

		$this->assertIsArray( $result );
		$this->assertSame( 'https://example.com/plugin.zip', $fake->package );
		$this->assertFalse( $fake->args['overwrite_package'] );
		$this->assertTrue( $fake->args['clear_update_cache'] );
		$this->assertSame( 'wp-ability-package-fixture/fixture.php', $result['plugin_file'] );
		$this->assertTrue( $result['installed'] );
		$this->assertFalse( $result['overwritten'] );
		$this->assertFalse( $result['activated'] );
	}

	/**
	 * Overwrite requests enable overwrite_package.
	 *
	 * @return void
	 */
	public function test_overwrites_package_when_requested(): void {
		$fake      = new WPAbilityFakePluginUpgrader();
		$installer = new Plugin_Package_Installer(
			static function () use ( $fake ) {
				return $fake;
			}
		);

		$result = $installer->install(
			array(
				'package_url' => 'https://example.com/plugin.zip',
				'overwrite'   => true,
				'activate'    => false,
			)
		);

		$this->assertIsArray( $result );
		$this->assertTrue( $fake->args['overwrite_package'] );
		$this->assertTrue( $result['overwritten'] );
	}

	/**
	 * Existing package refusal is returned as a structured error.
	 *
	 * @return void
	 */
	public function test_existing_plugin_without_overwrite_returns_error(): void {
		$fake                 = new WPAbilityFakePluginUpgrader();
		$fake->install_result = new WP_Error( 'folder_exists', 'Destination folder already exists.' );
		$installer            = new Plugin_Package_Installer(
			static function () use ( $fake ) {
				return $fake;
			}
		);

		$result = $installer->install(
			array(
				'package_url' => 'https://example.com/plugin.zip',
				'overwrite'   => false,
			)
		);

		$this->assertWPError( $result );
		$this->assertSame( 'folder_exists', $result->get_error_code() );
	}

	/**
	 * Upgrader WP_Error values are propagated.
	 *
	 * @return void
	 */
	public function test_propagates_plugin_upgrader_error(): void {
		$fake                 = new WPAbilityFakePluginUpgrader();
		$fake->install_result = new WP_Error( 'download_failed', 'Download failed.' );
		$installer            = new Plugin_Package_Installer(
			static function () use ( $fake ) {
				return $fake;
			}
		);

		$result = $installer->install(
			array(
				'package_url' => 'https://example.com/plugin.zip',
			)
		);

		$this->assertWPError( $result );
		$this->assertSame( 'download_failed', $result->get_error_code() );
	}

	/**
	 * A false upgrader result becomes a structured install error.
	 *
	 * @return void
	 */
	public function test_false_plugin_upgrader_result_returns_error(): void {
		$fake                 = new WPAbilityFakePluginUpgrader();
		$fake->install_result = false;
		$installer            = new Plugin_Package_Installer(
			static function () use ( $fake ) {
				return $fake;
			}
		);

		$result = $installer->install(
			array(
				'package_url' => 'https://example.com/plugin.zip',
			)
		);

		$this->assertWPError( $result );
		$this->assertSame( 'wp_ability_plugin_package_install_failed', $result->get_error_code() );
	}

	/**
	 * Missing plugin_info is rejected.
	 *
	 * @return void
	 */
	public function test_missing_plugin_info_returns_error(): void {
		$fake              = new WPAbilityFakePluginUpgrader();
		$fake->plugin_file = '';
		$installer         = new Plugin_Package_Installer(
			static function () use ( $fake ) {
				return $fake;
			}
		);

		$result = $installer->install(
			array(
				'package_url' => 'https://example.com/plugin.zip',
			)
		);

		$this->assertWPError( $result );
		$this->assertSame( 'wp_ability_plugin_file_unknown', $result->get_error_code() );
	}

	/**
	 * Activation errors are propagated.
	 *
	 * @return void
	 */
	public function test_activation_error_is_propagated(): void {
		$fake      = new WPAbilityFakePluginUpgrader();
		$installer = new Plugin_Package_Installer(
			static function () use ( $fake ) {
				return $fake;
			},
			static function () {
				return new WP_Error( 'activation_failed', 'Activation failed.' );
			},
			static function () {
				return false;
			}
		);

		$result = $installer->install(
			array(
				'package_url' => 'https://example.com/plugin.zip',
				'activate'    => true,
			)
		);

		$this->assertWPError( $result );
		$this->assertSame( 'activation_failed', $result->get_error_code() );
	}

	/**
	 * Activation must be verifiably active.
	 *
	 * @return void
	 */
	public function test_activation_must_be_verified(): void {
		$fake      = new WPAbilityFakePluginUpgrader();
		$installer = new Plugin_Package_Installer(
			static function () use ( $fake ) {
				return $fake;
			},
			static function () {
				return null;
			},
			static function () {
				return false;
			}
		);

		$result = $installer->install(
			array(
				'package_url' => 'https://example.com/plugin.zip',
				'activate'    => true,
			)
		);

		$this->assertWPError( $result );
		$this->assertSame( 'wp_ability_plugin_activation_unverified', $result->get_error_code() );
	}

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
