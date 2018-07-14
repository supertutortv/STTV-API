<?php

namespace STTV;
use \DomainException;
use \InvalidArgumentException;
use \UnexpectedValueException;
use \DateTime;
use \WP_Error;

defined( 'ABSPATH' ) || exit;

class JWT {

    public $token = '';

    public $payload;

    public $error = false;

    private $algs = [
        'HS256' => 'sha256'
    ];

    public function __construct( $arg ) {
        if ( $arg instanceof \WP_User ) {
            $this->token = $this->generate( $arg );
        } elseif ( is_string( $arg ) ) {
            $this->error = $this->verify( $arg );
        }
    }

    private function generate( $user ) {
        $issued = time();
        $pieces = [];

        $header = [
            'typ' => 'jwt',
            'alg' => 'HS256'
        ];
        $payload = [
            'iss' => STTV_JWT_ISSUER,
            'iat' => $issued,
            'nbf' => $issued,
            'exp' => $issued + 3600,//(DAY_IN_SECONDS*5),
            'sub' => $user->data->user_email.'|'.$user->data->ID
        ];

        $pieces[] = $this->base64Encode( json_encode( $header ) );
        $pieces[] = $this->base64Encode( json_encode( $payload ) );
        $signature = $this->sign( implode( '.', $pieces ) );
        $pieces[] = $this->base64Encode( $signature );

        return implode( '.', $pieces );
    }

    private function verify( $auth = '' ) {
        $status = ['status'=>200];
        if ( empty($auth) ) return new WP_Error('web_token_null','Auth token cannot be null or empty',$status);

        $timestamp = time();
        $token = str_replace('Bearer ','',$auth);

        $this->token = $token;

        $pieces = explode('.', $token);
        if ( count($pieces) != 3 ) return new WP_Error('web_token_malformed',$pieces,$status);

        list( $header64, $payload64, $sig64 ) = $pieces;

        if ( null === ( $header = json_decode( $this->base64Decode( $header64 ) ) ) ) return new WP_Error('web_token_header_encoding_invalid','',$status);
        if ( null === ( $payload = json_decode( $this->base64Decode( $payload64 ) ) ) ) return new WP_Error('web_token_payload_encoding_invalid','',$status);
        if ( false === ( $sig = $this->base64Decode( $sig64 ) ) ) return new WP_Error('web_token_signature_encoding_invalid','',$status);
        if ( !isset($this->algs[$header->alg]) ) return new WP_Error('web_token_algorithm_invalid','',$status);

        $sigcheck = $this->sign( "$header64.$payload64" );

        if ( $sigcheck !== $sig ) return new WP_Error('web_token_signature_verification_failed','',$status);
        if ( $payload->iss !== STTV_JWT_ISSUER ) return new WP_Error('web_token_issuer_invalid','',$status);
        if ( (isset( $payload->nbf ) && $payload->nbf > $timestamp) || (isset( $payload->iat ) && $payload->iat > $timestamp) )
            return new WP_Error('web_token_used_too_soon','',$status);
        if ( !isset( $payload->exp ) || $payload->exp < $timestamp ) return new WP_Error('web_token_expired','',$status);

        $this->payload = $payload;
        return false;
    }

    private function sign( $input ) {
        $secret = hash_hmac( 'sha512', LOGGED_IN_KEY, LOGGED_IN_SALT );
        return hash_hmac( 'sha256', $input, $secret, true);
    }

    private function base64Encode( $input ) {
        return str_replace( '=', '', strtr( base64_encode( $input ), '+/', '-_' ) );
    }

    private function base64Decode( $input ) {
        $remainder = strlen( $input ) % 4;
        if ( $remainder ) {
            $padlen = 4 - $remainder;
            $input .= str_repeat( '=', $padlen );
        }
        return base64_decode( strtr( $input, '-_', '+/' ) );
    }

}