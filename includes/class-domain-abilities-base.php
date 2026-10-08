<?php
/**
 * Shared helpers for focused ability domains.
 *
 * @package WordPress_Abilities_Bridge
 */

namespace WP_Ability;

defined( 'ABSPATH' ) || exit;

/**
 * Shared registration helpers for semantic WordPress ability domains.
 */
abstract class Domain_Abilities_Base {

	/**
	 * Register an ability with shared defaults.
	 *
	 * @param string   $name Ability name.
	 * @param string   $label Human label.
	 * @param string   $description Description.
	 * @param array    $schema Input schema.
	 * @param callable $execute Execute callback.
	 * @param callable $permission Permission callback.
	 * @param bool     $readonly Read-only operation.
	 * @param bool     $destructive Destructive operation.
	 * @param bool     $idempotent Idempotent operation.
	 * @param bool     $open_world May access external systems.
	 * @return void
	 */
	protected function register( $name, $label, $description, array $schema, $execute, $permission, $readonly, $destructive, $idempotent, $open_world = false ) {
		Ability_Registrar::register(
			$name,
			array(
				'label'               => $label,
				'description'         => $description,
				'category'            => 'wordpress-admin',
				'input_schema'        => $schema,
				'execute_callback'    => $execute,
				'permission_callback' => $permission,
				'meta'                => array(
					'public'      => true,
					'annotations' => array(
						'readonly'      => (bool) $readonly,
						'destructive'   => (bool) $destructive,
						'idempotent'    => (bool) $idempotent,
						'openWorldHint' => (bool) $open_world,
					),
				),
			)
		);
	}

	/**
	 * Shared metadata for directly registered abilities.
	 *
	 * @param bool $readonly Read-only.
	 * @param bool $destructive Destructive.
	 * @param bool $idempotent Idempotent.
	 * @param bool $open_world Open-world.
	 * @return array
	 */
	protected function meta( $readonly, $destructive, $idempotent, $open_world = false ) {
		return array(
			'public'      => true,
			'annotations' => array(
				'readonly'      => (bool) $readonly,
				'destructive'   => (bool) $destructive,
				'idempotent'    => (bool) $idempotent,
				'openWorldHint' => (bool) $open_world,
			),
		);
	}

	/**
	 * Empty object schema.
	 *
	 * @return array
	 */
	protected function empty_schema() {
		return array(
			'type'                 => 'object',
			'properties'           => array(),
			'additionalProperties' => false,
		);
	}
}
