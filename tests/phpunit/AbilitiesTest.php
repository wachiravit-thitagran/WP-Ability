<?php
/**
 * Abilities tests.
 *
 * @package WP_Ability
 */

use WP_Ability\Abilities;
use WP_Ability\Core_Abilities;
use WP_Ability\Plugin_Abilities;

/**
 * Test the WordPress administration abilities.
 */
class AbilitiesTest extends WP_UnitTestCase {

	/**
	 * Register abilities for each test when the API is available.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		if ( ! function_exists( 'wp_register_ability' ) || ! function_exists( 'wp_get_ability' ) ) {
			$this->markTestSkipped( 'The WordPress Abilities API is unavailable.' );
		}
	}

	/**
	 * All expected abilities are registered.
	 *
	 * @return void
	 */
	public function test_registers_expected_abilities(): void {
		$names = array(
			'wordpress/plugin-install',
			'wordpress/plugin-update',
			'wordpress/plugin-list',
			'wordpress/plugin-activate',
			'wordpress/plugin-deactivate',
			'wordpress/plugin-delete',
			'wordpress/theme-list',
			'wordpress/theme-activate',
			'wordpress/theme-delete',
			'wordpress/user-create',
			'wordpress/user-list',
			'wordpress/user-get',
			'wordpress/user-update',
			'wordpress/user-delete',
			'wordpress/post-list',
			'wordpress/post-get',
			'wordpress/post-create',
			'wordpress/post-update',
			'wordpress/post-delete',
			'wordpress/term-list',
			'wordpress/term-create',
			'wordpress/term-update',
			'wordpress/term-delete',
			'wordpress/media-list',
			'wordpress/media-get',
			'wordpress/media-delete',
			'wordpress/option-get',
			'wordpress/option-update',
			'wordpress/option-delete',
			'wordpress/cron-list',
			'wordpress/cron-schedule',
			'wordpress/cron-run',
			'wordpress/cron-delete',
			'wordpress/transient-get',
			'wordpress/transient-set',
			'wordpress/transient-delete',
			'wordpress/cache-flush',
			'wordpress/database-optimize',
			'wordpress/rewrite-flush',
			'wordpress/update-check',
		);

		foreach ( $names as $name ) {
			$this->assertNotNull( wp_get_ability( $name ), $name . ' should be registered.' );
		}
	}



	/**
	 * Plugin abilities are isolated in the plugin-domain wrapper.
	 *
	 * @return void
	 */
	public function test_plugin_abilities_are_owned_by_plugin_abilities_class(): void {
		$this->assertTrue( class_exists( Plugin_Abilities::class ) );

		$plugins = new Plugin_Abilities();

		$this->assertTrue( method_exists( $plugins, 'can_install_plugins' ) );
		$this->assertTrue( method_exists( $plugins, 'can_update_plugins' ) );
		$this->assertTrue( method_exists( $plugins, 'can_activate_plugins' ) );
		$this->assertTrue( method_exists( $plugins, 'can_delete_plugins' ) );

		foreach (
			array(
				'wordpress/plugin-list',
				'wordpress/plugin-install',
				'wordpress/plugin-install-package',
				'wordpress/plugin-update',
				'wordpress/plugin-activate',
				'wordpress/plugin-deactivate',
				'wordpress/plugin-delete',
			) as $name
		) {
			$this->assertNotNull( wp_get_ability( $name ), $name . ' should remain registered.' );
		}
	}

	/**
	 * Plugin package ability is registered.
	 *
	 * @return void
	 */
	public function test_registers_plugin_install_package_ability(): void {
		$this->assertNotNull( wp_get_ability( 'wordpress/plugin-install-package' ) );
	}

	/**
	 * Plugin package installation requires install_plugins.
	 *
	 * @return void
	 */
	public function test_plugin_install_package_requires_install_plugins(): void {
		$plugins    = new Plugin_Abilities();
		$subscriber = self::factory()->user->create( array( 'role' => 'subscriber' ) );

		wp_set_current_user( $subscriber );

		$this->assertFalse(
			$plugins->can_install_plugin_package(
				array(
					'package_url' => 'https://example.com/plugin.zip',
					'overwrite'   => false,
					'activate'    => false,
				)
			)
		);
	}

	/**
	 * Overwriting a plugin package requires update_plugins.
	 *
	 * @return void
	 */
	public function test_plugin_install_package_overwrite_requires_update_plugins(): void {
		$plugins   = new Plugin_Abilities();
		$user_id   = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$user      = new WP_User( $user_id );

		$user->add_cap( 'install_plugins' );
		$user->add_cap( 'update_plugins', false );
		wp_set_current_user( $user_id );

		$this->assertFalse(
			$plugins->can_install_plugin_package(
				array(
					'package_url' => 'https://example.com/plugin.zip',
					'overwrite'   => true,
					'activate'    => false,
				)
			)
		);

		$user->add_cap( 'update_plugins', true );
		wp_set_current_user( 0 );
		wp_set_current_user( $user_id );

		$this->assertTrue(
			$plugins->can_install_plugin_package(
				array(
					'package_url' => 'https://example.com/plugin.zip',
					'overwrite'   => true,
					'activate'    => false,
				)
			)
		);
	}

	/**
	 * Activating an installed package requires activate_plugins.
	 *
	 * @return void
	 */
	public function test_plugin_install_package_activation_requires_activate_plugins(): void {
		$plugins   = new Plugin_Abilities();
		$user_id   = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$user      = new WP_User( $user_id );

		$user->add_cap( 'install_plugins' );
		$user->add_cap( 'activate_plugins', false );
		wp_set_current_user( $user_id );

		$this->assertFalse(
			$plugins->can_install_plugin_package(
				array(
					'package_url' => 'https://example.com/plugin.zip',
					'overwrite'   => false,
					'activate'    => true,
				)
			)
		);

		$user->add_cap( 'activate_plugins', true );
		wp_set_current_user( 0 );
		wp_set_current_user( $user_id );

		$this->assertTrue(
			$plugins->can_install_plugin_package(
				array(
					'package_url' => 'https://example.com/plugin.zip',
					'overwrite'   => false,
					'activate'    => true,
				)
			)
		);
	}


	/**
	 * Package ability delegates execution to the installer service.
	 *
	 * @return void
	 */
	public function test_plugin_install_package_delegates_to_installer(): void {
		$installer = new class() {
			/**
			 * Return a successful fixture install result.
			 *
			 * @param array $input Ability input.
			 * @return array
			 */
			public function install( array $input ) {
				return array(
					'plugin_file' => 'fixture/fixture.php',
					'installed'   => true,
					'overwritten' => ! empty( $input['overwrite'] ),
					'activated'   => ! empty( $input['activate'] ),
				);
			}
		};

		$abilities = new Plugin_Abilities( $installer );
		$result    = $abilities->plugin_install_package(
			array(
				'package_url' => 'https://example.com/plugin.zip',
				'overwrite'   => true,
				'activate'    => true,
			)
		);

		$this->assertIsArray( $result );
		$this->assertSame( 'fixture/fixture.php', $result['plugin_file'] );
		$this->assertTrue( $result['installed'] );
		$this->assertTrue( $result['overwritten'] );
		$this->assertTrue( $result['activated'] );
		$this->assertArrayNotHasKey( 'filesystem_path', $result );
	}

	/**
	 * Package ability propagates installer errors unchanged.
	 *
	 * @return void
	 */
	public function test_plugin_install_package_propagates_installer_error(): void {
		$installer = new class() {
			/**
			 * Return a fixture installer error.
			 *
			 * @param array $input Ability input.
			 * @return WP_Error
			 */
			public function install( array $input ) {
				unset( $input );
				return new WP_Error( 'fixture_error', 'Fixture failure.' );
			}
		};

		$abilities = new Plugin_Abilities( $installer );
		$result    = $abilities->plugin_install_package(
			array(
				'package_url' => 'https://example.com/plugin.zip',
			)
		);

		$this->assertWPError( $result );
		$this->assertSame( 'fixture_error', $result->get_error_code() );
	}

	/**
	 * Option updates require manage_options.
	 *
	 * @return void
	 */
	public function test_option_update_permission_requires_manage_options(): void {
		$abilities = new Abilities();

		$subscriber = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber );
		$this->assertFalse( $abilities->can_manage_options() );

		$administrator = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $administrator );
		$this->assertTrue( $abilities->can_manage_options() );
	}

	/**
	 * Expanded Core abilities preserve capability checks.
	 *
	 * @return void
	 */
	public function test_core_abilities_require_expected_capabilities(): void {
		$core    = new Core_Abilities();
		$plugins = new Plugin_Abilities();

		$subscriber = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber );
		$this->assertFalse( $plugins->can_activate_plugins() );
		$this->assertFalse( $core->can_switch_themes() );
		$this->assertFalse( $core->can_list_users() );
		$this->assertFalse( $core->can_manage_options() );

		$administrator = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $administrator );
		$this->assertTrue( $plugins->can_activate_plugins() );
		$this->assertTrue( $core->can_switch_themes() );
		$this->assertTrue( $core->can_list_users() );
		$this->assertTrue( $core->can_manage_options() );
	}

	/**
	 * Protected core options cannot be changed directly.
	 *
	 * @return void
	 */
	public function test_protected_option_is_rejected(): void {
		$administrator = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $administrator );

		$abilities = new Abilities();
		$result    = $abilities->option_update(
			array(
				'option_name' => 'active_plugins',
				'value'       => array(),
			)
		);

		$this->assertWPError( $result );
		$this->assertSame( 'wp_ability_protected_option', $result->get_error_code() );
	}

	/**
	 * JSON-compatible option values can be updated.
	 *
	 * @return void
	 */
	public function test_json_compatible_option_can_be_updated(): void {
		$administrator = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $administrator );

		$abilities = new Abilities();
		$result    = $abilities->option_update(
			array(
				'option_name' => 'wp_ability_test_option',
				'value'       => array(
					'enabled' => true,
					'label'   => 'Example',
				),
			)
		);

		$this->assertIsArray( $result );
		$this->assertSame(
			array(
				'enabled' => true,
				'label'   => 'Example',
			),
			get_option( 'wp_ability_test_option' )
		);

		delete_option( 'wp_ability_test_option' );
	}

	/**
	 * User creation never returns a password.
	 *
	 * @return void
	 */
	public function test_user_create_does_not_return_password(): void {
		$administrator = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $administrator );

		$abilities = new Abilities();
		$result    = $abilities->user_create(
			array(
				'username' => 'ability-test-user',
				'email'    => 'ability-test@example.com',
				'password' => 'correct-horse-battery-staple',
			)
		);

		$this->assertIsArray( $result );
		$this->assertArrayNotHasKey( 'password', $result );
	}
}
