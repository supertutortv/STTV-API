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
            '/verify' => [
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
            ],
            '/reset' => [
                [
                    'methods' => 'POST',
                    'callback' => [ $this, 'requestPwChange']
                ],
                [
                    'methods' => 'PUT',
                    'callback' => [ $this, 'changePw' ]
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
                'loginFail',
                'The username or password you entered is incorrect',
                200,
                [ 'err' => $login ]
            );
        
        wp_set_current_user($login->ID);

        $token = new \STTV\JWT( $login );

        sttv_set_auth_cookie($token->token);

        \STTV\Log::access([
            'id' => $login->ID,
            'email' => $login->user_email
        ]);
        if ( !current_user_can('manage_options') ) {
            $umeta = get_user_meta( $login->ID, 'sttv_user_data', true ) ?: [];
            $umeta['user']['userdata']['login_timestamps'][] = time();
            update_user_meta( $login->ID, 'sttv_user_data', $umeta );
        }
        
        return sttv_rest_response(
            'loginSuccess',
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
            'loggedOut',
            'Logout successful.',
            200,
            [ 'redirect' => 'https://supertutortv.com' ]
        );
    }

    public function requestPwChange( WP_REST_Request $request ) {
        list($email,$token) = json_decode($request->get_body(),true);

        $response = json_decode(file_get_contents("https://www.google.com/recaptcha/api/siteverify?secret=".RECAPTCHA_SECRET."&response=".$token."&remoteip=".$_SERVER['REMOTE_ADDR']),true);

        $id = email_exists($email);

        if (!$id) return sttv_rest_response(
            'resetError',
            'That email address is not associated with a SupertutorTV account.',
            200
        );

        $user = new WP_User($id);
        $link = wp_lostpassword_url().'/'.get_password_reset_key($user).'.'.$user->user_login;

        $message = new \STTV\Email([
            'to' => $email,
            'subject' => 'SupertutorTV account password reset',
            'message' => "Click the link below to reset your password.<br/><a href='$link'>$link</a><br/><br/><br/>If you didn't request a password reset, you can ignore this email or forward it on to us so we can document unauthorized requests. Thanks!"
        ]);

        $message->send();

        return sttv_rest_response(
            'resetSuccess',
            'Check your email for a link to reset password.',
            200,
            $response
        );
    }

    public function changePw( WP_REST_Request $request ) {
        list($email,$token) = json_decode($request->get_body(),true);

    }
}