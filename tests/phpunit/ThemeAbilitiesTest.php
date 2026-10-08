<?php
/**
 * Theme abilities tests.
 *
 * @package WP_Ability
 */

use WP_Ability\Theme_Abilities;

/**
 * Test WordPress theme management abilities.
 */
class ThemeAbilitiesTest extends WP_UnitTestCase {

	/**
	 * Complete theme-management ability surface is registered.
	 *
	 * @return void
	 */
	public function test_registers_complete_theme_management_surface(): void {
		$this->assertTrue( class_exists( Theme_Abilities::class ) );

		foreach (
			array(
				'wordpress/theme-list',
				'wordpress/theme-get',
				'wordpress/theme-search',
				'wordpress/theme-get-information',
				'wordpress/theme-install',
				'wordpress/theme-install-package',
				'wordpress/theme-check-updates',
				'wordpress/theme-update',
				'wordpress/theme-update-many',
				'wordpress/theme-enable-auto-update',
				'wordpress/theme-disable-auto-update',
				'wordpress/theme-activate',
				'wordpress/theme-delete',
			) as $name
		) {
			$this->assertNotNull( wp_get_ability( $name ), $name . ' should be registered.' );
		}
	}

	/**
	 * Theme package installation loads the Core filesystem API.
	 *
	 * @return void
	 */
	public function test_theme_package_install_loads_core_filesystem_api(): void {
		$this->assertTrue( class_exists( Theme_Abilities::class ) );

		$reflection = new ReflectionMethod( Theme_Abilities::class, 'theme_install_package' );
		$source     = file( $reflection->getFileName() );
		$body       = implode(
			'',
			array_slice(
				$source,
				$reflection->getStartLine() - 1,
				$reflection->getEndLine() - $reflection->getStartLine() + 1
			)
		);

		$this->assertStringContainsString(
			"require_once ABSPATH . 'wp-admin/includes/file.php';",
			$body
		);
	}

	/**
	 * Theme management capabilities follow WordPress Core roles.
	 *
	 * @return void
	 */
	public function test_theme_management_requires_core_capabilities(): void {
		$themes     = new Theme_Abilities();
		$subscriber = self::factory()->user->create( array( 'role' => 'subscriber' ) );

		wp_set_current_user( $subscriber );
		$this->assertFalse( $themes->can_switch_themes() );
		$this->assertFalse( $themes->can_install_themes() );
		$this->assertFalse( $themes->can_update_themes() );
		$this->assertFalse( $themes->can_delete_themes() );

		$administrator = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $administrator );
		$this->assertTrue( $themes->can_switch_themes() );
		$this->assertTrue( $themes->can_install_themes() );
		$this->assertTrue( $themes->can_update_themes() );
		$this->assertTrue( $themes->can_delete_themes() );
	}

	/**
	 * Theme auto-update toggles preserve unrelated Core state.
	 *
	 * @return void
	 */
	public function test_theme_auto_update_preserves_unrelated_entries(): void {
		$themes     = new Theme_Abilities();
		$stylesheet = get_stylesheet();
		$unrelated  = 'wp-ability-unrelated-theme';

		update_site_option( 'auto_update_themes', array( $unrelated ) );

		$enabled = $themes->theme_enable_auto_update( array( 'stylesheet' => $stylesheet ) );
		$this->assertIsArray( $enabled );
		$this->assertContains( $unrelated, get_site_option( 'auto_update_themes', array() ) );
		$this->assertContains( $stylesheet, get_site_option( 'auto_update_themes', array() ) );

		$disabled = $themes->theme_disable_auto_update( array( 'stylesheet' => $stylesheet ) );
		$this->assertIsArray( $disabled );
		$this->assertContains( $unrelated, get_site_option( 'auto_update_themes', array() ) );
		$this->assertNotContains( $stylesheet, get_site_option( 'auto_update_themes', array() ) );

		delete_site_option( 'auto_update_themes' );
	}
}
