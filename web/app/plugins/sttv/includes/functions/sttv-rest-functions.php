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
    $string = $request->get_header('Authorization') ?? $_COOKIE['_stAuthToken'] ?? '';
    $token = json_decode(json_encode(new \STTV\JWT( $string )),true);
    print_r($token);
    return false;
    if ( $token['error'] !== false ) return $token['error'];

    $pieces = explode( '|', $token['payload']['sub'] );
    list( $email, $id ) = $pieces;

    $user = wp_set_current_user( $id );
    return !!$user->ID;
}

function sttv_set_auth_cookie($token) {
    setcookie('_stAuthToken',$token,time()+DAY_IN_SECONDS*7,'/','.supertutortv.com'); //change to secure cookie before production
}