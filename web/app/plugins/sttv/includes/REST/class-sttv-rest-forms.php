<?php

namespace STTV\REST;

defined( 'ABSPATH' ) || exit;

class Forms extends \WP_REST_Controller {

    public function __construct() {}

    public function register_routes() {
        $routes = [
            '/subscribe' => [
                [
                    'methods' => 'POST',
                    'callback' => [ $this, 'sttv_subscribe_processor' ],
                    //'permission_callback' => 'sttv_verify_recap'
                ]
            ],
            '/sales' => [
                [
                    'methods' => 'POST',
                    'callback' => [ $this, 'sttv_sales_processor' ],
                    //'permission_callback' => [ $this, 'verify_form_submit' ]
                ]
            ]
        ];

        foreach ( $routes as $route => $endpoint ) {
			register_rest_route( 'forms', $route, $endpoint );
		}
    }

    public function sttv_subscribe_processor( WP_REST_Request $request ) {
        $body = json_decode($request->get_body(),true);
        
        $response = sttv_mailinglist_subscribe( $body['email'], $body['firstname'], $body['lastname'] );

        return ( is_wp_error($response) ) ? 
            sttv_rest_response( 'sub_error', 'There was an error subscribing you to our list. Please try again later.', 200, ['response'=>$response] ) :
            sttv_rest_response( 'sub_success', 'Success! Thank you for subscribing to SupertutorTV!', 200, ['response'=>$response] );
    }

    public function sttv_sales_processor( WP_REST_Request $request ) {
        extract(json_decode($request->get_body(),true));

        $sentmail = new \STTV\Email([
            'to' => get_bloginfo('admin_email'),
            'subject' => 'Multi-user Sales Inquiry',
            'message' => $message,
            'headers' => [
                'Content-Type: text/html; charset=UTF-8',
                'From: '.$body['sttv_contact_name'].' <'.$body['sttv_contact_email'].'>',
                'Sender: SupertutorTV Website <info@supertutortv.com>',
                'Bcc: David Paul <dave@supertutortv.com>'
            ]
        ]);
        
        return ( $sentmail->send() ) ? 
            sttv_rest_response( 'sales_form_success', 'Thanks for contacting us! We\'ll get back to you ASAP!', 200 ) : 
            sttv_rest_response( 'sales_form_fail', 'There was an issue sending your message. Please try again later.', 200 );
    }
}