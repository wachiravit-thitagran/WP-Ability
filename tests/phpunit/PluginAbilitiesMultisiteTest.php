<?php
/**
 * Multisite plugin abilities tests.
 *
 * @package WordPress_Abilities_Bridge
 */

use WP_Ability\Plugin_Abilities;

/**
 * Test network-aware plugin abilities.
 */
class PluginAbilitiesMultisiteTest extends WP_UnitTestCase {

	/**
	 * Register network plugin abilities.
	 *
	 * @return void
	 */
	public function test_network_plugin_abilities_are_registered(): void {
		if ( ! function_exists( 'wp_get_ability' ) ) {
			$this->markTestSkipped( 'The WordPress Abilities API is unavailable.' );
		}

		$this->assertNotNull( wp_get_ability( 'wordpress/plugin-network-activate' ) );
		$this->assertNotNull( wp_get_ability( 'wordpress/plugin-network-deactivate' ) );
	}

	/**
	 * Network activation rejects single-site WordPress.
	 *
	 * @return void
	 */
	public function test_network_activate_requires_multisite(): void {
		if ( is_multisite() ) {
			$this->markTestSkipped( 'This assertion targets single-site WordPress.' );
		}

		$plugins = new Plugin_Abilities();
		$result  = $plugins->plugin_network_activate(
			array(
				'plugin_file' => 'example/example.php',
			)
		);

		$this->assertWPError( $result );
		$this->assertSame( 'wp_ability_multisite_required', $result->get_error_code() );
	}

	/**
	 * Network deactivation rejects single-site WordPress.
	 *
	 * @return void
	 */
	public function test_network_deactivate_requires_multisite(): void {
		if ( is_multisite() ) {
			$this->markTestSkipped( 'This assertion targets single-site WordPress.' );
		}

		$plugins = new Plugin_Abilities();
		$result  = $plugins->plugin_network_deactivate(
			array(
				'plugin_file' => 'example/example.php',
			)
		);

		$this->assertWPError( $result );
		$this->assertSame( 'wp_ability_multisite_required', $result->get_error_code() );
	}
}
