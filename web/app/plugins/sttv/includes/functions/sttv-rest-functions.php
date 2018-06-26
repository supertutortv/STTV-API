<?php

defined( 'ABSPATH' ) || exit;

#######################################
##### SupertutorTV REST Functions #####
#######################################

/* These are 'global' functions pertaining strictly to the REST API */

function sttv_rest_response( $code = '', $msg = '', $status = 200, $extra = [] ) {
    $data = [
        'code'    => $code,
        'message' => $msg,
        'data'    => [ 
            'status' => $status
        ]
    ];
    $data = array_merge( $data, (array) $extra );
    return new WP_REST_Response( $data, $status );
}

function sttv_verify_web_token( WP_REST_Request $request ) {
    $token = \STTV\JWT::verify( $request->get_header('Authorization') );

    if ( is_wp_error() ) {
        return $token;
    }

    $user = wp_set_current_user( $token->data->ID );
    return !!$user->ID;
}