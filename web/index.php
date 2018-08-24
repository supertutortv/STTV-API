<?php

if (strpos($_SERVER['REQUEST_URI'],'/auth/verify') > -1 && !isset($_COOKIE['stAuth'])) {
    header( 'Access-Control-Allow-Headers: Accept, Referrer, Origin, Credentials, Content-Type, User-Agent, Access-Control-Allow-Headers, Authorization, X-RateLimit-Buster' );
    header( 'Access-Control-Allow-Origin: https://courses.supertutortv.com' );
    header( 'Access-Control-Allow-Credentials: true' );
    header( 'Content-Type: application/vnd.sttv.app+json' );
    echo json_encode(['data' => false]);
    die;
}

/** WordPress view bootstrapper */
define('WP_USE_THEMES', true);
require(__DIR__ . '/wp/wp-blog-header.php');
