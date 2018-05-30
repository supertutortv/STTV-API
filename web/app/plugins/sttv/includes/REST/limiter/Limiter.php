<?php
namespace STTV\REST;

if ( ! defined( 'ABSPATH' ) ) {exit;}

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
/**
 * Main limiter class.
 *
 * @package STTV\REST
 * @since   1.0.0
 */
class Limiter {
	/**
	 * Prefix/namespace to prepend to cache keys.
	 *
	 * @since 1.4
	 * @var string
	 */
	private $prefix = 'sttvrest';
	/**
	 * Number of requests allowed per interval.
	 *
	 * @since 1.4
	 * @var integer
	 */
	private $limit;
	/**
	 * Seconds per interval.
	 *
	 * @since 1.4
	 * @var integer
	 */
	private $interval;
    /**
	 * Whitelisted IP addresses.
	 *
	 * @since 1.4
	 * @var array
	 */
	private $allow = [];
	/**
	 * Blacklisted IP addresses.
	 *
	 * @since 1.4
	 * @var array
	 */
	private $deny = [];
	/**
	 * Number of remaining ticks allowed in the interval.
	 *
	 * @since 1.4
	 * @var int
	 */
	private $remaining;
	/**
	 * The interval start time.
	 *
	 * @since 1.4
	 * @var int
	 */
	private $start;
	/**
	 * The client ID.
	 *
	 * @since 1.4
	 * @var string
	 */
	private $id;
	/**
     * 
	 * Constructor method.
     * 
     *  @since 1.4
	 */
	public function __construct() {
		$this->initialize_ip_rules();
	}
	/**
	 * Load the limiter.
	 *
	 * @since 1.4
	 *
	 * @return $this
	 */
	public function load( $limit = 100, $interval = 900 ) {
		$this
			->set_rate_limit( $limit )
			->set_interval( $interval )
			->set_client_id();

		add_filter( 'rest_authentication_errors', [ $this, 'check_ip_rules' ] );
		add_filter( 'rest_pre_dispatch',          [ $this, 'throttle_request' ], 10, 3 );
		return $this;
	}
	/**
	 * Global request handler.
	 *
	 * @since 1.4
	 *
	 * @param  mixed            $response Response.
	 * @param  \WP_REST_Server  $server   Server instance.
	 * @param  \WP_REST_Request $request  Request used to generate the response.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function throttle_request( $response, WP_REST_Server $server, WP_REST_Request $request ) {
		// No limiting if the IP address is whitelisted or the request is from an Admin
		if ( $this->is_allowed( $this->get_ip_address() ) || current_user_can( 'manage_options' ) ) {
			return $response;
		}

		// read the array from cache, or create it if it doesn't exist, then update object properties
		$limiter = get_transient( $this->get_cache_key() );
		if ( false === $limiter ) {
			$limiter = [
				'ip' => $this->get_ip_address(),
				'remaining' => $this->get_rate_limit(),
				'limited' => 0,
				'last_limited' => 0,
				'start' => time()
			];
		}
		$this->remaining = $limiter['remaining'];
		$this->start = $limiter['start'];

		// Don't throttle HEAD requests so folks can still get header info.
		if ( 'HEAD' !== $request->get_method() ) {
			$this->counter();
		}

		$limiter['remaining'] = $this->remaining;
		$limiter['reset'] = $this->get_reset();

		if ( $this->is_limit_exceeded() ) {
			if ( $this->remaining === -1) {
				$limiter['limited']++;
			}
			if ( time() - $this->interval > $limiter['last_limited'] ) {
				$limiter['last_limited'] = time();
			}
			if ( $limiter['limited'] > 4 ) {
				$this->update_ip_rules( $this->get_ip_address() );
			}
			$data = [
				'code'    => 'rate_limit_exceeded',
				'message' => 'Too many requests.',
				'data'    => [ 
					'status' => 429
				]
			];
			$response = new WP_REST_Response( $data, 429 );
		}

		if ( time() > $limiter['reset'] ){
			$limiter['start'] = time();
			$limiter['remaining'] = $this->remaining = $this->get_rate_limit();
		}

		$server->send_headers( $this->get_headers( $limiter['limited'] ) );
		
		// save new cached limits
		set_transient(
			$this->get_cache_key(),
			$limiter,
			DAY_IN_SECONDS
		);

		return $response;
	}
	/**
	 * Checks if IP is blacklisted and throws error if it is.
	 *
	 * @since 1.4
	 *
	 * @param  WP_Error|null|boolean $error WP_Error if authentication error, null if
	 *                                      authentication method wasn't used, true if
	 *                                      authentication succeeded.
	 * @return null|WP_Error
	 */
	public function check_ip_rules( $error ) {
		if ( $this->is_denied( $this->get_ip_address() ) ) {
			$error = $this->get_forbidden_error();
		}
		return $error;
	}
	/**
	 * Updates IP rules on invocation.
	 *
	 * @since 1.4
	 *
	 * @param string|boolean $deny = pass IP address to blacklist
	 * @param string|boolean $allow = pass IP address to whitelist
	 * 
	 * @return null|WP_Error
	 */
	private function update_ip_rules( $deny = false, $allow = false ) {
		if ( false !== $deny ) {
			$this->deny( $deny );
			update_option( $this->prefix.'_denied_ips', $this->get_denied() );
		}
		if ( false !== $allow ) {
			$this->allow( $allow );
			update_option( $this->prefix.'_allowed_ips', $this->get_allowed() );
		}
		return $this;
	}
	/**
	 * Initialize IP rules from options.
	 *
	 * @since 1.4
	 *
	 * @return $this
	 */
	private function initialize_ip_rules() {
		$allow = get_option( $this->prefix.'_allowed_ips' );
		if ( false === $allow ) {
			update_option( $this->prefix.'_allowed_ips', [] );
		}
		$deny = get_option( $this->prefix.'_denied_ips' );
		if ( false === $deny ) {
			update_option( $this->prefix.'_denied_ips', [] );
		}
		$this->allow( $allow );
		$this->deny( $deny );
		return $this;
	}
	/**
	 * Retrieve rate limit headers.
	 *
	 * @since 1.4
	 *
	 * @return array
	 */
	private function get_headers( $val = false ) {
		$headers = [
			'X-RateLimit-Limit'     => $this->get_rate_limit(),
			'X-RateLimit-Remaining' => $this->get_remaining()
		];
		if ( false !== $val ) {
			$headers['X-Custom-Key'] = $val;
		}
		if ( $this->is_limit_exceeded() ) {
			$headers['Retry-After']           = date( 'c', $this->get_reset() );
			$headers['X-RateLimit-Remaining'] = 0;
		} else {
			$headers['X-RateLimit-Reset'] = date( 'c', $this->get_reset() );
		}
		return $headers;
	}
	/**
	 * Retrieve the default forbidden error.
	 *
	 * @since 1.4
	 *
	 * @return WP_Error
	 */
	private function get_forbidden_error() {
		return new WP_Error(
			'rest_forbidden',
			'You have abused your privileges, and therefore are blacklisted. Please contact our web dev team with the \'help_key\' below if you feel you\'ve reached this message in error.',
			[ 'status' => 403, 'help_key' => base64_encode($this->get_cache_key()) ]
		);
	}
	/**
	 * Update the counter.
	 *
	 * @since 1.4
	 *
	 * @return $this
	 */
	private function counter() {
		$this->remaining -= 1;
		return $this;
	}
	/**
	 * Retrieve the number of seconds until the meter resets.
	 *
	 * @since 1.4
	 *
	 * @return int
	 */
	private function get_reset() {
		return $this->start + $this->interval;
	}
	/**
	 * Retrieve a key to use for storage.
	 *
	 * @since 1.4
	 *
	 * @return string
	 */
	private function get_cache_key() {
		return $this->prefix . ':limiter:' . $this->get_client_id();
	}
	/**
	 * Retrieve the current client's IP address.
	 *
	 * @since 1.4
	 *
	 * @return string
	 */
	private function get_ip_address() {
		return $_SERVER['REMOTE_ADDR'];
	}
	/**
	 * Retrieve the number of remaining requests.
	 *
	 * @since 1.4
	 *
	 * @return int
	 */
	private function get_remaining() {
		return $this->remaining;
	}
	/**
	 * Retrieve the global rate limit.
	 *
	 * @since 1.4
	 *
	 * @return int
	 */
	private function get_rate_limit() {
		return $this->limit;
	}
	/**
	 * Set the number of requests allowed per interval.
	 *
	 * @since 1.4
	 *
	 * @param  int $limit Number of requests.
	 * @return $this
	 */
	private function set_rate_limit( $limit ) {
		$this->limit = intval( $limit );
		return $this;
	}
	/**
	 * Retrieve the global rate limit interval.
	 *
	 * @since 1.4
	 *
	 * @return int
	 */
	private function get_interval() {
		return $this->interval;
	}
	/**
	 * Set the number of seconds per interval.
	 *
	 * @since 1.4
	 *
	 * @param  int $interval Seconds per interval.
	 * @return $this
	 */
	private function set_interval( $interval ) {
		$this->interval = intval( $interval );
		return $this;
	}
	/**
	 * Retrieve an identifier for the current client.
	 *
	 * If a user is logged in, their user ID will be used, otherwise, defaults
	 * to the current client's IP address.
	 *
	 * @since 1.4
	 *
	 * @return string|int
	 */
	private function get_client_id() {
		return $this->id;
	}
	/**
	 * Set the ID of current client.
	 *
	 * If a user is logged in, their user ID will be used, otherwise, defaults
	 * to the current client's IP address.
	 *
	 * @since 1.4
	 *
	 * @return string|int
	 */
	private function set_client_id() {
		$this->id = ( is_user_logged_in() ) ? get_current_user_id() : $this->get_ip_address();
		return $this;
	}
	/**
	 * Whether an IP address passes allowed and denied checks.
	 *
	 * @since 1.4
	 *
	 * @param  string $ip IP address to test.
	 * @return boolean
	 */
	private function check( $ip ) {
		return $this->is_allowed( $ip ) || $this->is_denied( $ip );
	}
	/**
	 * Whitelist one or more IP addresses.
	 *
	 * @since 1.4
	 *
	 * @param  string $ip IP address(es).
	 * @return $this
	 */
	private function allow( $ip ) {
		$this->allow = array_filter( array_merge( $this->allow, (array) $ip ) );
		return $this;
	}
	/**
	 * Blacklist one or more IP addresses.
	 *
	 * @since 1.4
	 * @param  string $ip IP address(es).
	 * @return $this
	 */
	private function deny( $ip ) {
		$this->deny = array_filter( array_merge( $this->deny, (array) $ip ) );
		return $this;
	}
    /**
	 * Retrieve allowed IP addresses.
	 *
	 * @since 1.4
	 *
	 * @return array
	 */
	private function get_allowed() {
		return $this->allow;
	}
	/**
	 * Retrieve denied IP addresses.
	 *
	 * @since 1.4
	 *
	 * @return array
	 */
	private function get_denied() {
		return $this->deny;
	}
	/**
	 * Whether an IP address is allowed.
	 *
	 * @since 1.4
	 *
	 * @param  string $ip IP address to test.
	 * @return boolean
	 */
	private function is_allowed( $ip ) {
		return in_array( $ip, $this->allow );
	}
	/**
	 * Whether an IP address is denied.
	 *
	 * @since 1.4
	 *
	 * @param  string $ip IP address to test.
	 * @return boolean
	 */
	private function is_denied( $ip ) {
		return in_array( $ip, $this->deny );
	}
	/**
	 * Whether the limit has been exceeded.
	 *
	 * @since 1.4
	 *
	 * @return bool
	 */
	private function is_limit_exceeded() {
		return 0 > $this->get_remaining();
	}
}