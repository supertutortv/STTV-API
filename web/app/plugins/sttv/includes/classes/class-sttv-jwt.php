<?php

namespace STTV;
use \DomainException;
use \InvalidArgumentException;
use \UnexpectedValueException;
use \DateTime;
use \WP_Error;

defined( 'ABSPATH' ) || exit;

class JWT {

    public function __construct() {}

    public static function generate( $obj = [] ) {

    }

    public static function verify( $auth = '' ) {
        if ( empty($auth) ) {
            return new WP_Error(
                'no_auth_header',
                'Authorization header not found and is required for this endpoint.',
                [
                    'status' => 403,
                ]
            );
        }

        list($token) = sscanf($auth, 'Bearer %s');
        if (!$token) {
            return new WP_Error(
                'bad_auth_header',
                'Authorization header malformed. Please check the syntax and try again.',
                [
                    'status' => 403,
                ]
            );
        }

        $timestamp = time();
        $tks = explode('.', $jwt);
        if (count($tks) != 3) {
            return new WP_Error(
                'bad_web_token',
                'The JSON token is malformed.',
                [
                    'status' => 403,
                ]
            );
        }

        return true;
    }

}