<?php
/**
 * Plugin Name: WP Ability
 * Plugin URI: https://github.com/wachiravit-thitagran/WP-Ability
 * Description: Administrative WordPress capabilities exposed through the WordPress Abilities API.
 * Version: 0.1.0
 * Requires at least: 6.9
 * Requires PHP: 7.4
 * Author: Wachiravit Thitagran
 * License: GPL-2.0-or-later
 * Text Domain: wp-ability
 *
 * @package WP_Ability
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/includes/class-abilities.php';

/**
 * Boot the plugin.
 *
 * @return void
 */
function wp_ability_boot() {
	new WP_Ability\Abilities();
}
add_action( 'plugins_loaded', 'wp_ability_boot' );
