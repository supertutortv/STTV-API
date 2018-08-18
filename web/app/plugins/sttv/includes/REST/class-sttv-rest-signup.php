<?php

namespace STTV\REST;

defined( 'ABSPATH' ) || exit;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * SupertutorTV signup class.
 *
 * Properties, methods, and endpoints for the frontend signup form to interact with.
 *
 * @class 		STTV\REST\Signup
 * @version		2.0.0
 * @package		STTV
 * @category	Class
 * @author		Supertutor Media, inc.
 */

class Signup extends \WP_REST_Controller {

    private $zips = [];

    private $countrydd = '';

    private $tax = 0;

    private $timestamp;

    public function __construct() {
        $this->zips = json_decode( get_option( 'sttv_ca_zips' ) );
        $this->countrydd = get_option( 'sttv_country_options' );
        $this->timestamp = time();
    }

    public function register_routes() {
        $routes = [
            '/init' => [
                [
                    'methods' => 'GET',
                    'callback' => [ $this, 'stSignupForm' ]
                ]
            ],
			'/check' => [
				[
                    'methods' => 'GET',
                    'callback' => [ $this, 'sttv_parameter_checker' ],
                    'args' => [
                        'pricing' => [
                            'required' => false,
                            'type' => 'string',
                            'description' => 'Course pricing'
                        ],
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
            '/account' => [
                [
                    'methods' => 'POST',
                    'callback' => [ $this, 'stSignupAccount' ]
                ]
            ],
            '/pay' => [
                [
                    'methods' => 'POST',
                    'callback' => [ $this, 'sttv_signup' ]
                ]
            ]
		];

		foreach ( $routes as $route => $endpoint ) {
			register_rest_route( 'signup', $route, $endpoint );
		}
    }

    public function sttv_parameter_checker( WP_REST_Request $request ) {
        $pars = $request->get_params();

        if ( isset( $pars['pricing'] ) && !empty($pars['pricing']) ) {
            return $this->_pricing( $pars['pricing'] );
        } elseif ( isset( $pars['email'] ) && !empty($pars['email']) ) {
            return $this->check_email( sanitize_email($pars['email']) );
        } elseif ( isset( $pars['coupon'] ) && !empty($pars['coupon']) ) {
            return $this->check_coupon( sanitize_text_field($pars['coupon']), sanitize_text_field($pars['sig']) );
        } elseif ( isset( $pars['tax'] ) && !empty($pars['tax']) ) {
            return $this->check_zip( sanitize_text_field($pars['tax']) );
        } else {
            return sttv_rest_response( 'bad_request', 'Valid parameters are required to use this method/endpoint combination. Only one parameter is allowed per request, and parameters must have value.', 400 );
        }
    }

    public function stSignupForm( WP_REST_Request $request ) {
        require_once STTV_TEMPLATE_DIR.'checkout.php';
        return sttv_rest_response( 'signup_success', 'ok' , 200, [ 'html' => checkout_template() ]);
    }

    public function stSignupAccount( WP_REST_Request $request ) {
        $verify = sttv_verify_web_token($request);
        $loggedin = is_wp_error($verify) ? !$verify : $verify;

        return sttv_stripe_errors(function() use ($request,$loggedin) {

            extract(json_decode($request->get_body(),true));

            if ( !is_email( $email ) ) return sttv_rest_response( 'signup_error', 'Email cannot be empty or blank, and must be a valid email address.', 200 );
    
            if ( email_exists( $email ) && !$loggedin ) return sttv_rest_response( 'signup_error', 'Email address is already in use. Is this you? <a href="/login">Sign in</a>', 200 );

            $fullname = $firstname.' '.$lastname;
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
                    'signup_error',
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

            return sttv_rest_response(
                'signup_success',
                'Account created',
                200,
                [
                    'update' => [
                        'userID' => $user_id
                    ]
                ]
            );
        });
    }

    public function sttv_checkout( WP_REST_Request $request ) {
        $body = json_decode($request->get_body(),true);
        
        if ( empty($body) ){
            return sttv_rest_response( 'checkout_null_body', 'Request body cannot be empty', 400 );
        }

        $body = sttv_array_map_recursive( 'rawurldecode', $body );
        $body = sttv_array_map_recursive( 'sanitize_text_field', $body );

        return $this->_checkout( $body, $request );
    }

    private function _checkout( $body, $request ){
        $auth = sttv_verify_web_token($request);
        if ($auth instanceof \WP_Error) return $auth;
        
        $customer = $create_invoice = $cid = $login = $items = false;
        $cus = $body['customer'];
        $skiptrial = isset($cus['options']['skipTrial']);
        $priship = isset($cus['options']['priorityShip']);
        $mailinglist = isset($cus['options']['mailinglist']);
        $items = $courseids = [];

        try {
            if (!$auth) {

            } else {
                $login = wp_get_current_user($user_id);
                $cid = get_user_meta(get_current_user_id(),'sttv_user_data',true)['user']['userdata']['customer'];
            }

            $customer = \Stripe\Customer::retrieve($cid);
            $customer->source = $cus['token'] ?: null;
            $customer->coupon = $body['coupon']['val'] ?: null;
            $customer->shipping = $cus['shipping'];
            $customer->save();
            
            //Begin Order Processing
            $this->set_tax( $cus['shipping']['address']['postal_code'] );

            $sublength = $taxable = 0;

            foreach($body['items'] as $item) {
                $course = get_post_meta( sttv_id_decode($item['id']), 'sttv_course_data', true );
                $taxable += $course['pricing']['taxable_amt'];
                $sublength += $course['pricing']['length'];
                $courseids[] = $course['pricing']['id'];
                $items[] = [
                    'customer' => $customer->id,
                    'currency' => 'usd',
                    'amount' => $course['pricing']['price'],
                    'description' => $course['name'],
                    'discountable' => true
                ];
            }

            if ( $this->tax > 0 ) {
                $items[99] = [
                    'customer' => $customer->id,
                    'amount' => round( $taxable * ( $this->tax / 100 ) ),
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
                    'wp_id' => $user_id,
                    'course' => json_encode($courseids),
                    'start' => time(),
                    'end' => time() + (MONTH_IN_SECONDS * $sublength)
                ],
                'items' => $items
            ]);
            $response = $order->response();
            //if ($skiptrial) $order->pay();

            $token = new \STTV\JWT( $login );
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

    private function _pricing( $ids ) {
        $pricing = [];
        $code = $msg = $html = '';
        $courses = json_decode( base64_decode($ids), true);

        require_once STTV_TEMPLATE_DIR.'checkout.php';

        if ( !is_array($courses) ) {
            $code = 'checkout_pricing_course_invalid';
            $msg = 'The course ID provided is invalid. Please try again.';
        } else {
            foreach ($courses as $course) {
                $cmeta = get_post_meta( sttv_id_decode($course), 'sttv_course_data', true );
                unset( $cmeta['pricing']['renewals'] );
                $pricing[] = array_merge($cmeta['pricing'],[
                    'name' => $cmeta['name'],
                    'qty' => 1
                ]);
            }
            $code = 'checkout_pricing_success';
                
            $html = checkout_template();
        }

        return sttv_rest_response(
            $code,
            $msg,
            200,
            [
                'data' => [
                    'pricing' => $pricing,
                    'html' => $html
                ]
            ]
        );
    }

    private function check_email( $email = '' ) {

    }

    private function check_coupon( $coupon, $sig ) {
        if ( empty( $coupon ) ) {
            return sttv_rest_response( 'bad_request', 'Coupon cannot be empty or blank.', 400 );
        }
        try {
            $coupon = \Stripe\Coupon::retrieve( $coupon );
            if ( $coupon->valid ) {
                $amt = ($coupon->amount_off > -1) ? '$'.$coupon->amount_off : $coupon->percent_off.'%';

                return sttv_rest_response( 'coupon_valid', 'Valid coupon', 200, [ 'id' => $coupon->id, 'value' => $amt ] );
            } else {
                return sttv_rest_response( 'coupon_expired', 'Expired coupon', 200, [ 'id' => $coupon->id, 'value' => '0' ] );
            }
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
            return sttv_rest_response( 'coupon_invalid', $e->getJsonBody()['error']['message'], 200, [
                'id' => '',
                'value' => ''
            ]);
        }
    }

    private function check_zip( $zip ) {
        $this->set_tax( $zip );
        $msg = ($this->tax > 0) ? "CA tax ($this->tax%)" : "";

        return sttv_rest_response( 'checkout_tax', $msg, 200, [ 'id' => $msg, 'value' => (string)$this->tax ] );
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