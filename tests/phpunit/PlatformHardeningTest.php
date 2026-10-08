<?php
/**
 * Platform hardening tests.
 *
 * @package WordPress_Abilities_Bridge
 */

use WP_Ability\Comment_Abilities;
use WP_Ability\Content_Abilities;
use WP_Ability\Cron_Abilities;
use WP_Ability\Maintenance_Abilities;
use WP_Ability\Media_Abilities;
use WP_Ability\Option_Abilities;
use WP_Ability\Taxonomy_Abilities;
use WP_Ability\User_Abilities;

/**
 * Tests shared ability contracts and domain ownership.
 */
class PlatformHardeningTest extends WP_UnitTestCase {

	/**
	 * Every public WordPress ability exposes an output schema.
	 *
	 * @return void
	 */
	public function test_all_wordpress_abilities_expose_output_schema(): void {
		foreach ( $this->wordpress_ability_names() as $name ) {
			$ability = wp_get_ability( $name );

			$this->assertNotNull( $ability, $name . ' should be registered.' );
			$this->assertNotEmpty( $ability->get_output_schema(), $name . ' should define output_schema.' );
			$this->assertSame( 'object', $ability->get_output_schema()['type'], $name . ' output_schema should be an object.' );
		}
	}

	/**
	 * Input and output object properties expose client-facing metadata.
	 *
	 * @return void
	 */
	public function test_all_ability_schema_properties_have_titles_and_descriptions(): void {
		foreach ( $this->wordpress_ability_names() as $name ) {
			$ability = wp_get_ability( $name );

			$this->assert_schema_property_metadata( $ability->get_input_schema(), $name . ' input' );
			$this->assert_schema_property_metadata( $ability->get_output_schema(), $name . ' output' );
		}
	}

	/**
	 * Broad Core responsibilities are split into focused domain classes.
	 *
	 * @return void
	 */
	public function test_core_domains_have_dedicated_ability_classes(): void {
		foreach (
			array(
				User_Abilities::class,
				Content_Abilities::class,
				Taxonomy_Abilities::class,
				Media_Abilities::class,
				Option_Abilities::class,
				Cron_Abilities::class,
				Maintenance_Abilities::class,
				Comment_Abilities::class,
			) as $class_name
		) {
			$this->assertTrue( class_exists( $class_name ), $class_name . ' should exist.' );
		}
	}

	/**
	 * Media management includes Core-backed create/update operations.
	 *
	 * @return void
	 */
	public function test_media_management_surface_is_registered(): void {
		foreach (
			array(
				'wordpress/media-upload',
				'wordpress/media-update',
			) as $name
		) {
			$this->assertNotNull( wp_get_ability( $name ), $name . ' should be registered.' );
		}
	}

	/**
	 * Comment management surface is registered.
	 *
	 * @return void
	 */
	public function test_comment_management_surface_is_registered(): void {
		foreach (
			array(
				'wordpress/comment-list',
				'wordpress/comment-get',
				'wordpress/comment-create',
				'wordpress/comment-update',
				'wordpress/comment-delete',
				'wordpress/comment-approve',
				'wordpress/comment-spam',
				'wordpress/comment-trash',
			) as $name
		) {
			$this->assertNotNull( wp_get_ability( $name ), $name . ' should be registered.' );
		}
	}

	/**
	 * Assert metadata for JSON Schema object properties recursively.
	 *
	 * @param array  $schema Schema.
	 * @param string $context Assertion context.
	 * @return void
	 */
	private function assert_schema_property_metadata( array $schema, $context ): void {
		if ( isset( $schema['properties'] ) && is_array( $schema['properties'] ) ) {
			foreach ( $schema['properties'] as $property_name => $property_schema ) {
				$this->assertArrayHasKey( 'title', $property_schema, $context . ' property ' . $property_name . ' should define title.' );
				$this->assertNotSame( '', trim( (string) $property_schema['title'] ) );
				$this->assertArrayHasKey( 'description', $property_schema, $context . ' property ' . $property_name . ' should define description.' );
				$this->assertNotSame( '', trim( (string) $property_schema['description'] ) );

				if ( is_array( $property_schema ) ) {
					$this->assert_schema_property_metadata( $property_schema, $context . '.' . $property_name );
				}
			}
		}

		if ( isset( $schema['items'] ) && is_array( $schema['items'] ) ) {
			$this->assert_schema_property_metadata( $schema['items'], $context . ' items' );
		}
	}

	/**
	 * WordPress ability names owned by this bridge.
	 *
	 * @return array
	 */
	private function wordpress_ability_names() {
		return array(
			'wordpress/cache-flush',
			'wordpress/cron-delete',
			'wordpress/cron-list',
			'wordpress/cron-run',
			'wordpress/cron-schedule',
			'wordpress/database-optimize',
			'wordpress/media-delete',
			'wordpress/media-get',
			'wordpress/media-list',
			'wordpress/media-upload',
			'wordpress/media-update',
			'wordpress/option-delete',
			'wordpress/option-get',
			'wordpress/option-update',
			'wordpress/plugin-activate',
			'wordpress/plugin-activate-many',
			'wordpress/plugin-check-updates',
			'wordpress/plugin-deactivate',
			'wordpress/plugin-deactivate-many',
			'wordpress/plugin-delete',
			'wordpress/plugin-delete-many',
			'wordpress/plugin-disable-auto-update',
			'wordpress/plugin-enable-auto-update',
			'wordpress/plugin-get',
			'wordpress/plugin-get-information',
			'wordpress/plugin-install',
			'wordpress/plugin-install-package',
			'wordpress/plugin-list',
			'wordpress/plugin-mu-list',
			'wordpress/plugin-network-activate',
			'wordpress/plugin-network-deactivate',
			'wordpress/plugin-search',
			'wordpress/plugin-update',
			'wordpress/plugin-update-many',
			'wordpress/post-create',
			'wordpress/post-delete',
			'wordpress/post-get',
			'wordpress/post-list',
			'wordpress/post-update',
			'wordpress/rewrite-flush',
			'wordpress/term-create',
			'wordpress/term-delete',
			'wordpress/term-list',
			'wordpress/term-update',
			'wordpress/theme-activate',
			'wordpress/theme-check-updates',
			'wordpress/theme-delete',
			'wordpress/theme-disable-auto-update',
			'wordpress/theme-enable-auto-update',
			'wordpress/theme-get',
			'wordpress/theme-get-information',
			'wordpress/theme-install',
			'wordpress/theme-install-package',
			'wordpress/theme-list',
			'wordpress/theme-search',
			'wordpress/theme-update',
			'wordpress/theme-update-many',
			'wordpress/transient-delete',
			'wordpress/transient-get',
			'wordpress/transient-set',
			'wordpress/update-check',
			'wordpress/user-create',
			'wordpress/user-delete',
			'wordpress/user-get',
			'wordpress/user-list',
			'wordpress/user-update',
			'wordpress/comment-list',
			'wordpress/comment-get',
			'wordpress/comment-create',
			'wordpress/comment-update',
			'wordpress/comment-delete',
			'wordpress/comment-approve',
			'wordpress/comment-spam',
			'wordpress/comment-trash',
		);
	}
}
