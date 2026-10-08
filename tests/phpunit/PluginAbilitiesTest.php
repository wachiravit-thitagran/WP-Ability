<?php
/**
 * Plugin abilities tests.
 *
 * @package WordPress_Abilities_Bridge
 */

use WP_Ability\Plugin_Abilities;

/**
 * Test plugin-domain abilities.
 */
class PluginAbilitiesTest extends WP_UnitTestCase {

	/**
	 * Fixture plugin directory.
	 *
	 * @var string
	 */
	private $plugin_dir;

	/**
	 * Fixture plugin file.
	 *
	 * @var string
	 */
	private $plugin_file;

	/**
	 * MU fixture plugin file.
	 *
	 * @var string
	 */
	private $mu_plugin_file;

	/**
	 * Set up plugin fixtures.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		if ( ! function_exists( 'wp_register_ability' ) || ! function_exists( 'wp_get_ability' ) ) {
			$this->markTestSkipped( 'The WordPress Abilities API is unavailable.' );
		}

		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		$this->plugin_dir     = WP_PLUGIN_DIR . '/wp-ability-inventory-fixture';
		$this->plugin_file    = $this->plugin_dir . '/fixture.php';
		$this->mu_plugin_file = WPMU_PLUGIN_DIR . '/wp-ability-mu-fixture.php';

		wp_mkdir_p( $this->plugin_dir );
		wp_mkdir_p( WPMU_PLUGIN_DIR );

		file_put_contents(
			$this->plugin_file,
			"<?php\n/**\n * Plugin Name: WP Ability Inventory Fixture\n * Description: Inventory fixture description.\n * Version: 1.2.3\n * Author: Fixture Author\n */\n"
		);

		file_put_contents(
			$this->mu_plugin_file,
			"<?php\n/**\n * Plugin Name: WP Ability MU Fixture\n * Version: 4.5.6\n * Author: MU Fixture Author\n */\n"
		);

		wp_clean_plugins_cache( true );
	}

	/**
	 * Clean plugin fixtures.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		deactivate_plugins( 'wp-ability-inventory-fixture/fixture.php', true );

		if ( file_exists( $this->plugin_file ) ) {
			unlink( $this->plugin_file );
		}
		if ( is_dir( $this->plugin_dir ) ) {
			rmdir( $this->plugin_dir );
		}
		if ( file_exists( $this->mu_plugin_file ) ) {
			unlink( $this->mu_plugin_file );
		}

		wp_clean_plugins_cache( true );
		parent::tearDown();
	}

	/**
	 * Plugin get and MU inventory abilities are registered.
	 *
	 * @return void
	 */
	public function test_inventory_detail_abilities_are_registered(): void {
		$this->assertNotNull( wp_get_ability( 'wordpress/plugin-get' ) );
		$this->assertNotNull( wp_get_ability( 'wordpress/plugin-mu-list' ) );
	}

	/**
	 * Plugin list reports Core plugin metadata and activation state.
	 *
	 * @return void
	 */
	public function test_plugin_list_reports_core_metadata_and_state(): void {
		$plugins = new Plugin_Abilities();
		$result  = $plugins->plugin_list();

		$fixture = null;
		foreach ( $result['plugins'] as $plugin ) {
			if ( 'wp-ability-inventory-fixture/fixture.php' === $plugin['plugin_file'] ) {
				$fixture = $plugin;
				break;
			}
		}

		$this->assertIsArray( $fixture );
		$this->assertSame( 'WP Ability Inventory Fixture', $fixture['name'] );
		$this->assertSame( '1.2.3', $fixture['version'] );
		$this->assertSame( 'Fixture Author', $fixture['author'] );
		$this->assertSame( 'Inventory fixture description.', $fixture['description'] );
		$this->assertFalse( $fixture['active'] );
		$this->assertFalse( $fixture['network_active'] );
	}

	/**
	 * Plugin get returns one installed plugin by plugin file.
	 *
	 * @return void
	 */
	public function test_plugin_get_returns_installed_plugin(): void {
		$plugins = new Plugin_Abilities();
		$result  = $plugins->plugin_get(
			array(
				'plugin_file' => 'wp-ability-inventory-fixture/fixture.php',
			)
		);

		$this->assertIsArray( $result );
		$this->assertSame( 'wp-ability-inventory-fixture/fixture.php', $result['plugin_file'] );
		$this->assertSame( '1.2.3', $result['version'] );
	}

	/**
	 * Plugin get rejects unknown plugin files.
	 *
	 * @return void
	 */
	public function test_plugin_get_rejects_unknown_plugin(): void {
		$plugins = new Plugin_Abilities();
		$result  = $plugins->plugin_get(
			array(
				'plugin_file' => 'missing/missing.php',
			)
		);

		$this->assertWPError( $result );
		$this->assertSame( 'wp_ability_plugin_not_found', $result->get_error_code() );
	}

	/**
	 * MU plugin list uses Core MU plugin inventory.
	 *
	 * @return void
	 */
	public function test_mu_plugin_list_reports_core_metadata(): void {
		$plugins = new Plugin_Abilities();
		$result  = $plugins->plugin_mu_list();

		$fixture = null;
		foreach ( $result['plugins'] as $plugin ) {
			if ( 'wp-ability-mu-fixture.php' === $plugin['plugin_file'] ) {
				$fixture = $plugin;
				break;
			}
		}

		$this->assertIsArray( $fixture );
		$this->assertSame( 'WP Ability MU Fixture', $fixture['name'] );
		$this->assertSame( '4.5.6', $fixture['version'] );
		$this->assertSame( 'MU Fixture Author', $fixture['author'] );
		$this->assertTrue( $fixture['must_use'] );
	}

	/**
	 * Plugin discovery abilities are registered.
	 *
	 * @return void
	 */
	public function test_plugin_discovery_abilities_are_registered(): void {
		$this->assertNotNull( wp_get_ability( 'wordpress/plugin-search' ) );
		$this->assertNotNull( wp_get_ability( 'wordpress/plugin-get-information' ) );
	}

	/**
	 * Plugin search delegates to the Core plugins API query action.
	 *
	 * @return void
	 */
	public function test_plugin_search_delegates_to_plugins_api(): void {
		$captured = array();
		$filter   = static function ( $result, $action, $args ) use ( &$captured ) {
			$captured = array(
				'action' => $action,
				'args'   => $args,
			);

			return (object) array(
				'plugins' => array(
					(object) array(
						'slug' => 'akismet',
						'name' => 'Akismet',
					),
				),
				'info'    => (object) array(
					'page'    => 1,
					'pages'   => 1,
					'results' => 1,
				),
			);
		};

		add_filter( 'plugins_api', $filter, 10, 3 );

		$plugins = new Plugin_Abilities();
		$result  = $plugins->plugin_search(
			array(
				'search'   => 'spam',
				'page'     => 1,
				'per_page' => 12,
			)
		);

		remove_filter( 'plugins_api', $filter, 10 );

		$this->assertSame( 'query_plugins', $captured['action'] );
		$this->assertSame( 'spam', $captured['args']->search );
		$this->assertSame( 1, $captured['args']->page );
		$this->assertSame( 12, $captured['args']->per_page );
		$this->assertSame( 'akismet', $result['plugins'][0]['slug'] );
	}

	/**
	 * Plugin information delegates to the Core plugin_information action.
	 *
	 * @return void
	 */
	public function test_plugin_get_information_delegates_to_plugins_api(): void {
		$captured = array();
		$filter   = static function ( $result, $action, $args ) use ( &$captured ) {
			$captured = array(
				'action' => $action,
				'args'   => $args,
			);

			return (object) array(
				'slug'    => 'akismet',
				'name'    => 'Akismet',
				'version' => '9.9.9',
			);
		};

		add_filter( 'plugins_api', $filter, 10, 3 );

		$plugins = new Plugin_Abilities();
		$result  = $plugins->plugin_get_information(
			array(
				'slug' => 'Akismet',
			)
		);

		remove_filter( 'plugins_api', $filter, 10 );

		$this->assertSame( 'plugin_information', $captured['action'] );
		$this->assertSame( 'akismet', $captured['args']->slug );
		$this->assertSame( 'akismet', $result['slug'] );
		$this->assertSame( '9.9.9', $result['version'] );
	}

	/**
	 * Plugin information preserves Core API errors.
	 *
	 * @return void
	 */
	public function test_plugin_get_information_preserves_core_error(): void {
		$filter = static function () {
			return new WP_Error( 'plugins_api_failed', 'Core plugin API failure.' );
		};

		add_filter( 'plugins_api', $filter );

		$plugins = new Plugin_Abilities();
		$result  = $plugins->plugin_get_information(
			array(
				'slug' => 'missing',
			)
		);

		remove_filter( 'plugins_api', $filter );

		$this->assertWPError( $result );
		$this->assertSame( 'plugins_api_failed', $result->get_error_code() );
	}

	/**
	 * Plugin update management abilities are registered.
	 *
	 * @return void
	 */
	public function test_plugin_update_management_abilities_are_registered(): void {
		$this->assertNotNull( wp_get_ability( 'wordpress/plugin-check-updates' ) );
		$this->assertNotNull( wp_get_ability( 'wordpress/plugin-update-many' ) );
	}

	/**
	 * Plugin update management requires update_plugins.
	 *
	 * @return void
	 */
	public function test_plugin_update_management_requires_update_plugins(): void {
		$plugins    = new Plugin_Abilities();
		$subscriber = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber );
		$this->assertFalse( $plugins->can_update_plugins() );

		$administrator = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $administrator );
		$this->assertTrue( $plugins->can_update_plugins() );
	}

	/**
	 * Plugin update checks return the WordPress Core update transient.
	 *
	 * @return void
	 */
	public function test_plugin_check_updates_returns_core_update_metadata(): void {
		$inject_fixture = static function ( $value ) {
			$value->response['wp-ability-inventory-fixture/fixture.php'] = (object) array(
				'plugin'      => 'wp-ability-inventory-fixture/fixture.php',
				'new_version' => '2.0.0',
			);

			return $value;
		};

		add_filter( 'pre_set_site_transient_update_plugins', $inject_fixture );

		$plugins = new Plugin_Abilities();
		$result  = $plugins->plugin_check_updates();

		remove_filter( 'pre_set_site_transient_update_plugins', $inject_fixture );

		$this->assertArrayHasKey( 'updates', $result );
		$this->assertSame(
			'2.0.0',
			$result['updates']['response']['wp-ability-inventory-fixture/fixture.php']['new_version']
		);
	}

	/**
	 * Bulk plugin update rejects plugin files that are not installed.
	 *
	 * @return void
	 */
	public function test_plugin_update_many_rejects_unknown_plugin(): void {
		$plugins = new Plugin_Abilities();
		$result  = $plugins->plugin_update_many(
			array(
				'plugin_files' => array(
					'wp-ability-inventory-fixture/fixture.php',
					'missing/missing.php',
				),
			)
		);

		$this->assertWPError( $result );
		$this->assertSame( 'wp_ability_plugin_not_found', $result->get_error_code() );
	}

	/**
	 * Plugin auto-update abilities are registered.
	 *
	 * @return void
	 */
	public function test_plugin_auto_update_abilities_are_registered(): void {
		$this->assertNotNull( wp_get_ability( 'wordpress/plugin-enable-auto-update' ) );
		$this->assertNotNull( wp_get_ability( 'wordpress/plugin-disable-auto-update' ) );
	}

	/**
	 * Enabling auto-update preserves unrelated plugin entries.
	 *
	 * @return void
	 */
	public function test_plugin_enable_auto_update_preserves_unrelated_entries(): void {
		update_site_option( 'auto_update_plugins', array( 'other/other.php' ) );

		$plugins = new Plugin_Abilities();
		$result  = $plugins->plugin_enable_auto_update(
			array(
				'plugin_file' => 'wp-ability-inventory-fixture/fixture.php',
			)
		);

		$this->assertTrue( $result['auto_update_enabled'] );
		$this->assertSame(
			array(
				'other/other.php',
				'wp-ability-inventory-fixture/fixture.php',
			),
			get_site_option( 'auto_update_plugins' )
		);
	}

	/**
	 * Disabling auto-update removes only the selected plugin entry.
	 *
	 * @return void
	 */
	public function test_plugin_disable_auto_update_preserves_unrelated_entries(): void {
		update_site_option(
			'auto_update_plugins',
			array(
				'other/other.php',
				'wp-ability-inventory-fixture/fixture.php',
			)
		);

		$plugins = new Plugin_Abilities();
		$result  = $plugins->plugin_disable_auto_update(
			array(
				'plugin_file' => 'wp-ability-inventory-fixture/fixture.php',
			)
		);

		$this->assertFalse( $result['auto_update_enabled'] );
		$this->assertSame( array( 'other/other.php' ), get_site_option( 'auto_update_plugins' ) );
	}

	/**
	 * Plugin bulk lifecycle abilities are registered.
	 *
	 * @return void
	 */
	public function test_plugin_bulk_lifecycle_abilities_are_registered(): void {
		$this->assertNotNull( wp_get_ability( 'wordpress/plugin-activate-many' ) );
		$this->assertNotNull( wp_get_ability( 'wordpress/plugin-deactivate-many' ) );
		$this->assertNotNull( wp_get_ability( 'wordpress/plugin-delete-many' ) );
	}

	/**
	 * Bulk activate and deactivate delegate to Core plugin state.
	 *
	 * @return void
	 */
	public function test_plugin_activate_many_and_deactivate_many_change_core_state(): void {
		$plugins = new Plugin_Abilities();
		$file    = 'wp-ability-inventory-fixture/fixture.php';

		$activated = $plugins->plugin_activate_many(
			array(
				'plugin_files' => array( $file ),
			)
		);

		$this->assertIsArray( $activated );
		$this->assertTrue( is_plugin_active( $file ) );

		$deactivated = $plugins->plugin_deactivate_many(
			array(
				'plugin_files' => array( $file ),
			)
		);

		$this->assertIsArray( $deactivated );
		$this->assertFalse( is_plugin_active( $file ) );
	}

	/**
	 * Bulk delete refuses active plugins like the WordPress Plugins UI.
	 *
	 * @return void
	 */
	public function test_plugin_delete_many_rejects_active_plugin(): void {
		$file = 'wp-ability-inventory-fixture/fixture.php';
		activate_plugin( $file );

		$plugins = new Plugin_Abilities();
		$result  = $plugins->plugin_delete_many(
			array(
				'plugin_files' => array( $file ),
			)
		);

		$this->assertWPError( $result );
		$this->assertSame( 'wp_ability_plugin_active', $result->get_error_code() );
		$this->assertFileExists( $this->plugin_file );
	}

	/**
	 * Plugin inventory exposes WordPress Core dependency status.
	 *
	 * @return void
	 */
	public function test_plugin_get_exposes_core_dependency_status(): void {
		$plugins = new Plugin_Abilities();
		$result  = $plugins->plugin_get(
			array(
				'plugin_file' => 'wp-ability-inventory-fixture/fixture.php',
			)
		);

		$this->assertArrayHasKey( 'dependencies', $result );
		$this->assertArrayHasKey( 'has_unmet_dependencies', $result );
		$this->assertArrayHasKey( 'has_dependents', $result );
		$this->assertArrayHasKey( 'has_active_dependents', $result );
		$this->assertSame( array(), $result['dependencies'] );
		$this->assertFalse( $result['has_unmet_dependencies'] );
		$this->assertFalse( $result['has_dependents'] );
		$this->assertFalse( $result['has_active_dependents'] );
	}
}
