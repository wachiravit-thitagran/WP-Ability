<?php
/**
 * Cron Abilities.
 *
 * @package WordPress_Abilities_Bridge
 */

namespace WP_Ability;

defined( 'ABSPATH' ) || exit;

/**
 * Focused WordPress ability domain.
 */
final class Cron_Abilities extends Domain_Abilities_Base {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'wp_abilities_api_init', array( $this, 'register_abilities' ) );
	}

	/**
	 * Register this domain's abilities.
	 *
	 * @return void
	 */
	public function register_abilities() {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		$this->register_cron_run();
		$this->register_cron();
		$this->register_transients();
	}

	/**
	 * Register cron run ability.
	 *
	 * @return void
	 */
	private function register_cron_run() {
		Ability_Registrar::register(
			'wordpress/cron-run',
			array(
				'label'               => __( 'Run Scheduled WordPress Event', 'wp-ability' ),
				'description'         => __( 'Runs one existing WordPress cron event identified by hook, timestamp, and optional arguments, then updates its schedule consistently with WordPress cron behavior.', 'wp-ability' ),
				'category'            => 'wordpress-admin',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'hook'      => array(
							'type'      => 'string',
							'minLength' => 1,
						),
						'timestamp' => array(
							'type'    => 'integer',
							'minimum' => 1,
						),
						'args'      => array(
							'type'    => 'array',
							'default' => array(),
						),
					),
					'required'             => array( 'hook', 'timestamp' ),
					'additionalProperties' => false,
				),
				'execute_callback'    => array( $this, 'cron_run' ),
				'permission_callback' => array( $this, 'can_manage_options' ),
				'meta'                => $this->meta( false, true, false, false ),
			)
		);
	}

	/**
	 * Run one scheduled event.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public function cron_run( array $input ) {
		$hook      = sanitize_key( $input['hook'] );
		$timestamp = (int) $input['timestamp'];
		$args      = isset( $input['args'] ) && is_array( $input['args'] ) ? $input['args'] : array();

		$event = wp_get_scheduled_event( $hook, $args, $timestamp );

		if ( ! $event ) {
			return new \WP_Error( 'wp_ability_cron_event_not_found', __( 'The requested scheduled event was not found.', 'wp-ability' ) );
		}

		if ( ! empty( $event->schedule ) ) {
			$rescheduled = wp_reschedule_event( $event->timestamp, $event->schedule, $event->hook, $event->args, true );

			if ( is_wp_error( $rescheduled ) ) {
				return $rescheduled;
			}
		}

		$unscheduled = wp_unschedule_event( $event->timestamp, $event->hook, $event->args, true );

		if ( is_wp_error( $unscheduled ) ) {
			return $unscheduled;
		}

		do_action_ref_array( $event->hook, $event->args );

		return array(
			'hook'      => $event->hook,
			'timestamp' => (int) $event->timestamp,
			'ran'       => true,
			'recurring' => ! empty( $event->schedule ),
		);
	}

	/**
	 * Check whether the current user can perform this ability.
	 *
	 * @return bool
	 */
	public function can_manage_options() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Register cron abilities.
	 *
	 * @return void
	 */
	private function register_cron() {
		$this->register( 'wordpress/cron-list', __( 'List WordPress Cron Events', 'wp-ability' ), __( 'Lists scheduled WordPress cron events.', 'wp-ability' ), $this->empty_schema(), array( $this, 'cron_list' ), array( $this, 'can_manage_options' ), true, false, true );
		$this->register(
			'wordpress/cron-schedule',
			__( 'Schedule WordPress Cron Event', 'wp-ability' ),
			__( 'Schedules a one-time or recurring WordPress cron event.', 'wp-ability' ),
			array(
				'type' => 'object',
				'properties' => array(
					'hook' => array(
						'type' => 'string',
						'minLength' => 1,
					),
					'timestamp' => array(
						'type' => 'integer',
						'minimum' => 1,
					),
					'recurrence' => array( 'type' => array( 'string', 'null' ) ),
					'args' => array(
						'type' => 'array',
						'default' => array(),
					),
				),
				'required' => array( 'hook', 'timestamp' ),
				'additionalProperties' => false,
			),
			array( $this, 'cron_schedule' ),
			array( $this, 'can_manage_options' ),
			false,
			false,
			false
		);
		$this->register(
			'wordpress/cron-delete',
			__( 'Delete WordPress Cron Event', 'wp-ability' ),
			__( 'Unschedules one WordPress cron event.', 'wp-ability' ),
			array(
				'type' => 'object',
				'properties' => array(
					'hook' => array(
						'type' => 'string',
						'minLength' => 1,
					),
					'timestamp' => array(
						'type' => 'integer',
						'minimum' => 1,
					),
					'args' => array(
						'type' => 'array',
						'default' => array(),
					),
				),
				'required' => array( 'hook', 'timestamp' ),
				'additionalProperties' => false,
			),
			array( $this, 'cron_delete' ),
			array( $this, 'can_manage_options' ),
			false,
			true,
			true
		);
	}

	/**
	 * List scheduled WordPress cron events.
	 *
	 * @return array
	 */
	public function cron_list() {
		$cron   = _get_cron_array();
		$result = array();
		foreach ( $cron as $timestamp => $hooks ) {
			foreach ( $hooks as $hook => $events ) {
				foreach ( $events as $event ) {
					$result[] = array(
						'hook'       => $hook,
						'timestamp'  => (int) $timestamp,
						'schedule'   => isset( $event['schedule'] ) ? $event['schedule'] : false,
						'args'       => isset( $event['args'] ) ? $event['args'] : array(),
						'interval'   => isset( $event['interval'] ) ? (int) $event['interval'] : null,
					);
				}
			}
		}
		return array( 'events' => $result );
	}

	/**
	 * Execute the cron schedule ability.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public function cron_schedule( array $input ) {
		$hook       = sanitize_key( $input['hook'] );
		$timestamp  = (int) $input['timestamp'];
		$args       = isset( $input['args'] ) && is_array( $input['args'] ) ? $input['args'] : array();
		$recurrence = isset( $input['recurrence'] ) && null !== $input['recurrence'] ? sanitize_key( $input['recurrence'] ) : null;
		if ( $recurrence ) {
			$result = wp_schedule_event( $timestamp, $recurrence, $hook, $args, true );
		} else {
			$result = wp_schedule_single_event( $timestamp, $hook, $args, true );
		}
		return is_wp_error( $result ) ? $result : array(
			'hook' => $hook,
			'timestamp' => $timestamp,
			'recurrence' => $recurrence,
			'scheduled' => (bool) $result,
		);
	}

	/**
	 * Execute the cron delete ability.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public function cron_delete( array $input ) {
		$hook      = sanitize_key( $input['hook'] );
		$timestamp = (int) $input['timestamp'];
		$args      = isset( $input['args'] ) && is_array( $input['args'] ) ? $input['args'] : array();
		$result    = wp_unschedule_event( $timestamp, $hook, $args, true );
		return is_wp_error( $result ) ? $result : array(
			'hook' => $hook,
			'timestamp' => $timestamp,
			'deleted' => (bool) $result,
		);
	}

	/**
	 * Register transient abilities.
	 *
	 * @return void
	 */
	private function register_transients() {
		$name_schema = array(
			'type' => 'object',
			'properties' => array(
				'name' => array(
					'type' => 'string',
					'minLength' => 1,
				),
			),
			'required' => array( 'name' ),
			'additionalProperties' => false,
		);
		$this->register( 'wordpress/transient-get', __( 'Get WordPress Transient', 'wp-ability' ), __( 'Gets one site-local WordPress transient.', 'wp-ability' ), $name_schema, array( $this, 'transient_get' ), array( $this, 'can_manage_options' ), true, false, true );
		$this->register(
			'wordpress/transient-set',
			__( 'Set WordPress Transient', 'wp-ability' ),
			__( 'Sets one site-local WordPress transient with optional expiration.', 'wp-ability' ),
			array(
				'type' => 'object',
				'properties' => array(
					'name' => array(
						'type' => 'string',
						'minLength' => 1,
					),
					'value' => array(),
					'expiration' => array(
						'type' => 'integer',
						'minimum' => 0,
						'default' => 0,
					),
				),
				'required' => array( 'name', 'value' ),
				'additionalProperties' => false,
			),
			array( $this, 'transient_set' ),
			array( $this, 'can_manage_options' ),
			false,
			false,
			true
		);
		$this->register( 'wordpress/transient-delete', __( 'Delete WordPress Transient', 'wp-ability' ), __( 'Deletes one site-local WordPress transient.', 'wp-ability' ), $name_schema, array( $this, 'transient_delete' ), array( $this, 'can_manage_options' ), false, true, true );
	}

	/**
	 * Execute the transient get ability.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public function transient_get( array $input ) {
		$name  = sanitize_key( $input['name'] );
		$value = get_transient( $name );
		return array(
			'name' => $name,
			'exists' => false !== $value,
			'value' => false === $value ? null : $value,
		);
	}

	/**
	 * Execute the transient set ability.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public function transient_set( array $input ) {
		$name       = sanitize_key( $input['name'] );
		$expiration = isset( $input['expiration'] ) ? max( 0, (int) $input['expiration'] ) : 0;
		$result     = set_transient( $name, $input['value'], $expiration );
		return array(
			'name' => $name,
			'set' => (bool) $result,
			'expiration' => $expiration,
		);
	}

	/**
	 * Execute the transient delete ability.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public function transient_delete( array $input ) {
		$name = sanitize_key( $input['name'] );
		return array(
			'name' => $name,
			'deleted' => (bool) delete_transient( $name ),
		);
	}
}
