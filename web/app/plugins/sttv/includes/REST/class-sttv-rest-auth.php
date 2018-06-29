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
 * Currently only handles login/logout, but will in future releases handle JSON web tokenization.
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
            '/token/verify' => [
                [
                    'methods' => 'POST',
                    'permission_callback' => 'sttv_verify_web_token',
                    'callback' => [ $this, 'verify' ]
                ]
            ],
            '/logout' => [
                [
                    'methods' => 'POST',
                    'callback' => [ $this, 'logout' ],
                    'permission_callback' => 'sttv_verify_web_token'
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
                200
            );

        $token = new \STTV\JWT( $login );
        $token2 = new \STTV\JWT( 'Bearer '.$token->token );
        return print_r($token2);

        //do_action( 'wp_login' );
        
        return sttv_rest_response(
            'login_success',
            'Login successful!',
            200,
            [ 'token' => $token->token ]
        );
    }

    public function verify() {
        return sttv_rest_response(
            'token_verified',
            'You provided a valid JSON web token to the API.',
            200
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
        $times = get_user_meta( $user->ID, 'sttv_user_data', true );
        $times['login_timestamps'][] = time();
        update_user_meta( $user->ID, 'sttv_user_data', $times );
    }
}