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
		$title = ucwords( str_replace( array( 'wordpress/', '-', '_' ), array( '', ' ', ' ' ), (string) $ability_name ) );

		return array(
			'type'                 => 'object',
			'title'                => $title . ' Result',
			'description'          => sprintf(
				/* translators: %s: ability name. */
				__( 'Structured result returned by %s.', 'wp-ability' ),
				$ability_name
			),
			'additionalProperties' => true,
		);
	}
}
