<?php 

if ( ! defined( 'ABSPATH' ) ) {exit;}

add_action( 'rest_api_init', 'sttv_rest_cors', 15 );
remove_action( 'wp_head', 'rest_output_link_wp_head', 10 );
remove_action( 'template_redirect', 'rest_output_link_header', 11, 0 );
if ( $this->restauth !== 'wp_rest' ) {
	add_filter( 'rest_nonce_action', function() {
		return $this->restauth;
	});
}
add_filter( 'rest_url_prefix', function() {
	return 'api';
} );

function sttv_rest_cors() {
    
	remove_filter( 'rest_pre_serve_request', 'rest_send_cors_headers' );
	add_filter( 'rest_pre_serve_request', function( $value ) {
		$origin = get_http_origin();
		if ( $origin ) {
			header( 'Access-Control-Allow-Origin: ' . esc_url_raw( $origin ) );
		} else {
			header( 'Access-Control-Allow-Origin: *' );
		}
		header( 'Access-Control-Allow-Methods: GET, OPTIONS' );
		header( 'Access-Control-Allow-Credentials: true' );
		//header( 'Access-Control-Allow-Headers', 'Content-Type, Access-Control-Allow-Headers, Authorization, X-WP-Nonce, X-STTV-Auth' );
		header( 'Content-Type: application/sttv.app.data+json' );
		header( 'Host: '.rest_url(STTV_REST_NAMESPACE) );

		//remove default headers
		header_remove( 'Access-Control-Expose-Headers' );
		header_remove( 'Link' );
		header_remove( 'X-Powered-By' );
		header_remove( 'X-Robots-Tag' );

		return $value;
		
	});
}

use STTV\REST\Limiter;

require( __DIR__ . '/limiter/Limiter.php' );

function sttvlimiter() {
	static $instance;
	if ( null === $instance ) {
		$instance = new Limiter();
	}
	return $instance;
}
$sttvlimiter = sttvlimiter();

add_action( 'rest_api_init', function() use ( $sttvlimiter ) {
	$sttvlimiter->load();
}, 5 );

require_once 'sttv_feedback.class.php';
require_once 'sttv_product_reviews.class.php';
require_once 'sttv_test_dates.class.php';
require_once 'sttv_jobs.class.php';
require_once 'sttv_forms.class.php';
require_once 'sttv_checkout.class.php';
require_once 'sttv_murest.class.php';