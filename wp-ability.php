<?php
/**
 * Plugin Name: WordPress Abilities Bridge
 * Plugin URI: https://github.com/wachiravit-thitagran/WordPress-Abilities-Bridge
 * Description: Exposes WordPress Core functionality as secure, permission-aware abilities through the native WordPress Abilities API.
 * Version: 0.3.2
 * Requires at least: 6.9
 * Requires PHP: 7.4
 * Author: Wachiravit Thitagran
 * License: GPL-2.0-or-later
 * Text Domain: wp-ability
 *
 * @package WordPress_Abilities_Bridge
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/includes/class-plugin-package-installer.php';
require_once __DIR__ . '/includes/class-abilities.php';
require_once __DIR__ . '/includes/class-plugin-abilities.php';
require_once __DIR__ . '/includes/class-theme-abilities.php';
require_once __DIR__ . '/includes/class-core-abilities.php';

/**
 * Boot the plugin.
 *
 * @return void
 */
function wp_ability_boot() {
	new WP_Ability\Abilities();
	new WP_Ability\Plugin_Abilities();
	new WP_Ability\Theme_Abilities();
	new WP_Ability\Core_Abilities();
}
add_action( 'plugins_loaded', 'wp_ability_boot' );
