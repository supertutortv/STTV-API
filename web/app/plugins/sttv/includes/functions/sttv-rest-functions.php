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

    $pieces = explode( '|', $token->payload->sub );
    list( $email, $id ) = $pieces;

    $user = wp_set_current_user( $id );
    return !!$user->ID;
}

function sttv_verify_course_user( WP_REST_Request $request ) {
    $token = sttv_verify_web_token( $request );
    if ( $token instanceof \WP_Error ) {
        return $token;
    }
    $perm = current_user_can( 'course_access_cap' );
    if (!$perm) {
        return new \WP_Error('unauthorized_course_user','You do not have access to our courses at this time.',['status'=>403]);
    }
    return $perm;
}