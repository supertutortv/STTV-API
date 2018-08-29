<?php

namespace STTV\REST;

defined( 'ABSPATH' ) || exit;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class Signup extends \WP_REST_Controller {

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
            '/init' => [
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
                        ],
                        'tax' => [
                            'required' => false,
                            'type' => 'string',
                            'description' => 'Postal code to check for tax rate'
                        ]
                    ]
                ]
            ],
            '/account' => $steps,
            '/plan' => $steps,
            '/billing' => $steps,
            '/shipping' => $steps,
            '/pay' => $steps
		];

		foreach ( $routes as $route => $endpoint ) {
			register_rest_route( 'signup', $route, $endpoint );
		}
    }

    public function stSignupInit( WP_REST_Request $request ) {
        ob_start();
        require_once STTV_TEMPLATE_DIR.'signup/account.php';
        return sttv_rest_response( 'signup_success', 'ok' , 200, [ 'html' => ob_get_clean() ]);
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
        $verify = sttv_verify_web_token($request);
        $loggedin = is_wp_error($verify) ? !$verify : $verify;

        return sttv_stripe_errors(function() use ($body,$loggedin) {

            extract($body);
            $firstname = ucfirst(strtolower($firstname));
            $lastname = ucfirst(strtolower($lastname));
            $email = strtolower($email);
            $fullname = $firstname.' '.$lastname;

            if ( !is_email( $email ) ) return sttv_rest_response( 'signupError', 'Email cannot be empty or blank, and must be a valid email address.', 200 );
    
            if ( email_exists( $email ) && !$loggedin ) return sttv_rest_response( 'signupError', 'Email address is already in use. Is this you? <a href="/login">Sign in</a>', 200 );

            $creds = [
                'user_pass' => $password,
                'user_email' => $email,
                'first_name' => $firstname,
                'last_name' => $lastname,
                'display_name' => $fullname,
                'show_admin_bar_front' => 'false'
            ];

            $login = wp_get_current_user();
            if ($login->ID === 0) {
                $creds = $creds + [
                    'user_login' => 'st',
                    'role' => 'student'
                ];
                $user_id = wp_insert_user($creds);
            } else {
                $creds = $creds + [
                    'ID' => $login->ID
                ];
                $user_id = wp_update_user($creds);
            }

            if ( is_wp_error( $user_id ) ) {
                return sttv_rest_response(
                    'signupError',
                    'Cannot update your account. Please check back again later.',
                    200,
                    [ 'data' => $user_id ]
                );
            }

            $login = wp_set_current_user($user_id);

            $customer = (new \STTV\Checkout\Customer( 'create', [
                'description' => $fullname,
                'email' => $email,
                'metadata' => [ 'wp_id' => $user_id ]
            ]))->response();

            ob_start();
            include_once STTV_TEMPLATE_DIR.'signup/plans.php';
            $html = ob_get_clean();

            return sttv_rest_response(
                'signupSuccess',
                'Account created',
                200,
                [
                    'update' => [
                        'id' => $user_id
                    ]
                ]
            );
        });
    }

    private function _plan( $body ) {
        extract($body);
        $meta = get_post_meta(sttv_id_decode($id),'pricing_data',true)[$id];

        ob_start();
        include_once STTV_TEMPLATE_DIR.'signup/billing.php';
        $html = ob_get_clean();

        return sttv_rest_response( 'signup_success', 'Pricing retrieved', 200, [
            'html' => $html,
            'update' => [
                'name' => $meta['name'],
                'price' => $meta['price'],
                'taxable' => $meta['taxable']
            ]
        ]);
    }

    private function _billing( $body ) {
        ob_start();
        include_once STTV_TEMPLATE_DIR.'signup/shipping.php';
        $html = ob_get_clean();

        return sttv_rest_response( 'signup_success', 'Billing saved', 200, [
            'html' => $html,
            'update' => new \stdClass()
        ]);
    }

    private function _shipping( $body ) {
        ob_start();
        include_once STTV_TEMPLATE_DIR.'signup/pay.php';
        $html = ob_get_clean();

        return sttv_rest_response( 'signup_success', 'Shipping saved', 200, [
            'html' => $html,
            'update' => new \stdClass()
        ]);
    }

    private function _pay( $body, $request ) {
        if ( empty($body) ) return sttv_rest_response( 'signup_error', 'Request body cannot be empty', 200 );

        return sttv_stripe_errors(function() use ($body) {
            $customer = $create_invoice = $cid = $login = $items = $user = false;
            $cus = $body['customer'];
            $user = wp_set_current_user($cus['account']['id']);
            $cid = 'cus_'.$user->user_login;
            $skiptrial = isset($cus['options']['skipTrial']);
            $priship = isset($cus['options']['priorityShip']);
            $mailinglist = isset($cus['options']['mailinglist']);
            $items = $courseids = [];

            $customer = \Stripe\Customer::retrieve($cid);
            $customer->source = $cus['token'] ?: null;
            $customer->coupon = $body['pricing']['coupon']['id'] ?: null;
            $customer->shipping = $cus['shipping'];
            $customer->save();
            
            //Begin Order Processing
            $this->set_tax( $cus['shipping']['address']['postal_code'] );

            $plan = get_post_meta( sttv_id_decode($cus['plan']['id']), 'pricing_data', true )[$cus['plan']['id']];
 
            $items[] = [
                'customer' => $customer->id,
                'currency' => 'usd',
                'amount' => $plan['price'],
                'description' => $plan['name'],
                'discountable' => true
            ];

            if ( $this->tax > 0 ) {
                $items[99] = [
                    'customer' => $customer->id,
                    'amount' => round( (int)$plan['taxable'] * ( $this->tax / 100 ) ),
                    'currency' => 'usd',
                    'description' => 'Sales tax',
                    'discountable' => false
                ];
            }

            if ( $priship ) {
                $items[100] = [
                    'customer' => $customer->id,
                    'amount' => 705,
                    'currency' => 'usd',
                    'description' => 'Priority Shipping',
                    'discountable' => false
                ];
            }

            $order = new \STTV\Checkout\Order( 'create', [
                'customer' => $customer->id,
                'trial' => $skiptrial ? 0 : 5,
                'metadata' => [
                    'checkout_id' => $body['id'],
                    'wp_id' => $user->ID,
                    'plan' => json_encode($plan['courses']),
                    'start' => time(),
                    'end' => time() + (MONTH_IN_SECONDS * (int)$plan['length'])
                ],
                'items' => $items
            ]);
            $response = $order->response();
            if ($skiptrial) $order->pay();

            $token = new \STTV\JWT( $user );
            sttv_set_auth_cookie($token->token);

            return sttv_rest_response(
                'checkout_success',
                'Thank you for signing up! You will be redirected shortly.',
                200,
                [
                    'redirect' => 'https://courses.supertutortv.com',
                    'response' => $response
                ]
            );
        });
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

    private function check_coupon( $coupon, $sig ) {
        if ( empty( $coupon ) ) return sttv_rest_response( 'bad_request', 'Coupon cannot be empty or blank.', 400 );
        try {
            $coupon = \Stripe\Coupon::retrieve( $coupon );
            if ( !$coupon->valid ) return sttv_rest_response( 'signup_error', 'Expired coupon', 200 );

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
            return sttv_rest_response( 'signup_error', $e->getJsonBody()['error']['message'], 200);
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