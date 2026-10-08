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

		$themes = new Theme_Abilities();
		$themes->register_abilities();

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
}
