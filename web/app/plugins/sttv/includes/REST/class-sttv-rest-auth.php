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

    public function __construct() {}

    public function register_routes() {
        $routes = [
            '/form' => [
                [
                    'methods' => 'GET',
                    'callback' => [ $this, 'form' ]
                ]
            ],
            '/token' => [
                [
                    'methods' => 'POST',
                    'callback' => [ $this, 'token' ]
                ]
            ],
            '/token/verify' => [
                [
                    'methods' => 'POST',
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

    public function verify( WP_REST_Request $request ) {
        $verify = sttv_verify_web_token($request);
        return [ 'data' => is_wp_error($verify) ? !$verify : $verify ];
    }

    public function token( WP_REST_Request $request ) {
        // get username and password from request
        $body = json_decode($request->get_body(),true);

        // attempt login
        $login = wp_authenticate( $body['username'], $body['password'] );

        // checks if WP_Error is thrown after login attempt
        if ( is_wp_error( $login ) )
            return sttv_rest_response(
                'login_fail',
                'The username or password you entered is incorrect',
                200,
                [ 'err' => $login ]
            );

        $token = new \STTV\JWT( $login );

        sttv_set_auth_cookie($token->token);

        \STTV\Log::access([
            'id' => $login->ID,
            'email' => $login->user_email
        ]);
        $umeta = get_user_meta( $login->ID, 'sttv_user_data', true );
        $umeta['user']['userdata']['login_timestamps'][] = time();
        update_user_meta( $login->ID, 'sttv_user_data', $umeta );
        
        return sttv_rest_response(
            'login_success',
            'Login successful!',
            200,
            [ 'token' => $token->token ]
        );
    }

    public function form() {
        ob_start();
        include STTV_TEMPLATE_DIR.'login.php';

        return sttv_rest_response(
            'login_form',
            'Here\'s your form',
            200,
            [ 'form' => ob_get_clean() ]
        );
    }

    public function logout() {
        sttv_unset_auth_cookie();
        return sttv_rest_response(
            'logged_out',
            'Logout successful.',
            200,
            [ 'redirect' => 'https://supertutortv.com' ]
        );
    }
}