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
		$this->assertSame( 'spam', $captured['args']['search'] );
		$this->assertSame( 1, $captured['args']['page'] );
		$this->assertSame( 12, $captured['args']['per_page'] );
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
		$this->assertSame( 'akismet', $captured['args']['slug'] );
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

}
