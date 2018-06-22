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
        // Log all logins to our API
        add_action( 'wp_login', [ $this, 'log_all_logins' ], 10, 2 );
    }

    public function register_routes() {
        $routes = [
            '/login' => [
                [
                    'methods' => 'POST',
                    'callback' => [ $this, 'login' ],
                    'permission_callback' => 'sttv_verify_rest_nonce'
                ]
            ],
            '/logout' => [
                [
                    'methods' => 'POST',
                    'callback' => [ $this, 'logout' ],
                    'permission_callback' => 'sttv_verify_rest_nonce'
                ]
            ]
        ];

        foreach ( $routes as $route => $endpoint ) {
			register_rest_route( 'auth', $route, $endpoint );
		}
    }

    public function login( WP_REST_Request $request ) {
        $auth = $request->get_header( 'Authorization' );
        return sttv_rest_response(
            'logged_in',
            'Login successful.',
            200,
            [ 'data' => $auth ]
        );
    }

    public function logout() {
        while (!!wp_validate_auth_cookie()) {
            wp_logout();
        }
        return sttv_rest_response(
            'logged_out',
            'Logout successful.',
            200
        );
    }

    public function log_all_logins( $username, $user ) {
        $times = get_user_meta( $user->ID, 'login_timestamps', true ) ?: ['SOR'];
        $times[] = time();
        update_user_meta( $user->ID, 'login_timestamps', $times );
    }
}