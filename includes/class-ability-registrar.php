<?php
/**
 * Shared WordPress Ability registration.
 *
 * @package WordPress_Abilities_Bridge
 */

namespace WP_Ability;

defined( 'ABSPATH' ) || exit;

/**
 * Applies shared schema and compatibility policy to every bridge ability.
 */
final class Ability_Registrar {

	/**
	 * Register an ability without replacing an ability already owned by Core.
	 *
	 * @param string $name Ability name.
	 * @param array  $args Ability registration arguments.
	 * @return mixed
	 */
	public static function register( $name, array $args ) {
		if ( function_exists( 'wp_has_ability' ) && wp_has_ability( $name ) ) {
			return wp_get_ability( $name );
		}

		$args['input_schema'] = Ability_Schemas::annotate(
			isset( $args['input_schema'] ) && is_array( $args['input_schema'] )
				? $args['input_schema']
				: array(
					'type'                 => 'object',
					'properties'           => array(),
					'additionalProperties' => false,
				)
		);

		$args['output_schema'] = Ability_Schemas::annotate(
			isset( $args['output_schema'] ) && is_array( $args['output_schema'] ) && ! empty( $args['output_schema'] )
				? $args['output_schema']
				: Ability_Schemas::output( $name )
		);

		if ( ! isset( $args['meta'] ) || ! is_array( $args['meta'] ) ) {
			$args['meta'] = array();
		}

		$equivalent = self::core_equivalent( $name );
		if ( $equivalent ) {
			$args['meta']['coreEquivalent'] = $equivalent;
			$args['meta']['coreEquivalentAvailable'] = function_exists( 'wp_has_ability' ) && wp_has_ability( $equivalent );
		}

		return wp_register_ability( $name, $args );
	}

	/**
	 * Map bridge abilities to official WordPress Core equivalents where known.
	 *
	 * @param string $name Bridge ability name.
	 * @return string|null
	 */
	public static function core_equivalent( $name ) {
		$map = array(
			'wordpress/user-list'  => 'core/read-users',
			'wordpress/post-list'  => 'core/read-content',
			'wordpress/post-get'   => 'core/read-content',
			'wordpress/option-get' => 'core/read-settings',
		);

		return isset( $map[ $name ] ) ? $map[ $name ] : null;
	}
}
