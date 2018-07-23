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
                    'callback' => [ $this, 'signup' ]
                ]
            ]
        ];

        foreach ( $routes as $route => $endpoint ) {
			register_rest_route( 'multiuser', $route, $endpoint );
		}
    }

    public function get_teacher_keys( WP_REST_Request $req ) {
        return sttv_rest_response(
            'mu_keys_requested',
            'Here are the requested multi-user keys.',
            200,
            [
                'data' => [
                    'keys' => (new \STTV\Multiuser\Keys( $req->get_param('uid') ))->get_keys()
                ]
            ]
        );
    }

    public function keygen( WP_REST_Request $req ) {
        $body = json_decode($req->get_body(),true);
        $keys = (new \STTV\Multiuser\Keys( $body[ 'user' ], $body[ 'course' ] ))->keygen( $body['qty'] );
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

        return sttv_rest_response(
            'mu_keys_generated',
            'The multi-user keys were succesfully generated.'
        );
    }

    public function reset( WP_REST_Request $req ) {
        $k = new \STTV\Multiuser\Keys( $req->get_param('mu_key') );
        $key = $k->get_current_key();
        if ( 0 === $key['active_user'] ) return false;

        $user = get_userdata( $key['active_user'] );
        $user->remove_all_caps();
        $user->set_role( 'Student' );

        return sttv_rest_response(
            'key_reset',
            $key['mu_key'].' was reset to default status.',
            200,
            [ 'key' => $k->reset() ]
        );
    }

    public function signup( WP_REST_Request $req ) {
        $body = json_decode( $req->get_body(), true );
        $key = new \STTV\Multiuser\Keys( $body['mu_key'] );

        return $key->is_subscribed(814);

        if ( !$key->validate() ) return sttv_rest_response(
            'mu_key_invalid',
            'The multi-user key is invalid. Please contact your teacher/tutor for assistance.'
        );

        $student = wp_set_current_user(null,$body['email']);
        if ( false === $student ) {
            $user_id = wp_insert_user([
                'user_login' => $body['email'],
                'user_pass' => $body['password'],
                'user_email' => $body['email'],
                'first_name' => $body['firstname'],
                'last_name' => $body['lastname'],
                'display_name' => $body['firstname'].' '.$body['lastname'],
                'show_admin_bar_front' => 'false',
                'role' => 'student'
            ]);
    
            if ( is_wp_error( $user_id ) ) {
                return sttv_rest_response(
                    'user_insert_error',
                    'There was an error adding you as a user in our system. Please check with your teacher/tutor for further instructions.',
                    200,
                    [ 'data' => $user_id ]
                );
            }
            $student = wp_set_current_user( $user_id );
        }

        $key->activate();
    }
}