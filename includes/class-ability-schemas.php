<?php
/**
 * Shared ability schema helpers.
 *
 * @package WordPress_Abilities_Bridge
 */

namespace WP_Ability;

defined( 'ABSPATH' ) || exit;

/**
 * Builds consistent JSON Schemas for ability clients.
 */
final class Ability_Schemas {

	/**
	 * Add titles and descriptions to object properties recursively.
	 *
	 * @param array $schema JSON Schema.
	 * @return array
	 */
	public static function annotate( array $schema ) {
		if ( isset( $schema['properties'] ) && is_array( $schema['properties'] ) ) {
			foreach ( $schema['properties'] as $name => $property ) {
				if ( ! is_array( $property ) ) {
					continue;
				}

				$title = ucwords( str_replace( array( '_', '-' ), ' ', (string) $name ) );
				if ( empty( $property['title'] ) ) {
					$property['title'] = $title;
				}
				if ( empty( $property['description'] ) ) {
					$property['description'] = sprintf(
						/* translators: %s: schema property title. */
						__( 'Value for %s.', 'wp-ability' ),
						$title
					);
				}

				$schema['properties'][ $name ] = self::annotate( $property );
			}
		}

		if ( isset( $schema['items'] ) && is_array( $schema['items'] ) ) {
			$schema['items'] = self::annotate( $schema['items'] );
		}

		return $schema;
	}

	/**
	 * Default output schema for semantic WordPress abilities.
	 *
	 * The schema remains forward-compatible while documenting that bridge
	 * callbacks return structured objects. Domain classes may provide a
	 * narrower schema when useful.
	 *
	 * @param string $ability_name Ability name.
	 * @return array
	 */
	public static function output( $ability_name ) {
		$title      = ucwords( str_replace( array( 'wordpress/', '-', '_' ), array( '', ' ', ' ' ), (string) $ability_name ) );
		$properties = array();

		if ( 0 === strpos( $ability_name, 'wordpress/plugin-' ) ) {
			$properties = array(
				'plugin_file'         => array( 'type' => 'string' ),
				'plugins'             => array(
					'type'  => 'array',
					'items' => array( 'type' => 'object' ),
				),
				'name'                => array( 'type' => 'string' ),
				'slug'                => array( 'type' => 'string' ),
				'version'             => array( 'type' => 'string' ),
				'active'              => array( 'type' => 'boolean' ),
				'network_active'      => array( 'type' => 'boolean' ),
				'installed'           => array( 'type' => 'boolean' ),
				'overwritten'         => array( 'type' => 'boolean' ),
				'activated'           => array( 'type' => 'boolean' ),
				'deleted'             => array( 'type' => 'boolean' ),
				'updated'             => array( 'type' => 'boolean' ),
				'update_available'    => array( 'type' => 'boolean' ),
				'auto_update_enabled' => array( 'type' => 'boolean' ),
				'updates'             => array( 'type' => 'object' ),
				'results'             => array( 'type' => array( 'array', 'object' ) ),
			);
		} elseif ( 0 === strpos( $ability_name, 'wordpress/theme-' ) ) {
			$properties = array(
				'stylesheet'          => array( 'type' => 'string' ),
				'themes'              => array(
					'type'  => 'array',
					'items' => array( 'type' => 'object' ),
				),
				'name'                => array( 'type' => 'string' ),
				'slug'                => array( 'type' => 'string' ),
				'version'             => array( 'type' => 'string' ),
				'active'              => array( 'type' => 'boolean' ),
				'installed'           => array( 'type' => 'boolean' ),
				'overwritten'         => array( 'type' => 'boolean' ),
				'activated'           => array( 'type' => 'boolean' ),
				'deleted'             => array( 'type' => 'boolean' ),
				'updated'             => array( 'type' => 'boolean' ),
				'update_available'    => array( 'type' => 'boolean' ),
				'auto_update_enabled' => array( 'type' => 'boolean' ),
				'updates'             => array( 'type' => 'object' ),
				'results'             => array( 'type' => array( 'array', 'object' ) ),
			);
		} elseif ( 0 === strpos( $ability_name, 'wordpress/user-' ) ) {
			$properties = array(
				'user_id'      => array( 'type' => 'integer' ),
				'users'        => array(
					'type'  => 'array',
					'items' => array( 'type' => 'object' ),
				),
				'username'     => array( 'type' => 'string' ),
				'email'        => array( 'type' => 'string' ),
				'display_name' => array( 'type' => 'string' ),
				'roles'        => array( 'type' => 'array' ),
				'total'        => array( 'type' => 'integer' ),
				'deleted'      => array( 'type' => 'boolean' ),
			);
		} elseif ( 0 === strpos( $ability_name, 'wordpress/post-' ) ) {
			$properties = array(
				'post_id' => array( 'type' => 'integer' ),
				'posts'   => array(
					'type'  => 'array',
					'items' => array( 'type' => 'object' ),
				),
				'title'   => array( 'type' => 'string' ),
				'content' => array( 'type' => 'string' ),
				'status'  => array( 'type' => 'string' ),
				'total'   => array( 'type' => 'integer' ),
				'deleted' => array( 'type' => 'boolean' ),
			);
		} elseif ( 0 === strpos( $ability_name, 'wordpress/term-' ) ) {
			$properties = array(
				'term_id'  => array( 'type' => 'integer' ),
				'terms'    => array(
					'type'  => 'array',
					'items' => array( 'type' => 'object' ),
				),
				'name'     => array( 'type' => 'string' ),
				'slug'     => array( 'type' => 'string' ),
				'taxonomy' => array( 'type' => 'string' ),
				'deleted'  => array( 'type' => 'boolean' ),
			);
		} elseif ( 0 === strpos( $ability_name, 'wordpress/media-' ) ) {
			$properties = array(
				'attachment_id' => array( 'type' => 'integer' ),
				'media'         => array(
					'type'  => 'array',
					'items' => array( 'type' => 'object' ),
				),
				'title'         => array( 'type' => 'string' ),
				'url'           => array( 'type' => 'string' ),
				'mime_type'     => array( 'type' => 'string' ),
				'alt_text'      => array( 'type' => 'string' ),
				'deleted'       => array( 'type' => 'boolean' ),
			);
		} elseif ( 0 === strpos( $ability_name, 'wordpress/comment-' ) ) {
			$properties = array(
				'comment_id' => array( 'type' => 'integer' ),
				'comments'   => array(
					'type'  => 'array',
					'items' => array( 'type' => 'object' ),
				),
				'post_id'    => array( 'type' => 'integer' ),
				'content'    => array( 'type' => 'string' ),
				'status'     => array( 'type' => 'string' ),
				'total'      => array( 'type' => 'integer' ),
				'deleted'    => array( 'type' => 'boolean' ),
			);
		} elseif ( 0 === strpos( $ability_name, 'wordpress/option-' ) ) {
			$properties = array(
				'option_name' => array( 'type' => 'string' ),
				'value'       => array(),
				'updated'     => array( 'type' => 'boolean' ),
				'deleted'     => array( 'type' => 'boolean' ),
				'value_type'  => array( 'type' => 'string' ),
			);
		} elseif ( 0 === strpos( $ability_name, 'wordpress/cron-' ) ) {
			$properties = array(
				'events'    => array(
					'type'  => 'array',
					'items' => array( 'type' => 'object' ),
				),
				'hook'      => array( 'type' => 'string' ),
				'timestamp' => array( 'type' => 'integer' ),
				'scheduled' => array( 'type' => 'boolean' ),
				'deleted'   => array( 'type' => 'boolean' ),
				'ran'       => array( 'type' => 'boolean' ),
			);
		} elseif ( 0 === strpos( $ability_name, 'wordpress/transient-' ) ) {
			$properties = array(
				'transient_name' => array( 'type' => 'string' ),
				'value'          => array(),
				'set'            => array( 'type' => 'boolean' ),
				'deleted'        => array( 'type' => 'boolean' ),
			);
		} else {
			$properties = array(
				'success' => array( 'type' => 'boolean' ),
			);
		}

		return array(
			'type'                 => 'object',
			'title'                => $title . ' Result',
			'description'          => sprintf(
				/* translators: %s: ability name. */
				__( 'Structured result returned by %s.', 'wp-ability' ),
				$ability_name
			),
			'properties'           => $properties,
			'additionalProperties' => true,
		);
	}
}
