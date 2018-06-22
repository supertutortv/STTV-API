<?php

namespace STTV\REST;

defined( 'ABSPATH' ) || exit;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * SupertutorTV Auth controller class.
 *
 * Properties, methods, routes, and endpoints for processing and managing the authorization process on all SupertutorTV web applications.
 * Currently only handles login/lgout, but will in future releases handle JSON web tokenization.
 *
 * @class 		Auth
 * @version		2.0.0
 * @package		STTV
 * @category	Class
 * @author		Supertutor Media, inc.
 */
class Auth extends \WP_REST_Controller {

    public function __construct() {
        
    }

    public function register_routes() {
        $routes = [
            '/login' => [
                [
                    'methods' => 'POST',
                    'callback' => [ $this, 'auth_endpoint' ],
                    'permission_callback' => 'sttv_verify_rest_nonce'
                ]
            ],
            '/logout' => [
                [
                    'methods' => 'POST',
                    'callback' => [ $this, 'auth_endpoint' ],
                    'permission_callback' => 'sttv_verify_rest_nonce'
                ]
            ]
        ];

        foreach ( $routes as $route => $endpoint ) {
			register_rest_route( 'auth', $route, $endpoint );
		}
    }

    public function auth_endpoint( WP_REST_Request $request ) {
        $route = $request->get_route();
        return $route;
    }
}