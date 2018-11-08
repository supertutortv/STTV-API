<?php

namespace STTV;

defined( 'ABSPATH' ) || exit;

class Webhook {

    private $http = 200;

    private $event = null;

    private $message = '';

    private $request = null;

    private $response = null;

    private $error = false;

    public static function init() {
        if ( !isset($_GET['stripeevent']) && !isset($_GET['sttvwebhook']) ) {
			return false;
        }
        $class = __CLASS__;
        echo new $class;
        die;
    }

    public function __toString() {
        return "\n".date('Y/m/d H:i:s')."\t".json_encode( sttv_rest_response(
                $this->event,
                $this->message,
                $this->http,
                [ 'data' => $this->response ]
            )
        );
    }

    private function __construct() {

        $this->request = @file_get_contents( "php://input" );
        
        if ( empty( $this->request ) ) {
            $this->http = 400;
            $this->event = 'null_body';
            $this->message = 'Request body cannot be empty.';
            $this->response = [];
            $this->error = true;
        }

        $signed = null;
        
        if ( !$this->error ) {
            switch ( array_keys($_GET)[0] ) {
                case 'sttvwebhook':
                    $signed = $this->verifySignature( $this->request, @$_SERVER["HTTP_X_STTV_WHSEC"] );
                    break;
                case 'stripeevent':
                    $signed = \Stripe\Webhook::constructEvent(
                        $this->request, @$_SERVER["HTTP_STRIPE_SIGNATURE"], STRIPE_WHSEC
                    );
                    break;
            }

            if ( $signed ) {
                $this->respond( json_decode( $this->request, true ) );
            }
        }

        if ( false !== $this->response ) {
            \STTV\Log::webhook([
                'direction' => 'received',
                'error' => $this->error,
                'event' => $this->event,
                'data' => [
                    $this->message,
                    $this->http,
                    $this->response
                ]
            ]);
        }

        http_response_code( $this->http );
        return sttv_rest_response(
            $this->event,
            $this->message,
            $this->http,
            [ 'data' => $this->response ]
        );
    }

    private function respond( $data ) {
        //change event dot notation to underscores
        $this->event = str_replace( '.', '_', $data['type'] );

        if ( is_callable( $this->event ) ) {
            $this->response = ($this->event)( $data );
            $this->message = $this->response ? 'Webhook executed' : 'Valid webhook, but no action taken. Thank you!';
        } else {
            $email = new \STTV\Email([
                'to' => 'enlightenedpie@gmail.com',
                'subject' => 'Webhook data',
                'message' => '<pre>'.json_encode($data,JSON_PRETTY_PRINT).'</pre>'
            ]);
            $email->send();
            $this->http = 418;
            $this->message = 'invalid_webhook';
            $this->response = [
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
        }

    }

    public static function send( $body, $event ) {
        return wp_safe_remote_request( 'https://supertutortv.com/?sttvwh', 
            [
                'method' => 'POST',
                'user-agent' => STTV_UA,
                'headers' => [
                    'X-STTV-WHSEC' => self::sign( $body ),
                    'X-STTV-Event' => $event
                ],
                'body' => json_encode( $body )
            ]
        );
    }

    private function verifySignature( $request, $sig ) {
        $data = json_decode( $request, true );
        $jsonError = json_last_error();
        if ($data === null && $jsonError !== JSON_ERROR_NONE) {
            $this->event = 'invalid_payload';
            $this->message = "Invalid payload. ($jsonError)";
            $this->http = 403;
            $this->response = $data;
            $this->error = true;
            return false;
        }

        if ( $sig !== self::sign( $data ) ) {
            $this->event = 'invalid_signature';
            $this->message = "Webhook signature is invalid. ($sig)";
            $this->http = 401;
            $this->response = $data;
            $this->error = true;
            return false;
        }

        return true;
    }

    private static function sign( $payload ) {
        return hash_hmac( 'sha256', json_encode( $payload ), env( 'STTV_WHSEC' ) );
    }
}
/* $response = [
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
]; */