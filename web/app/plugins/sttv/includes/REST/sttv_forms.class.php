<?php
if ( ! defined( 'ABSPATH' ) ) exit;
/**
 * SupertutorTV Forms handler class.
 *
 * Properties, methods, and endpoints for processing the forms on the SupertutorTV website.
 * This includes, for now, the login/logout process, contact form, and email list subscription form.
 *
 * @class 		STTV_Forms
 * @version		1.4.0
 * @package		STTV
 * @category	Class
 * @author		Supertutor Media, inc.
 */
class STTV_Forms extends WP_REST_Controller {

    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_forms_endpoints' ] );
    }

    public function register_forms_endpoints() {
        register_rest_route( STTV_REST_NAMESPACE , '/contact', [
            [
                'methods' => 'POST',
                'callback' => [ $this, 'sttv_contact_form_processor' ],
                'permission_callback' => [ $this, 'verify_form_submit' ]
            ]
        ]);

        register_rest_route( STTV_REST_NAMESPACE , '/auth', [
            [
                'methods' => 'GET',
                'callback' => [ $this, 'sttv_auth_form' ],
                'permission_callback' => 'sttv_verify_rest_nonce'
            ],
            [
                'methods' => 'POST',
                'callback' => [ $this, 'sttv_auth_processor' ],
                'args' => [
                    'action' => [
                        'required' => true,
                        'type' => 'string',
                        'description' => 'The type of auth action requested'
                    ]
                ]
            ]
        ]);

        register_rest_route( STTV_REST_NAMESPACE , '/subscribe', [
            [
                'methods' => 'POST',
                'callback' => [ $this, 'sttv_subscribe_processor' ],
                'permission_callback' => [ $this, 'verify_form_submit' ]
            ]
        ]);
    }

    public function sttv_contact_form_processor( WP_REST_Request $request ) {
        $body = json_decode($request->get_body(),true);
        $sentmail = wp_mail(
            get_bloginfo('admin_email'),
            $body['sttv_contact_subject'],
            $body['sttv_contact_message'],
            [
                'Content-Type: text/html; charset=UTF-8',
                'From: '.$body['sttv_contact_name'].' <'.$body['sttv_contact_email'].'>',
                'Sender: SupertutorTV Website <info@supertutortv.com>',
                'Bcc: David Paul <dave@supertutortv.com>'
            ]
        );
        
        if ($sentmail) {
            return $this->forms_generic_response( 'contact_form_success', 'Thanks for contacting us! We\'ll get back to you ASAP!', 200, [ 'sent' => $sentmail ] );
        } else {
            return $this->forms_generic_response( 'contact_form_fail', 'There was an issue sending your message. Please try again later.', 200, [ 'sent' => $sentmail ] );
        }

    }

    public function sttv_auth_processor( WP_REST_Request $request ) {
        $action = $request->get_param('action');
        $auth = $request->get_header('X-STTV-Auth');

        switch ($action) {
            case 'login':
                return $this->login($auth,site_url());

            case 'logout':
                return $this->logout(site_url());

            default:
                return $this->forms_generic_response( 'action_invalid', 'The action parameter was invalid. Check documentation for allowed actions.', 400 );
        }
    }

    public function sttv_auth_form() {
        ob_start();
        sttv_get_template('_authform','auth');
        return ob_get_clean();
    }

    public function sttv_subscribe_processor( WP_REST_Request $request ) {
        $body = json_decode($request->get_body(),true);
        
        // use sttv API mailinglist function
        $response = sttv_mailinglist_subscribe( $body['email'], $body['fname'], $body['lname'] );

        if ( is_wp_error($response) ){
            return $this->forms_generic_response( 'sub_error', 'There was an error subscribing you to our list. Please try again later.', 400, ['response'=>$response] );
        } else {
            return $this->forms_generic_response( 'sub_success', '<h1 style="display:block">Success!</h1> Thank you for subscribing to SupertutorTV!', 200, ['response'=>$response] );
        }
    }

    private function login( $auth, $redirect = '' ) {
        if ( null === $auth || empty($auth) ) {
            return $this->forms_generic_response( 'auth_header', 'You must set the authentication header X-STTV-Auth', 400 );
        }

        $auth = explode( ':', base64_decode($auth) );

        // username validation
        $user = sanitize_user($auth[0]);
        if (!validate_username($user)){
            return $this->forms_generic_response( 'login_fail', '<strong>ERROR: </strong>The username or password you entered is incorrect', 401 );
        }

        unset($auth[0]);
        $pw = implode( ':', $auth);

        $login = wp_signon([
            'user_login' => $user,
            'user_password' => $pw,
            'remember' => true
        ], true);

        if ( !is_wp_error( $login ) ){
            unset($login->data->user_pass,$login->data->user_activation_key);
            return $this->forms_generic_response( 'login_success', 'Login successful! Redirecting...', 200, [ 'data' => $login->data, 'redirect' => $redirect ] );
        } else {
            return $this->forms_generic_response( 'login_fail', '<strong>ERROR: </strong>The username or password you entered is incorrect', 401, $login );
        }
    }

    private function logout( $redirect = '' ){
        wp_logout();
        return $redirect;
    }

    public function verify_form_submit( WP_REST_Request $request ){
        $token = json_decode($request->get_body(),true);
        $token = $token['g_recaptcha_response'];

        if ( empty($token) ) {
            return new WP_Error( 'no_recaptcha', 'Shoo bot, shoo!', [ 'status' => 403 ] );
        }
        $response = json_decode(file_get_contents("https://www.google.com/recaptcha/api/siteverify?secret=".RECAPTCHA_SECRET."&response=".$token."&remoteip=".$_SERVER['REMOTE_ADDR']),true);
        return $response['success'] ?: new WP_Error( 'recaptcha_failed', 'Shoo bot, shoo!', [ 'status' => 403 ] );
    }

    private function forms_generic_response( $code = '', $msg = '', $status = 200, $extra = [] ) {
        $data = [
            'code'    => $code,
            'message' => $msg,
            'data'    => [ 
                'status' => $status
            ]
        ];
        $data = array_merge($data, (array) $extra);
        return new WP_REST_Response( $data, $status );
    }

}
new STTV_Forms;