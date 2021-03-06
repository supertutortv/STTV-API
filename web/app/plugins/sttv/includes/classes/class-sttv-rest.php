<?php

namespace STTV\REST;

defined( 'ABSPATH' ) || exit;

class API {

    public $limiter = null;

    private $allowed_origins = [
        'https://supertutortv.com',
        'https://dev.courses.supertutortv.com',
        'https://courses.supertutortv.com',
        'https://api.supertutortv.com'
    ];

    private $origin;

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

        $this->origin = get_http_origin();

        $this->rest_includes();
        $this->init();
    }

    private function init() {
        $limiter = $this->limiter();
        add_action( 'rest_api_init', function() use ( $limiter ) {
            $limiter->load();
        }, 5 );
        add_action( 'rest_api_init', [ $this, 'sttv_rest_cors' ], 10 );
        add_action( 'rest_api_init', [ $this, 'register_rest_routes' ], 11 );
    }

    public function sttv_rest_cors() {
    
        remove_filter( 'rest_pre_serve_request', 'rest_send_cors_headers' );
        add_filter( 'rest_pre_serve_request', function( $value ) {

            //remove default headers
            header_remove( 'Access-Control-Expose-Headers' );
            header_remove( 'Access-Control-Allow-Headers' );
            header_remove( 'Link' );
            header_remove( 'X-Powered-By' );
            header_remove( 'X-Robots-Tag' );

            if ( in_array( $this->origin, $this->allowed_origins ) ) {
                header( 'Access-Control-Allow-Origin: ' . $this->origin );
            } else {
                header( 'Access-Control-Allow-Origin: ' . home_url() );
            }
            header( 'Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS, HEAD' );
            header( 'Access-Control-Allow-Credentials: true' );
            header( 'Access-Control-Allow-Headers: Accept, Referrer, Origin, Credentials, Content-Type, User-Agent, Access-Control-Allow-Headers, Authorization, X-WP-Nonce, X-STTV-Auth, X-STTV-WHSEC, X-RateLimit-Buster' );
            header( 'Content-Type: '.STTV_REST_CONTENT_TYPE );
            header( 'Host: ' . rest_url() );
            header( 'X-Origin-Verify: ' . $this->origin );
    
            return $value;
            
        });
    }

    private function rest_includes() {
        $path = dirname( __DIR__ ) . '/REST/';
        require_once $path . 'class-sttv-rest-limiter.php';
        require_once $path . 'class-sttv-rest-auth.php';
        require_once $path . 'class-sttv-rest-checkout.php';
        require_once $path . 'class-sttv-rest-signup.php';
        require_once $path . 'class-sttv-rest-forms.php';
        require_once $path . 'class-sttv-rest-migrate.php';
        
        //courses
        require_once $path . 'courses/class-sttv-rest-courses.php';
        require_once $path . 'courses/class-sttv-rest-notifications.php';
        require_once $path . 'courses/class-sttv-rest-feedback.php';
        require_once $path . 'courses/class-sttv-rest-reviews.php';
        require_once $path . 'courses/class-sttv-rest-multiuser.php';
    }

    public function register_rest_routes() {
        $controllers = [
            'STTV\REST\Auth',
            'STTV\REST\Checkout',
            'STTV\REST\Signup',
            'STTV\REST\Forms',
            'STTV\REST\Migrate',
            'STTV\REST\Courses',
            'STTV\REST\Courses\Notifications',
            'STTV\REST\Courses\Feedback',
            'STTV\REST\Courses\Reviews',
            'STTV\REST\Courses\MultiUser'
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