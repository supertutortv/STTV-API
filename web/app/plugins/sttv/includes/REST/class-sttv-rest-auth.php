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
            '/token' => [
                [
                    'methods' => 'POST',
                    'callback' => [ $this, 'token' ]
                ]
            ],
            '/logout' => [
                [
                    'methods' => 'POST',
                    'callback' => [ $this, 'logout' ],
                    //'permission_callback' => 'is_user_logged_in'
                    'permission_callback' => '__return_true'
                ]
            ]
        ];

        foreach ( $routes as $route => $endpoint ) {
			register_rest_route( 'auth', $route, $endpoint );
		}
    }

    public function token( WP_REST_Request $request ) {
        // get username and password from request
        $username = $request->get_param('username');
        $password = $request->get_param('password');

        // attempt login
        $login = wp_authenticate( $username, $password );

        // checks if WP_Error is thrown after login attempt
        if ( is_wp_error( $login ) )
            return sttv_rest_response(
                'login_fail',
                'The username or password you entered is incorrect',
                401
            );;

        $issued = time();
        $token = [
            'iss' => get_bloginfo('url'),
            'iat' => $issued,
            'nbf' => $issued,
            'exp' => $issued + (DAY_IN_SECONDS*5),
            'data' => [
                'user' => [
                    'id' => $login->data->ID
                ]
            ]
        ];
        
        return sttv_rest_response(
            'login_success',
            'Login successful!',
            200,
            [ 'token' => \STTV\JWT::generate( $token ) ]
        );
    }

    public function logout() {
        while (!!wp_validate_auth_cookie()) {
            wp_logout();
        }
        return sttv_rest_response(
            'logged_out',
            'Logout successful.',
            200,
            [ 'data' => [
                    'redirect' => 'https://supertutortv.com'
                ]
            ]
        );
    }

    public function log_all_logins( $username, $user ) {
        $times = get_user_meta( $user->ID, 'login_timestamps', true ) ?: ['SOR'];
        $times[] = time();
        update_user_meta( $user->ID, 'login_timestamps', $times );
    }
}