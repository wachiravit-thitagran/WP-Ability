<?php
/**
 * Abilities tests.
 *
 * @package WP_Ability
 */

use WP_Ability\Abilities;

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
			'wordpress/user-create',
			'wordpress/option-update',
			'wordpress/media-delete',
			'wordpress/cron-run',
			'wordpress/cache-flush',
			'wordpress/database-optimize',
		);

		foreach ( $names as $name ) {
			$this->assertNotNull( wp_get_ability( $name ), $name . ' should be registered.' );
		}
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
