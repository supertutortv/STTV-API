<?php

namespace STTV\REST;

defined( 'ABSPATH' ) || exit;

class API {

    public $limiter = null;

    public function __construct() {
        if ( STTV_REST_AUTH !== 'wp_rest' ) {
            add_filter( 'rest_nonce_action', function() {
                return STTV_REST_AUTH;
            });
        }

        add_filter( 'rest_url_prefix', function() {
            return STTV_REST_NAMESPACE;
        }, 11 );

        remove_action( 'wp_head', 'rest_output_link_wp_head', 10 );
        remove_action( 'template_redirect', 'rest_output_link_header', 11, 0 );

        $this->rest_includes();
        $this->init();
    }

    private function init() {
        $limiter = $this->limiter();
        add_action( 'rest_api_init', function() use ( $limiter ) {
            $limiter->load();
        }, 5 );
        add_action( 'rest_api_init', [ $this, 'register_rest_routes' ], 10 );
        add_action( 'rest_api_init', [ $this, 'sttv_rest_cors' ], 15 );
    }

    public function sttv_rest_cors() {
    
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
            header( 'Access-Control-Allow-Headers', 'Content-Type, Access-Control-Allow-Headers, Authorization, X-WP-Nonce, X-STTV-Auth, X-STTV-WHSEC' );
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

    private function rest_includes() {
        $path = dirname( __DIR__ ) . '/REST/';

        require_once $path . 'class-sttv-rest-limiter.php';
        require_once $path . 'class-sttv-rest-checkout.php';
        require_once $path . 'class-sttv-rest-multiuser.php';
        require_once $path . 'class-sttv-rest-courses.php';
        require_once $path . 'class-sttv-rest-forms.php';

    }

    public function register_rest_routes() {
        $controllers = [
            'STTV\REST\Checkout',
            'STTV\REST\MultiUser',
            'STTV\REST\Courses',
            'STTV\REST\Forms',
        ];

        foreach ( $controllers as $controller ) {
			$this->$controller = new $controller();
			$this->$controller->register_routes();
		}
    }

    private function limiter() {
        static $instance;
        if ( null === $instance ) {
            $instance = new Limiter();
        }
        return $instance;
    }
}