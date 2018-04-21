<?php

namespace STTV;

if ( ! defined( 'ABSPATH' ) ) {exit;}

class Webhook {

    public static function init() {
        if ( !isset($_GET['stripeevent']) || !isset($_GET['sttvwebhook']) ) {
			return false;
        }

        $event = false;
        $request = @file_get_contents( "php://input" );
        $decoded = json_decode( $request, true );
        $log_vars = [
            'log_path' => SP_LOG_PATH . 'events/',
            'date' => date('m/d/Y G:i:s', time()),
            'fw_ip' => getenv('HTTP_X_FORWARDED_FOR') ?: '0.0.0.0',
            'ip' => getenv('REMOTE_ADDR'),
            'ua' => getenv('HTTP_USER_AGENT'),
            'event' => $decoded['type'],
            'id' => $decoded['id'],
            'error' => false
        ];
        
        try {
            
            switch ( array_keys($_GET)[0] ) {
                case 'sttvwebhook':
                    $event = self::respond();
                    break;
                case 'stripeevent':
                    $event = \Stripe\Webhook::constructEvent(
                        $request, @$_SERVER["HTTP_STRIPE_SIGNATURE"], STRIPE_WHSEC
                    );
                    break;
            }
            
        } catch( \UnexpectedValueException $e) { // \InvalidPayload |  <-- add this to exception
            //handle errors
        } finally {
            //log shit
        }
        die;
    }

    public static function respond( $data ) {

        $jsonError = json_last_error();
        if ($data === null && $jsonError !== JSON_ERROR_NONE) {
            $msg = "Invalid payload: $data "
              . "($jsonError)";
            throw new \InvalidPayload($msg);
        }

        //change event dot notation to underscores
        $event = str_replace( '.', '_', $data['event'] );

        $response = ( is_callable( $event ) ) ? self::log( $event( $data ), 'receive' ) : 'Valid webhook, but no action taken. Thank you!';

        http_response_code(200);
		echo wp_send_json( $response );

    }

    public static function send( $body ) {
        return self::log( wp_safe_remote_request( 'https://supertutortv.com/?sttvwh', 
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