<?php

defined( 'ABSPATH' ) || exit;

#######################################
##### SupertutorTV REST Functions #####
#######################################

/* These are 'global' functions pertaining strictly to the REST API */

function sttv_rest_response( $code = '', $msg = '', $status = 200, $extra = [] ) {
    $data = [
        'code'    => $code,
        'message' => $msg,
        'data'    => [ 
            'status' => $status
        ]
    ];
    $data = array_merge( $data, (array) $extra );
    return new WP_REST_Response( $data, $status );
}

function sttv_verify_web_token( WP_REST_Request $request ) {
    $string = $request->get_header('Authorization') ?? $_COOKIE['_stAuthToken'] ?? '';
    $token = new \STTV\JWT( $string );
    if ( $token->error !== false ) return $token->error;

    $token = json_decode(json_encode($token),true);
    $pieces = explode( '|', $token['payload']['sub'] );
    list( $email, $id ) = $pieces;

    $user = wp_set_current_user( $id );
    return !!$user->ID;
}

function sttv_set_auth_cookie($token) {
    setcookie('_stAuthToken',$token,time()+DAY_IN_SECONDS*7,'/','.supertutortv.com',true,true);
}

function sttv_unset_auth_cookie() {
    setcookie('_stAuthToken','x',1,'/','.supertutortv.com',true,true);
}

function sttv_verify_recap( WP_REST_Request $request ){
    $token = json_decode($request->get_body(),true);
    $token = $token['g_recaptcha_response'];

    if ( empty($token) ) return new \WP_Error( 'no_recaptcha', 'Shoo bot, shoo!', [ 'status' => 200 ] );

    $response = json_decode(file_get_contents("https://www.google.com/recaptcha/api/siteverify?secret=".RECAPTCHA_SECRET."&response=".$token."&remoteip=".$_SERVER['REMOTE_ADDR']),true);
    return $response['success'] ?: new \WP_Error( 'recaptcha_failed', 'Shoo bot, shoo!', [ 'status' => 200 ] );
}

function sttv_stripe_errors($cb) {
    try {
        return is_callable($cb) && $cb();
    } catch(\Stripe\Error\Card $e) {
        $body = $e->getJsonBody();
        $err  = $body['error'];
        return sttv_rest_response(
            'stripe_error',
            'There was an error',
            200,
            [ 'data' => $err ]
        );
    } catch (\Stripe\Error\RateLimit $e) {
        $body = $e->getJsonBody();
        $err  = $body['error'];
        return sttv_rest_response(
            'stripe_error',
            'There was an error',
            200,
            [ 'data' => $err ]
        );
    } catch (\Stripe\Error\InvalidRequest $e) {
        $body = $e->getJsonBody();
        $err  = $body['error'];
        return sttv_rest_response(
            'stripe_error',
            'There was an error',
            200,
            [ 'data' => $err ]
        );
    } catch (\Stripe\Error\Authentication $e) {
        $body = $e->getJsonBody();
        $err  = $body['error'];
        return sttv_rest_response(
            'stripe_error',
            'There was an error',
            200,
            [ 'data' => $err ]
        );
    } catch (\Stripe\Error\ApiConnection $e) {
        $body = $e->getJsonBody();
        $err  = $body['error'];
        return sttv_rest_response(
            'stripe_error',
            'There was an error',
            200,
            [ 'data' => $err ]
        );
    } catch (\Stripe\Error\Base $e) {
        $body = $e->getJsonBody();
        $err  = $body['error'];
        return sttv_rest_response(
            'stripe_error',
            'There was an error',
            200,
            [ 'data' => $err ]
        );
    } catch (\Exception $e) {
        $body = $e->getJsonBody();
        $err  = $body['error'];
        return sttv_rest_response(
            'stripe_error',
            'There was an error',
            200,
            [ 'data' => $err ]
        );
    }
}