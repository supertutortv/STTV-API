<?php

namespace STTV\REST\Courses;

defined( 'ABSPATH' ) || exit;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class MultiUser extends \WP_REST_Controller {

    private $countrydd;

    private $price_table;

    public function __construct() {
        $this->countrydd = get_option( 'sttv_country_options' );
    }

    public function register_routes() {
        $routes = [
            '/keys' => [
                [
                    'methods' => 'GET',
                    'callback' => [ $this, 'get_teacher_keys' ],
                    'args' => [
                        'uid' => [
                            'required' => true,
                            'type' => 'integer',
                            'description' => 'Please provide the teacher uid'
                        ]
                    ]
                ],
                [
                    'methods' => 'POST',
                    'callback' => [ $this, 'keygen' ]
                ],
                [
                    'methods' => 'PATCH',
                    'callback' => [ $this, 'reset' ]
                ]
            ],
            '/signup' => [
                [
                    'methods' => 'POST',
                    'callback' => [ $this, 'mu_signup' ]
                ]
            ]
        ];

        foreach ( $routes as $route => $endpoint ) {
			register_rest_route( 'multiuser', $route, $endpoint );
		}
    }

    public function get_teacher_keys( WP_REST_Request $req ) {
        return (new STTV\Multiuser\Keys( $req->get_param('uid') ))->get_keys();
    }

    public function multi_user_keygen( WP_REST_Request $req ) {
        $body = json_decode($req->get_body(),true);
        $mu = new MultiUser(  );
        $keys = (new STTV\Multiuser\Keys( $body[ 'user' ], $body[ 'course' ] ))->keygen( $body['qty'] );
        $msg = "\r\n";

        foreach ( $keys as $key ) {
            $msg .= $key."\r\n";
        }

        wp_mail(
            $params[ 'email' ],
            'Your SupertutorTV multi-user keys',
            "The keys below were generated for you. Thank you for your purchase! Sign into your SupertutorTV account to see more info on the keys, including their active status and expiration dates.".$msg,
            ['Bcc: info@supertutortv.com']
        );

        return $keys;
    }

    private function reset_key( $token ) {
        $k = new MultiUser( $token );
        $key = $k->get_current_key();
        if ( 0 === $key[$token]['active_user'] ) {
            return false;
        }

        get_userdata( $key[$token]['active_user'] )->set_role( sttv_default_role() );
        return sttv_rest_response(
            'key_reset',
            $token.' was reset to default status.',
            200,
            [ 'key' => $k->reset_key() ]
        );
    }

    public function multi_user_verify( WP_REST_Request $req ) {
        $params = json_decode($req->get_body(),true);
        if ( !isset( $params[ 'mukey' ] ) ) {
            return sttv_rest_response( 'null_key', 'You must provide an invitation code with this request.', 400 );
        }

        if ( !isset( $params[ 'email' ] ) ) {
            return sttv_rest_response( 'null_email', 'You must provide the email associated with the invitation code.', 400 );
        }

        $mup = new MultiUserPermissions( $params[ 'mukey' ] );

        if ( !$mup->verify_key( $params[ 'email' ] )->is_valid ) {
            return sttv_rest_response( 'invalid_key', 'The invitation code provided is invalid.', 403 );
        }

        $usekey = $mup->usekey()->output;
        ob_start();

        sttv_get_template('checkout','checkout',[
            'countrydd' => $this->countrydd,
            'user' => $mup->get_current_user()
        ]);

        return sttv_rest_response(
            'success',
            'Permission granted.',
            200,
            [
                'data' => [
                    'id' => $mup->get_current_key(),
                    'type' => 'multi-user',
                    'price' => $this->price_table[ $params[ 'license' ][ 'id' ] ][ $params[ 'license' ][ 'qty' ] ],
                    'qty' => $params[ 'license' ][ 'qty' ],
                    'course_id' => $params[ 'license' ][ 'id' ],
                    'name' => $params[ 'license' ][ 'title' ],
                    'taxable' => false
                ],
                'html' => ob_get_clean()
            ]
        );
    }
}