<?php

namespace STTV;

defined( 'ABSPATH' ) || exit;

class Webhook {

    private static $http = 200;

    private static $log_vars = [
        'direction' => 'received',
        'error' => false
    ];

    private static $event = null;

    private static $response = null;

    private static $error = false;

    public static function init() {
        
        if ( !isset($_GET['stripeevent']) && !isset($_GET['sttvwebhook']) ) {
			return false;
        }
        return array_keys($_GET)[0] && die;

        $request = @file_get_contents( "php://input" );
        if ( empty( $request ) ) {
            self::$response = sttv_rest_response(
                'null_body',
                'Request body cannot be empty.',
                400
            );
        }
        
        switch ( array_keys($_GET)[0] ) {
            case 'sttvwebhook':
                self::$event = self::verifySignature( $request, @$_SERVER["HTTP_X-STTV-WHSEC"] );
                break;
            case 'stripeevent':
                self::$event = \Stripe\Webhook::constructEvent(
                    $request, @$_SERVER["HTTP_STRIPE_SIGNATURE"], STRIPE_WHSEC
                );
                break;
        }

        self::$log_vars['event'] = self::$event['type'];

        $response = self::respond( self::$event );

        \STTV\Log::webhook( self::$log_vars );
        http_response_code( self::$http );
        return $response;
        
        try {

            
            
            

            
            
        } catch ( \InvalidArgumentException $e ) {

            $log_vars['error'] = true;
            $log_vars['err_obj'] = $e->getMessage();

            $http = 400;
            
            $response = [
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
                ];
            
        } catch( \Stripe\Error\SignatureVerification $e ) {

            $log_vars['error'] = true;
            $log_vars['err_obj'] = $e->getMessage();

            $http = 401;
            $response = $e;
            
        }
        die;
    }

    public static function respond( $data ) {

        //change event dot notation to underscores
        $event = str_replace( '.', '_', $data['event'] );

        return ( is_callable( $event ) ) ? $event( $data ) : 'Valid webhook, but no action taken. Thank you!';

    }

    public static function send( $body, $event ) {
        return wp_safe_remote_request( 'https://supertutortv.com/?sttvwh', 
            [
                'method' => 'POST',
                'user-agent' => STTV_UA,
                'headers' => [
                    'X_STTV_WHSEC' => self::sign( $body )
                ],
                'body' => json_encode( $body )
            ]
        );
    }

    private static function verifySignature( $request, $sig ) {
        $data = json_decode( $request, true );
        $jsonError = json_last_error();
        if ($data === null && $jsonError !== JSON_ERROR_NONE) {
            return sttv_rest_response(
                'invalid_payload',
                "Invalid payload. ($jsonError)",
                403,
                [ 'data' => $data ]
            );
        }

        if ( $sig !== self::sign( $request ) ) {
            return sttv_rest_response(
                'invalid_signature',
                "Webhook signature is invalid. ($sig)",
                401
            );
        }

        return $data;
    }

    private static function sign( $payload ) {
        return hash_hmac( 'sha256', json_encode( $payload ), env( 'STTV_WHSEC' ) );
    }
}