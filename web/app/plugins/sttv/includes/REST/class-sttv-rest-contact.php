<?php

namespace STTV\REST;

defined( 'ABSPATH' ) || exit;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * SupertutorTV Contact form controller class.
 *
 * Properties, methods, routes, and endpoints for processing the contact form on the SupertutorTV website.
 * This class also handles the 
 *
 * @class 		Contact
 * @version		2.0.0
 * @package		STTV
 * @category	Class
 * @author		Supertutor Media, inc.
 */
class Contact extends \WP_REST_Controller {

    public function __construct() {}

    public function register_routes() {

        $routes = [
            '/send' => [
                [
                    'methods' => 'POST',
                    'callback' => [ $this, 'contact_form_processor' ],
                    'permission_callback' => [ $this, 'verify_form_submit' ]
                ]
            ],
            '/subscribe' => [
                [
                    'methods' => 'POST',
                    'callback' => [ $this, 'sttv_subscribe_processor' ],
                    'permission_callback' => [ $this, 'verify_form_submit' ]
                ]
            ]
        ];

        foreach ( $routes as $route => $endpoint ) {
			register_rest_route( 'contact', $route, $endpoint );
		}
    }

    public function contact_form_processor( WP_REST_Request $request ) {
        $body = json_decode($request->get_body(),true);
        $sentmail = new \STTV\Email([
            'to' => get_bloginfo('admin_email'),
            'subject' => $body['sttv_contact_subject'],
            'message' => $body['sttv_contact_message'],
            'headers' => [
                'Content-Type: text/html; charset=UTF-8',
                'From: '.$body['sttv_contact_name'].' <'.$body['sttv_contact_email'].'>',
                'Sender: SupertutorTV Website <info@supertutortv.com>',
                'Bcc: David Paul <dave@supertutortv.com>'
            ]
        ]);
        
        if ($sentmail->send()) {
            return sttv_rest_response( 'contact_form_success', 'Thanks for contacting us! We\'ll get back to you ASAP!', 200 );
        } else {
            return sttv_rest_response( 'contact_form_fail', 'There was an issue sending your message. Please try again later.', 200 );
        }
    }

    public function sttv_subscribe_processor( WP_REST_Request $request ) {
        $body = json_decode($request->get_body(),true);
        
        // use sttv API mailinglist function
        $response = sttv_mailinglist_subscribe( $body['email'], $body['fname'], $body['lname'] );

        if ( is_wp_error($response) ){
            return sttv_rest_response( 'sub_error', 'There was an error subscribing you to our list. Please try again later.', 400, ['response'=>$response] );
        } else {
            return sttv_rest_response( 'sub_success', 'Success! Thank you for subscribing to SupertutorTV!', 200, ['response'=>$response] );
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
}