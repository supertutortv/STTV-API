<?php
if (strpos($_SERVER['REQUEST_URI'],'/auth/verify') > -1 && !isset($_COOKIE['_stAuthToken'])) {
    $allowed_origins = [
        'https://supertutortv.com',
        'https://dev.courses.supertutortv.com',
        'https://courses.supertutortv.com',
        'https://api.supertutortv.com'
    ];
    if ( in_array( $_SERVER['HTTP_ORIGIN'], $allowed_origins ) ) {
        header( 'Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN'] );
    } else {
        header( 'Access-Control-Allow-Origin: https://courses.supertutortv.com' );
    }
    header( 'Access-Control-Allow-Headers: Accept, Referrer, Origin, Credentials, Content-Type, User-Agent, Access-Control-Allow-Headers, Authorization, X-RateLimit-Buster' );
    header( 'Access-Control-Allow-Credentials: true' );
    header( 'Content-Type: application/vnd.sttv.app+json' );
    echo json_encode(['data' => false]);
    die;
}