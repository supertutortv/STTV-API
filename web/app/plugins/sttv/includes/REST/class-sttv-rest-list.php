<?php

namespace STTV\REST;

defined( 'ABSPATH' ) || exit;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class Mailinglist extends \WP_REST_Controller {

    public function __construct() {}

    public function register_routes() {
        $routes = [
            '/subscribe' => [
                [
                    'methods' => 'POST',
                    'callback' => [ $this, 'sttv_subscribe_processor' ],
                    //'permission_callback' => 'sttv_verify_recap'
                ]
            ]
        ];

        foreach ( $routes as $route => $endpoint ) {
			register_rest_route( 'list', $route, $endpoint );
		}
    }

    public function sttv_subscribe_processor( WP_REST_Request $request ) {
        $body = json_decode($request->get_body(),true);
        
        // use sttv API mailinglist function
        $response = sttv_mailinglist_subscribe( $body['email'], $body['firstname'], $body['lastname'] );

        if ( is_wp_error($response) ){
            return sttv_rest_response( 'sub_error', 'There was an error subscribing you to our list. Please try again later.', 400, ['response'=>$response] );
        } else {
            return sttv_rest_response( 'sub_success', 'Success! Thank you for subscribing to SupertutorTV!', 200, ['response'=>$response] );
        }
    }
}