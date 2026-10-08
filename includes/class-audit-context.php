<?php
/**
 * Ability audit event context.
 *
 * @package WordPress_Abilities_Bridge
 */

namespace WP_Ability;

defined( 'ABSPATH' ) || exit;

/**
 * Republishes native execution lifecycle hooks as normalized, non-persistent events.
 */
final class Audit_Context {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'wp_before_execute_ability', array( $this, 'capture_before' ), 10, 3 );
		add_action( 'wp_after_execute_ability', array( $this, 'capture_after' ), 10, 4 );
	}

	/**
	 * Emit a pre-execution audit event without raw input values.
	 *
	 * @param string $ability_name Ability name.
	 * @param mixed  $input Input.
	 * @param mixed  $ability Ability object.
	 * @return void
	 */
	public function capture_before( $ability_name, $input, $ability ) {
		unset( $ability );
		$event = array(
			'phase'      => 'before',
			'ability'    => (string) $ability_name,
			'user_id'    => (int) get_current_user_id(),
			'input_keys' => is_array( $input ) ? array_values( array_map( 'strval', array_keys( $input ) ) ) : array(),
			'timestamp'  => time(),
		);
		$this->emit( $event );
	}

	/**
	 * Emit a post-execution audit event without raw result values.
	 *
	 * @param string $ability_name Ability name.
	 * @param mixed  $input Input.
	 * @param mixed  $result Result.
	 * @param mixed  $ability Ability object.
	 * @return void
	 */
	public function capture_after( $ability_name, $input, $result, $ability ) {
		unset( $input, $ability );
		$event = array(
			'phase'       => 'after',
			'ability'     => (string) $ability_name,
			'user_id'     => (int) get_current_user_id(),
			'result_type' => is_wp_error( $result ) ? 'error' : gettype( $result ),
			'timestamp'   => time(),
		);
		if ( is_wp_error( $result ) ) {
			$event['error_code'] = $result->get_error_code();
		}
		$this->emit( $event );
	}

	/**
	 * Emit filterable context for external observability systems.
	 *
	 * @param array $event Event context.
	 * @return void
	 */
	private function emit( array $event ) {
		$event = apply_filters( 'wp_ability_audit_event_context', $event );
		do_action( 'wp_ability_audit_event', $event );
	}
}
