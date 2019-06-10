<?php

namespace STTV\REST;

defined( 'ABSPATH' ) || exit;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class Checkout extends \WP_REST_Controller {

    private $zips = [];

    private $tax = 0;

    private $timestamp;

    public function __construct() {
        $this->zips = json_decode( get_option( 'sttv_ca_zips' ) );
        $this->timestamp = time();
    }

    public function __call($a,$b) {
        return false;
    }

    public function register_routes() {
        $steps = [
            [
                'methods' => 'POST',
                'callback' => [ $this, 'stSignupPost' ]
            ]
        ];

        $routes = [
            '/(?P<plan>act|sat|combo)' => [
                [
                    'methods' => 'GET',
                    'callback' => [ $this, 'stSignupInit' ]
                ]
            ],
			'/check' => [
				[
                    'methods' => 'GET',
                    'callback' => [ $this, 'sttv_parameter_checker' ],
                    'args' => [
                        'coupon' => [
                            'required' => false,
                            'type' => 'string',
                            'description' => 'Coupon to check'
                        ]
                    ]
                ]
            ],
            '/cancel' => [
				[
                    'methods' => 'POST',
                    'callback' => [ $this, 'stCancellations' ],
                    'permission_callback' => 'sttv_verify_web_token'
                ]
            ],
            '/account' => $steps,
            '/pay' => $steps
		];

		foreach ( $routes as $route => $endpoint ) {
			register_rest_route( 'checkout', $route, $endpoint );
		}
    }

    public function stSignupInit( WP_REST_Request $request ) {
        $plan = json_decode(get_option('pricingplan_'.$request['plan']),true);
        return sttv_rest_response( 'pricingData', 'ok' , 200, [ 'data' => $plan ]);
    }

    public function stSignupPost( WP_REST_Request $request ) {
        $body = json_decode($request->get_body(),true);
        $ep = str_replace('/signup/','_',$request->get_route());
        
        if ( empty($body) ) return sttv_rest_response( 'checkout_null_body', 'Request body cannot be empty', 400 );

        $body = sttv_array_map_recursive( 'rawurldecode', $body );
        $body = sttv_array_map_recursive( 'sanitize_text_field', $body );

        return $this->$ep($body,$request);
    }

    private function _account( $body, $request ) {
        extract($body);
        $firstname = ucfirst(strtolower($firstname));
        $lastname = ucfirst(strtolower($lastname));
        $email = strtolower($email);
        $fullname = $firstname.' '.$lastname;

        if ( !is_email( $email ) ) return sttv_rest_response( 'signupError', 'Email cannot be empty or blank, and must be a valid email address.', 200 );

        if ( email_exists( $email ) ) return sttv_rest_response( 'signupError', 'Email address is already in use. If this is you, please login and make your purchase through the dashboard. ', 200 );

        return sttv_rest_response(
            'signupSuccess',
            'Account created',
            200,
            [
                'update' => [
                    'account' => [
                        'email' => $email,
                        'firstname' => $firstname,
                        'lastname' => $lastname,
                        'password' => $password
                    ]
                ]
            ]
        );
    }

    private function _pay( $body, $request ) {
        if ( empty($body) ) return sttv_rest_response( 'signupError', 'Request body cannot be empty', 200 );

        return sttv_rest_response( 'signupSuccess', 'Congrats!', 200, wp_get_current_user() );

        /* return sttv_stripe_errors(function() use ($body) {
            $customer = $create_invoice = $cid = $login = $items = $user = $plan = false;
            $items = $courseids = [];

            $cus = $body['customer'];
            $dotrial = isset($cus['options']['doTrial']) && $cus['options']['doTrial'];
            $priship = isset($cus['options']['priorityShip']) && $cus['options']['priorityShip'];
            $mailinglist = isset($cus['options']['mailinglist']) && $cus['options']['mailinglist'];
            
            extract($cus['account']);
            $firstname = ucfirst(strtolower($firstname));
            $lastname = ucfirst(strtolower($lastname));
            $email = strtolower($email);
            $fullname = $firstname.' '.$lastname;

            if ( !is_email( $email ) ) return sttv_rest_response( 'signupError', 'Email cannot be empty or blank, and must be a valid email address.', 200 );
    
            if ( email_exists( $email ) ) return sttv_rest_response( 'signupError', 'Email address is already in use. Is this you? ', 200 );

            $creds = [
                'user_pass' => $password,
                'user_email' => $email,
                'first_name' => $firstname,
                'last_name' => $lastname,
                'display_name' => $fullname,
                'user_login' => $email,
                'show_admin_bar_front' => 'false'
            ];
            $user_id = wp_insert_user($creds);

            if ( is_wp_error( $user_id ) ) {
                return sttv_rest_response(
                    'signupError',
                    'Cannot update your account. Please check back again later.',
                    200,
                    [ 'data' => $user_id ]
                );
            }

            $login = wp_set_current_user($user_id);

            $customer = \Stripe\Customer::create([
                'description' => $fullname,
                'email' => $email,
                'source' => $cus['token'] ?: null,
                'coupon' => $body['pricing']['coupon']['id'] ?: null,
                'shipping' => $cus['shipping'],
                'metadata' => [ 'wp_id' => $user_id ]
            ]);
            
            //Begin Order Processing
            $order = \Stripe\Subscription::create([
                'customer' => $customer->id,
                "items" => [
                    [
                        'plan' => $body['plan']['id']
                    ]
                ],
                'cancel_at_period_end' => !$dotrial,
                'metadata' => [
                    'checkout_id' => $body['session']['id'],
                    'wp_id' => $user_id,
                    'priship' => $priship
                ],
                'trial_period_days' => $dotrial ? 5 : 0
            ]);

            $token = new \STTV\JWT( $user, $skiptrial ? DAY_IN_SECONDS*30 : DAY_IN_SECONDS*5 );
            sttv_set_auth_cookie($token->token);

            return sttv_rest_response(
                'checkoutSuccess',
                'Thank you for signing up! You will be redirected shortly.',
                200,
                [
                    'response' => $order
                ]
            );
        }); */
    }

    public function sttv_parameter_checker( WP_REST_Request $request ) {
        $pars = $request->get_params();

        if ( isset( $pars['coupon'] ) && !empty($pars['coupon']) ) {
            return $this->check_coupon( sanitize_text_field($pars['coupon']), sanitize_text_field($pars['sig']) );
        } elseif ( isset( $pars['tax'] ) && !empty($pars['tax']) ) {
            return $this->check_zip( sanitize_text_field($pars['tax']) );
        } else {
            return sttv_rest_response( 'bad_request', 'Valid parameters are required to use this method/endpoint combination. Only one parameter is allowed per request, and parameters must have value.', 400 );
        }
    }

    public function stCancellations( WP_REST_Request $request ) {
        $body = json_decode($request->get_body(),true);
        $user = wp_get_current_user();
        $umeta = get_user_meta( $user->ID, 'sttv_user_data', true );

        if ( !isset($body['subscription']) ) return sttv_rest_response( 'signupError', 'A valid subscription id must be provided to cancel a subscription or trial.', 200 );

        if ( $umeta['user']['subscription'] !== $body['subscription'] ) return sttv_rest_response(
            'signupError',
            'Something went wrong on our end. No action has been taken.',
            200,
            [
                'additional' => 'Subscription ID provided did not match the user subscription on file.',
                'meta_sub_id' => $umeta['user']['subscription'],
                'provided_id' => $body['subscription']
            ] 
        );

        try {
            $sub = \Stripe\Subscription::retrieve($body['subscription']);

            switch($body['action']) {
                case 'trial':
                    /* return sttv_rest_response(
                        'signupSuccess',
                        'Trial cancelled',
                        200,
                        ['extra'=>$body['subscription']]
                    ); */
                    $sub->trial_end = 'now';
                    $sub->save();
                break;
                case 'subscription':
                    /* return sttv_rest_response(
                        'signupSuccess',
                        'Subscription cancelled',
                        200
                    ); */
                    $sub->metadata->cancelled = 'manual';
                    $sub->save();
                    $sub->cancel();
                break;
                default:
                    return sttv_rest_response( 'signupError', 'A valid cancellation action must be passed with this request.', 200 );
            }

            return sttv_rest_response(
                'signupSuccess',
                'Your request has been processed.',
                200,
                [
                    'response' => $sub
                ]
            );
        } catch (\Exception $e) {
            return sttv_rest_response(
                'signupError',
                'There was an error with Stripe.',
                200,
                [
                    'error' => $e
                ]
            );
        }
    }

    private function check_coupon( $coupon, $sig ) {
        if ( empty( $coupon ) ) return sttv_rest_response( 'bad_request', 'Coupon cannot be empty or blank.', 400 );
        try {
            $coupon = \Stripe\Coupon::retrieve( $coupon );
            if ( !$coupon->valid ) return sttv_rest_response( 'signupError', 'Expired coupon', 200 );

            $amt = ($coupon->amount_off > -1) ? '$'.$coupon->amount_off : $coupon->percent_off.'%';
            return sttv_rest_response( 'coupon_valid', 'Valid coupon', 200, [ 'update' => ['id' => $coupon->id, 'value' => $amt ]] );
        } catch ( \Exception $e ) {
            $sig = base64_decode($sig);
            list($UA,$platform,$product) = explode('|',$sig);
            $signature = [
                'ip' => $_SERVER['REMOTE_ADDR'],
                'fwd' => $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '',
                'ua' => $UA,
                'platform' => $platform,
                'product' => $product
            ];
            return sttv_rest_response( 'signupError', $e->getJsonBody()['error']['message'], 200);
        }
    }

    private function check_zip( $zip ) {
        $this->set_tax( $zip );
        $msg = ($this->tax > 0) ? "CA tax ($this->tax%)" : "";

        return sttv_rest_response( 'checkout_tax', $msg, 200, [ 'update' => ['id' => $msg, 'value' => $this->tax ]]);
    }

    private function set_tax( $zip ) {
        //set tax rate based on postal code
        if ( in_array( $zip, $this->zips->losangeles ) ) {
            $this->tax = 9.5;
        } else {
            foreach ( $this->zips as $array ) {
                if ( in_array( $zip, $array ) ) {
                    $this->tax = 7.5;
                    break;
                }
            }
        }
    }
}
