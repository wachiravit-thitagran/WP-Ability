<?php
/**
 * Fake plugin upgrader used by package installer tests.
 *
 * @package WP_Ability
 */

/**
 * Fake upgrader used to observe native installer orchestration.
 */
class WPAbilityFakePluginUpgrader {
	/**
	 * Result returned by install().
	 *
	 * @var mixed
	 */
	public $install_result = true;

	/**
	 * Plugin file returned by plugin_info().
	 *
	 * @var string
	 */
	public $plugin_file = 'wp-ability-package-fixture/fixture.php';

	/**
	 * Last package passed to install().
	 *
	 * @var string|null
	 */
	public $package;

	/**
	 * Last arguments passed to install().
	 *
	 * @var array
	 */
	public $args = array();

	/**
	 * Simulate Plugin_Upgrader::install().
	 *
	 * @param string $package Package URL.
	 * @param array  $args    Install arguments.
	 * @return mixed
	 */
	public function install( $package, $args = array() ) {
		$this->package = $package;
		$this->args    = $args;
		return $this->install_result;
	}

	/**
	 * Simulate Plugin_Upgrader::plugin_info().
	 *
	 * @return string
	 */
	public function plugin_info() {
		return $this->plugin_file;
	}
}
