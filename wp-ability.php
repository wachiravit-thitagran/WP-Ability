<?php
/**
 * Plugin Name: WordPress Abilities Bridge
 * Plugin URI: https://github.com/wachiravit-thitagran/WordPress-Abilities-Bridge
 * Description: Exposes WordPress Core functionality as secure, permission-aware abilities through the native WordPress Abilities API.
 * Version: 0.5.1
 * Requires at least: 6.9
 * Requires PHP: 7.4
 * Author: Wachiravit Thitagran
 * License: GPL-2.0-or-later
 * Text Domain: wp-ability
 *
 * @package WordPress_Abilities_Bridge
 */

defined( 'ABSPATH' ) || exit;

defined( 'WP_ABILITY_VERSION' ) || define( 'WP_ABILITY_VERSION', '0.5.1' );

require_once __DIR__ . '/includes/class-ability-schemas.php';
require_once __DIR__ . '/includes/class-ability-registrar.php';
require_once __DIR__ . '/includes/class-domain-abilities-base.php';
require_once __DIR__ . '/includes/class-user-abilities.php';
require_once __DIR__ . '/includes/class-content-abilities.php';
require_once __DIR__ . '/includes/class-taxonomy-abilities.php';
require_once __DIR__ . '/includes/class-media-abilities.php';
require_once __DIR__ . '/includes/class-option-abilities.php';
require_once __DIR__ . '/includes/class-cron-abilities.php';
require_once __DIR__ . '/includes/class-maintenance-abilities.php';
require_once __DIR__ . '/includes/class-comment-abilities.php';
require_once __DIR__ . '/includes/class-package-integrity-verifier.php';
require_once __DIR__ . '/includes/class-github-self-updater.php';
require_once __DIR__ . '/includes/class-audit-context.php';
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
	new WP_Ability\User_Abilities();
	new WP_Ability\Content_Abilities();
	new WP_Ability\Taxonomy_Abilities();
	new WP_Ability\Media_Abilities();
	new WP_Ability\Option_Abilities();
	new WP_Ability\Cron_Abilities();
	new WP_Ability\Maintenance_Abilities();
	new WP_Ability\Comment_Abilities();
	new WP_Ability\GitHub_Self_Updater();
	new WP_Ability\Audit_Context();
}
add_action( 'plugins_loaded', 'wp_ability_boot' );
