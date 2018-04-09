<?php

namespace STTV;

if ( ! defined( 'ABSPATH' ) ) {exit;}

class Webhook {

    public static function init() {

    }

    public static function respond( $data ) {
        //change event dot notation to underscores
        $event = str_replace( '.', '_', $data['event'] );

        $response = ( is_callable( $event ) ) ? self::log( $event( $data ), 'receive' ) : 'Valid webhook, but no action taken. Thank you!';

        http_response_code(200);
		echo wp_send_json( $response );

    }

    public static function send( $body ) {
        return self::log( wp_safe_remote_request( '', 
            [

            ]
        ) );
    }

    private static function verifySignature( $sig ) {
        return ( $sig === hash_hmac( 'sha256', $body, env( 'STTV_WHSEC' ) ) );
    }

    private static function sign( $payload ) {
        return hash_hmac( 'sha256', $payload, env( 'STTV_WHSEC' ) );
    }

    private static function log( $response, $type = 'send' ) {
        return $response;
    }
}