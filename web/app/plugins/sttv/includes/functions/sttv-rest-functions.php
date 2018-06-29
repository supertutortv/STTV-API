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
    $token = new \STTV\JWT( $request->get_header('Authorization') );
    if ( $token->error !== false ) return $token->error;
    print_r($token->payload);
    return;

    $pieces = explode( '|', $token->payload->sub );
    list( $email, $id ) = $pieces;

    $user = wp_set_current_user( $id );
    return !!$user->ID;
}