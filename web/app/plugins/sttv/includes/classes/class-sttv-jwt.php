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

    public $payload = [];

    public $error = false;

    private $algs = [
        'HS256' => 'sha256',
        'HS512' => 'sha512',
        'HS384' => 'sha384',
    ];

    public function __construct( $arg, $alg = 'HS256' ) {
        if ( $arg instanceof \WP_User ) {
            $this->token = $this->generate( $arg, $alg );
        } elseif ( is_string( $arg ) ) {
            $this->error = $this->verify( $arg );
        }
    }

    private function generate( $user, $alg ) {
        $issued = time();
        $pieces = [];

        $header = [
            'typ' => 'jwt',
            'alg' => $alg
        ];
        $payload = [
            'iss' => STTV_JWT_ISSUER,
            'iat' => $issued,
            'nbf' => $issued,
            'exp' => $issued + 30,//(DAY_IN_SECONDS*5),
            'sub' => $user->data->user_email.'|'.$user->data->ID
        ];

        $pieces[] = $this->base64Encode( json_encode( $header ) );
        $pieces[] = $this->base64Encode( json_encode( $payload ) );
        $signature = $this->sign( implode( '.', $pieces ), $alg );
        $pieces[] = $this->base64Encode( $signature );

        return implode( '.', $pieces );
    }

    private function verify( $auth = '' ) {
        $status = ['status'=>403];
        if ( empty($auth) ) return new WP_Error('web_token_auth_header_missing','',$status);

        $timestamp = time();
        list($token) = sscanf($auth, 'Bearer %s');
        if (!$token) return new WP_Error('web_token_auth_header_malformed','',$status);

        $pieces = explode('.', $token);
        if ( count($pieces) != 3 ) return new WP_Error('web_token_malformed','',$status);

        list( $header64, $payload64, $sig64 ) = $pieces;

        if ( null === ( $header = json_decode( $this->base64Decode( $header64 ) ) ) ) return new WP_Error('web_token_header_encoding_invalid','',$status);
        if ( null === ( $payload = json_decode( $this->base64Decode( $payload64 ) ) ) ) return new WP_Error('web_token_payload_encoding_invalid','',$status);
        if ( false === ( $sig = $this->base64Decode( $sig64 ) ) ) return new WP_Error('web_token_signature_encoding_invalid','',$status);
        if ( !isset($this->algs[$header->alg]) ) return new WP_Error('web_token_algorithm_invalid','',$status);

        $sigcheck = $this->sign( "$header64.$payload64", $header->alg );

        if ( $sigcheck !== $sig ) return new WP_Error('web_token_signature_verification_failed','',$status);
        if ( $payload->iss !== STTV_JWT_ISSUER ) return new WP_Error('web_token_issuer_invalid','',$status);
        if ( (isset( $payload->nbf ) && $payload->nbf > $timestamp) || (isset( $payload->iat ) && $payload->iat > $timestamp) )
            return new WP_Error('web_token_used_too_soon','',$status);
        if ( !isset( $payload->exp ) || $payload->exp < $timestamp ) return new WP_Error('web_token_expired','',$status);

        $this->payload = $payload;
        return false;
    }

    private function sign( $input, $alg = 'HS256' ) {
        $secret = hash_hmac( 'SHA512', LOGGED_IN_KEY, LOGGED_IN_SALT );
        return hash_hmac( $this->algs[$alg], $input, $secret, true);
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