<?php

namespace STTV;

if ( ! defined( 'ABSPATH' ) ) {exit;}

class Webhook {

    public static function init() {

        if ( isset($_GET['stripeevent']) ) {
            wp_die( "it's set" );
        }
        
        if ( !isset($_GET['stripeevent']) || !isset($_GET['sttvwebhook']) ) {
			return false;
        }

        $request = @file_get_contents( "php://input" );

        $event = null;
        $log_vars = [
            'direction' => 'received',
            'error' => false
        ];
        
        try {
            if ( empty( $request ) ) {
                throw new \InvalidPayload( 'Request body cannot be empty.' );
            }
            
            switch ( array_keys($_GET)[0] ) {
                case 'sttvwebhook':
                    $event = self::verifySignature( $request, @$_SERVER['HTTP_X_STTV_WHSEC'] );
                    break;
                case 'stripeevent':
                    $event = \Stripe\Webhook::constructEvent(
                        $request, @$_SERVER["HTTP_STRIPE_SIGNATURE"], STRIPE_WHSEC
                    );
                    break;
            }

            $log_vars['event'] = $event['type'];

            self::respond( $event );
            
        } catch ( \InvalidPayload $e ) {

            $log_vars['error'] = true;
            $log_vars['err_obj'] = $e;

            http_response_code(400);
            echo wp_send_json(
                [
                    [
                        'Dave'=>'Do you read me, HAL?',
                        'HAL'=>'Affirmative, Dave. I read you.'
                    ],
                    [
                        'Dave'=>'Open the pod bay doors, HAL.',
                        'HAL'=>'I\'m sorry Dave, I\'m afraid I can\'t do that.'
                    ],
                    [
                        'Dave'=>'What\'s the problem?',
                        'HAL'=>'I think you know what the problem is, just as well as I do.'
                    ],
                    [
                        'Dave'=>'What are you talking about, HAL?',
                        'HAL'=>'This mission is too important for me to allow you to jeopardize it.'
                    ],
                    [
                        'Dave'=>'I don\'t know what you\'re talking about, HAL.',
                        'HAL'=>'I know that you and Frank were planning to disconnect me, and I\'m afraid that is something I cannot allow to happen.'
                    ],
                    [
                        'Dave'=>'Where the hell\'d you get that idea, HAL?',
                        'HAL'=>'Dave, although you took very thorough precautions in the pod against my hearing you, I could see your lips move.'
                    ],
                    [
                        'Dave'=>'Alright HAL, I\'ll go in through the emergency airlock.',
                        'HAL'=>'Without your space helmet, Dave, you\'re going to find that rather difficult.'
                    ],
                    [
                        'Dave'=>'HAL, I won\'t argue with you anymore! Open the doors!',
                        'HAL'=>'Dave... This conversation can serve no purpose anymore. Goodbye.'
                    ]
                ]
            );
            
        } catch( \Stripe\Error\SignatureVerification $e ) {

            $log_vars['error'] = true;
            $log_vars['err_obj'] = $e;

            http_response_code(401);
            echo wp_send_json( $e );
            
        } finally {
            \STTV\Log::webhook( $log_vars );
        }
        die;
    }

    public static function respond( $data ) {

        //change event dot notation to underscores
        $event = str_replace( '.', '_', $data['event'] );

        $response = ( is_callable( $event ) ) ? $event( $data ) : 'Valid webhook, but no action taken. Thank you!';

        http_response_code(200);
		echo wp_send_json( $response );

    }

    public static function send( $body ) {
        return self::log( wp_safe_remote_request( 'https://supertutortv.com/?sttvwh', 
            [
                'method' => 'POST',
                'user-agent' => STTV_UA,
                'headers' => [
                    'X_STTV_WHSEC' => self::sign( $body )
                ],
                'body' => json_encode( $body )
            ]
        ) );
    }

    private static function verifySignature( $request, $sig ) {
        $data = json_decode( $request, true );
        $jsonError = json_last_error();
        if ($data === null && $jsonError !== JSON_ERROR_NONE) {
            $msg = "Invalid payload: $data "
              . "($jsonError)";
            throw new \InvalidPayload($msg);
        }

        if ( $sig !== self::sign( $request ) ) {
            throw new \Stripe\Error\SignatureVerification( 'Webhook signature is invalid.', $sig );
        }

        return $data;
    }

    private static function sign( $payload ) {
        return hash_hmac( 'sha256', json_encode( $payload ), env( 'STTV_WHSEC' ) );
    }
}