<?php

namespace STTV\REST;

defined( 'ABSPATH' ) || exit;

use WP_Error;
use WP_User;
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
                    'methods' => 'GET',
                    'callback' => [ $this, 'verifyPwChange' ],
                    'args' => [
                        'key' => [
                            'required' => true,
                            'type' => 'string',
                            'description' => 'Password reset validation key'
                        ]
                    ]
                ],
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

        if (@$verify->ID > 0) \STTV\Log::access([
            'id' => $verify->ID,
            'email' => $verify->user_email,
            'type' => 'pageload'
        ]);
        
        return [ 'data' => !(is_wp_error($verify) || $verify->ID === 0) ];
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

        $umeta = get_user_meta($login->ID, 'sttv_user_data', true);

        $exp = (isset($umeta['user']['trialing']) && (time() < $umeta['user']['trialing']) && ($umeta['user']['trialing'] > 0)) ? $umeta['user']['trialing']-time() : DAY_IN_SECONDS*30 ;

        $token = new \STTV\JWT( $login, $exp );

        \STTV\Log::access([
            'id' => $login->ID,
            'email' => $login->user_email,
            'type' => 'login'
        ]);
        
        return sttv_rest_response(
            'loginSuccess',
            'Login successful!',
            200,
            [ 'token' => sttv_set_auth_cookie($token->token) ]
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
        $body = json_decode($request->get_body(),true);
        $email = $body['email'];

        /* $response = json_decode(file_get_contents("https://www.google.com/recaptcha/api/siteverify?secret=".RECAPTCHA_SECRET."&response=".$token."&remoteip=".$_SERVER['REMOTE_ADDR']),true); */

        $id = email_exists($email);

        if (!$id) return sttv_rest_response(
            'resetError',
            'That email address is not associated with a SupertutorTV account.',
            200
        );

        $user = wp_set_current_user($id);
        $link = wp_lostpassword_url().'/'.get_password_reset_key($user).'|'.$user->user_login;

        $message = new \STTV\Email\Standard([
            'to' => $email,
            'subject' => 'SupertutorTV account password reset',
            'message' => "Click the link below to reset your password.<br/><a href='$link'>$link</a><br/><br/><br/>If you didn't request a password reset, you can ignore this email or forward it on to us so we can document unauthorized requests. Thanks!<br/><br/>"
        ]);

        $message->send();

        return sttv_rest_response(
            'resetSuccess',
            'Check your email for a link to reset password.',
            200
        );
    }

    public function verifyPwChange( WP_REST_Request $request ) {
        list($key,$login) = explode('|',$request->get_param('key'),2);

        $check = check_password_reset_key($key,$login);
        return sttv_rest_response(
            (is_wp_error($check)) ? 'pwError' : 'pwSuccess',
            'Password check',
            200,
            $check
        );
    }

    public function changePw( WP_REST_Request $request ) {
        extract(json_decode($request->get_body(),true));

        if ($password1 !== $password2) {
            return sttv_rest_response(
                'resetError',
                'Passwords not equal',
                200
            );
        }

        list($key,$login) = explode('.',$key);

        $check = check_password_reset_key($key,$login);

        if (is_wp_error($check)) {
            return sttv_rest_response(
                'resetError',
                'Reset link invalid or expired',
                200
            );
        }
        
        wp_set_password($password2,$check->ID);
        return sttv_rest_response(
            'resetSuccess',
            'Your password has been reset. Please ',
            200
        );

    }
}