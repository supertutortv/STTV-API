<?php

namespace STTV\REST;

defined( 'ABSPATH' ) || exit;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * SupertutorTV Forms controller class.
 *
 * Properties, methods, routes, and endpoints for processing the forms on the SupertutorTV website.
 * This includes, for now, the login/logout process, contact form, and email list subscription form.
 *
 * @class 		Forms
 * @version		2.0.0
 * @package		STTV
 * @category	Class
 * @author		Supertutor Media, inc.
 */
class Forms extends \WP_REST_Controller {

    public function __construct() {
        
    }

    public function register_routes() {
        register_rest_route( STTV_REST_NAMESPACE , '/contact', [
            [
                'methods' => 'POST',
                'callback' => [ $this, 'sttv_contact_form_processor' ],
                'permission_callback' => [ $this, 'verify_form_submit' ]
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

    public function verify_form_submit( WP_REST_Request $request ){
        $token = json_decode($request->get_body(),true);
        $token = $token['g_recaptcha_response'];

        if ( empty($token) ) {
            return new \WP_Error( 'no_recaptcha', 'Shoo bot, shoo!', [ 'status' => 403 ] );
        }
        $response = json_decode(file_get_contents("https://www.google.com/recaptcha/api/siteverify?secret=".RECAPTCHA_SECRET."&response=".$token."&remoteip=".$_SERVER['REMOTE_ADDR']),true);
        return $response['success'] ?: new \WP_Error( 'recaptcha_failed', 'Shoo bot, shoo!', [ 'status' => 403 ] );
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
        return new \WP_REST_Response( $data, $status );
    }

}