<?php
/**
 * Optional GitHub self-update integration.
 *
 * @package WordPress_Abilities_Bridge
 */

namespace WP_Ability;

defined( 'ABSPATH' ) || exit;

/**
 * Adds GitHub Releases to the normal WordPress plugin update transient.
 */
final class GitHub_Self_Updater {

	const API_URL = 'https://api.github.com/repos/wachiravit-thitagran/WordPress-Abilities-Bridge/releases/latest';
	const PLUGIN_FILE = 'wp-ability/wp-ability.php';

	/**
	 * HTTP callback.
	 *
	 * @var callable|null
	 */
	private $http_get;

	/**
	 * Constructor.
	 *
	 * @param callable|null $http_get Optional HTTP callback for tests.
	 */
	public function __construct( $http_get = null ) {
		$this->http_get = $http_get;
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'inject_update' ) );
		add_filter( 'upgrader_pre_download', array( $this, 'verify_update_download' ), 10, 4 );
	}

	/**
	 * Whether GitHub update checks are enabled.
	 *
	 * Disabled by default so hosts that block outbound GitHub traffic incur
	 * no timeout or failure during normal WordPress update checks.
	 *
	 * @return bool
	 */
	public function is_enabled() {
		$enabled = defined( 'WP_ABILITY_GITHUB_UPDATES' ) ? (bool) WP_ABILITY_GITHUB_UPDATES : false;
		return (bool) apply_filters( 'wp_ability_github_updates_enabled', $enabled );
	}

	/**
	 * Inject a newer GitHub release into Core plugin update metadata.
	 *
	 * @param mixed $transient Update transient.
	 * @return mixed
	 */
	public function inject_update( $transient ) {
		if ( ! $this->is_enabled() || ! is_object( $transient ) ) {
			return $transient;
		}

		$response = is_callable( $this->http_get )
			? call_user_func( $this->http_get, self::API_URL )
			: wp_remote_get(
				self::API_URL,
				array(
					'timeout' => 3,
					'headers' => array(
						'Accept'     => 'application/vnd.github+json',
						'User-Agent' => 'WordPress-Abilities-Bridge',
					),
				)
			);

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return $transient;
		}

		$release = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $release ) || empty( $release['tag_name'] ) || empty( $release['assets'] ) || ! is_array( $release['assets'] ) ) {
			return $transient;
		}

		$version = ltrim( sanitize_text_field( $release['tag_name'] ), 'vV' );
		$current = isset( $transient->checked[ self::PLUGIN_FILE ] )
			? (string) $transient->checked[ self::PLUGIN_FILE ]
			: ( defined( 'WP_ABILITY_VERSION' ) ? WP_ABILITY_VERSION : '0.0.0' );

		if ( ! version_compare( $version, $current, '>' ) ) {
			return $transient;
		}

		$asset = null;
		foreach ( $release['assets'] as $candidate ) {
			if ( isset( $candidate['name'] ) && 'wp-ability-' . $version . '.zip' === $candidate['name'] ) {
				$asset = $candidate;
				break;
			}
		}
		if ( ! is_array( $asset ) || empty( $asset['browser_download_url'] ) ) {
			return $transient;
		}

		$sha256 = '';
		if ( ! empty( $asset['digest'] ) && 0 === strpos( $asset['digest'], 'sha256:' ) ) {
			$sha256 = strtolower( substr( $asset['digest'], 7 ) );
		}

		if ( 1 !== preg_match( '/^[a-f0-9]{64}$/', $sha256 ) ) {
			return $transient;
		}

		if ( ! isset( $transient->response ) || ! is_array( $transient->response ) ) {
			$transient->response = array();
		}

		$transient->response[ self::PLUGIN_FILE ] = (object) array(
			'id'                    => 'https://github.com/wachiravit-thitagran/WordPress-Abilities-Bridge',
			'slug'                  => 'wp-ability',
			'plugin'                => self::PLUGIN_FILE,
			'new_version'           => $version,
			'url'                   => isset( $release['html_url'] ) ? esc_url_raw( $release['html_url'] ) : '',
			'package'               => esc_url_raw( $asset['browser_download_url'] ),
			'wp_ability_sha256'     => $sha256,
		);

		return $transient;
	}

	/**
	 * Enforce a GitHub release SHA-256 on the native Core plugin update path.
	 *
	 * This guard only acts when the update transient for this plugin contains
	 * the private checksum metadata injected by this updater. Other update
	 * providers remain untouched.
	 *
	 * @param mixed  $reply Existing pre-download result.
	 * @param string $package Package URL.
	 * @param mixed  $upgrader Upgrader instance.
	 * @param array  $hook_extra Upgrader context.
	 * @return mixed
	 */
	public function verify_update_download( $reply, $package, $upgrader, $hook_extra ) {
		if ( false !== $reply || ! is_array( $hook_extra ) ) {
			return $reply;
		}

		if (
			self::PLUGIN_FILE !== ( isset( $hook_extra['plugin'] ) ? $hook_extra['plugin'] : '' )
			|| 'plugin' !== ( isset( $hook_extra['type'] ) ? $hook_extra['type'] : '' )
			|| 'update' !== ( isset( $hook_extra['action'] ) ? $hook_extra['action'] : '' )
		) {
			return $reply;
		}

		$transient = get_site_transient( 'update_plugins' );
		if ( ! is_object( $transient ) || ! isset( $transient->response[ self::PLUGIN_FILE ] ) ) {
			return $reply;
		}

		$update = $transient->response[ self::PLUGIN_FILE ];
		if ( is_object( $update ) ) {
			$checksum = isset( $update->wp_ability_sha256 ) ? $update->wp_ability_sha256 : '';
		} elseif ( is_array( $update ) ) {
			$checksum = isset( $update['wp_ability_sha256'] ) ? $update['wp_ability_sha256'] : '';
		} else {
			return $reply;
		}

		if ( '' === (string) $checksum ) {
			return $reply;
		}

		$verifier = new Package_Integrity_Verifier( $checksum );
		return $verifier->verify( $reply, $package, $upgrader, $hook_extra );
	}

}
