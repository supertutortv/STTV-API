<?php

namespace STTV\REST;

defined( 'ABSPATH' ) || exit;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * SupertutorTV checkout class.
 *
 * Properties, methods, and endpoints for the frontend checkout form to interact with.
 *
 * @class 		STTV_Checkout
 * @version		2.0.0
 * @package		STTV
 * @category	Class
 * @author		Supertutor Media, inc.
 */

class Checkout extends \WP_REST_Controller {

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
			'/checkout' => [
				[
                    'methods' => 'GET',
                    'callback' => [ $this, 'sttv_parameter_checker' ],
                    'args' => [
                        'pricing' => [
                            'required' => false,
                            'description' => 'Course ID to retrieve pricing'
                        ],
                        'email' => [
                            'required' => false,
                            'type' => 'string',
                            'description' => 'Email to check'
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
                ],
                [
                    'methods' => 'POST',
                    'callback' => [ $this, 'sttv_checkout' ]
                ]
            ]
		];

		foreach ( $routes as $route => $endpoint ) {
			rest_get_server()->register_route( '', $route, $endpoint );
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

    public function sttv_checkout( WP_REST_Request $request ) {
        $body = json_decode($request->get_body(),true);
        
        if ( empty($body) ){
            return sttv_rest_response( 'checkout_null_body', 'Request body cannot be empty', 400 );
        }

        $body = sttv_array_map_recursive( 'rawurldecode', $body );
        $body = sttv_array_map_recursive( 'sanitize_text_field', $body );

        if ( isset($body['muid']) ) {
            return $this->_mu_checkout( $body );
        }

        return $this->_checkout( $body, $request );
    }

    private function _mu_checkout( $body ) {
        $mu = new MultiUser;

        $key = $mu->validate_key( $body['muid'] );

        if ( false === $key ) {
            return sttv_rest_response(
                'invalid_multi-user_token',
                'This multi-user token is invalid or expired. Please contact purchaser of this token if you have reached this in error.',
                403
            );
        }

        $student = get_user_by( 'email', $body['email'] );
        $course = get_post( $key[$body['muid']]['course_id'] );

        if ( false === $student ) {
            $submitted = [
                'first_name' => $body['firstName'],
                'last_name' => $body['lastName'],
                'user_login' => $body['email'],
                'user_email' => $body['email'],
                'user_pass' => $body['password'],
                'show_admin_bar_front' => false
            ];
            $student = wp_insert_user( $submitted );
        }      

        if ( ! is_wp_error( $student ) ) {
            if ( is_int( $student ) ) {
                $student = get_userdata( $student );
            }

            if ( $mu->is_subscribed( $student->ID , $course->ID ) ) {
                return sttv_rest_response(
                    'user_already_subscribed',
                    'This user has already signed up for this course. Please choose a unique user for this key.',
                    400
                );
            }

            $student->add_role( 'multi-user_student' );
            $student->add_role( str_replace( ' ', '_', strtolower( $course->post_title ) ) );

            $meta = [];
            $activated_key = $mu->activate_key( $student->ID );
            foreach ( $activated_key as $k => $v ) {
                $v['id'] = $k;
                $meta[] = $v;
            }
            update_user_meta( $student->ID, 'mu_used_keys', json_encode( $meta ) );

            wp_signon([
                'user_login' => $body['email'],
                'user_password' => $body['password'],
                'remember' => true
            ], is_ssl());

            return sttv_rest_response(
                'subscription_success',
                'Thank you for subscribing! You are being redirected to your account page.',
                200,
                [
                    'data' => [
                        'key' => $activated_key,
                        'redirect' => site_url().'/my-account'
                    ],
                    'html' => '<div style="position:absolute;width:400px;left:50%;top:50%;transform:translate(-50%,-50%);text-align:center"><h4>Thank you for subscribing! You are being redirected to your account page.</h4></div>'
                ]
            );
        } else {
            return sttv_rest_response(
                'registration_error',
                'There was an error setting up your account. If you are an existing user, please log in first and try again..',
                400,
                $student
            );
        }

    }

    private function _checkout( $body ){
        $auth = isset($body['authToken']) ? sttv_verify_web_token($body['authToken']) : false;
        if ($auth instanceof \WP_Error) return $auth;
        
        $customer = $create_invoice = $cid = $login = $items = false;
        $cus = $body['customer'];
        $skiptrial = isset($cus['options']['skipTrial']);
        $priship = isset($cus['options']['priorityShip']);
        $mailinglist = isset($cus['options']['mailinglist']);
        $items = $courseids = [];

        try {
            if (!$auth) {
                $fullname = $cus['firstname'].' '.$cus['lastname'];
                $user_id = wp_insert_user([
                    'user_login' => $body['email']['val'],
                    'user_pass' => $cus['password'],
                    'user_email' => $body['email']['val'],
                    'first_name' => $cus['firstname'],
                    'last_name' => $cus['lastname'],
                    'display_name' => $fullname,
                    'show_admin_bar_front' => 'false',
                    'role' => 'student'
                ]);

                if ( is_wp_error( $user_id ) ) {
                    return sttv_rest_response(
                        'user_insert_error',
                        'There was an error adding you as a user and you have not been charged. Please check your registration form and try again.',
                        200,
                        [ 'data' => $user_id ]
                    );
                }

                $login = wp_set_current_user($user_id);
    
                $customer = (new \STTV\Checkout\Customer( 'create', [
                    'description' => $fullname,
                    'email' => $body['email']['val'],
                    'metadata' => [ 'wp_id' => $user_id ]
                ]))->response();

                $cid = $customer->id;

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
        if ( !is_email( $email ) ) {
            return sttv_rest_response( 'bad_request', 'Email cannot be empty or blank, and must be a valid email address.', 400 );
        }
        
        if ( wp_get_current_user()->user_email === $email ) {
            return sttv_rest_response( 'email_current_user', 'Email address is the same as the currently logged in user', 200, [ 'id' => '', 'value' => '' ] );
        }

        if ( email_exists( $email ) ) {
            return sttv_rest_response( 'email_taken', 'Email address is already in use', 200, [ 'id' => '', 'value' => '' ] );
        }
        
        return sttv_rest_response( 'email_available', 'Email address available', 200, [ 'id' => $email, 'value' => $email ] );
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