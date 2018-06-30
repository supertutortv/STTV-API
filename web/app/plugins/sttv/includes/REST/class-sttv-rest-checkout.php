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

    const BOOK_PRICE = 2500;

    public function __construct() {

        $zips = get_option( 'sttv_ca_zips' );

        $countrydd = get_option( 'sttv_country_options' );

        $this->zips = json_decode( $zips );
        $this->countrydd = $countrydd;
        $this->timestamp = time();
    }

    public function register_routes() {
        $routes = [
			'/checkout' => [
				[
                    'methods' => 'GET',
                    'callback' => [ $this, 'sttv_parameter_checker' ],
                    'permission_callback' => '__return_true',
                    'args' => [
                        'pricing' => [
                            'required' => false,
                            'sanitize_callback' => function( $p ) {
                                return intval($p);
                            }
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
                        'zip' => [
                            'required' => false,
                            'type' => 'string',
                            'description' => 'Postal code to check'
                        ]
                    ]
                ],
                [
                    'methods' => 'POST',
                    'callback' => [ $this, 'sttv_checkout' ],
                    //'permission_callback' => 'sttv_verify_rest_nonce',
                ]
            ]
		];

		foreach ( $routes as $route => $endpoint ) {
			rest_get_server()->register_route( '', $route, $endpoint );
		}
    }

    public function sttv_parameter_checker( WP_REST_Request $request ) {
        $pars = $request->get_params();

        if ( isset( $pars['pricing'] ) ) {
            return $pars;
        } elseif ( isset( $pars['email'] ) ) {
            return $this->check_email( sanitize_email($pars['email']) );
        } elseif ( isset( $pars['coupon'] ) ) {
            return $this->check_coupon( sanitize_text_field($pars['coupon']) );
        } elseif ( isset( $pars['zip'] ) ) {
            return $this->check_zip( sanitize_text_field($pars['zip']) );
        } else {
            return sttv_rest_response( 'bad_request', 'Valid parameters are required to use this method/endpoint combination. Only one parameter is allowed per request, and parameters must have value.', 400 );
        }
    }

    public function sttv_checkout( WP_REST_Request $request ) {
        return $request->get_headers();

        $body = json_decode($request->get_body(),true);
        
        if ( empty($body) ){
            return sttv_rest_response( 'bad_request', 'Request body cannot be empty', 400 );
        }

        $body = sttv_array_map_recursive( 'rawurldecode', $body );
        $body = sttv_array_map_recursive( 'sanitize_text_field', $body );

        if ( isset($body['init']) && $body['init'] ) {
            return $this->checkout_init( $body );
        }

        if ( isset($body['muid']) ) {
            return $this->_mu_checkout( $body );
        }

        return $this->_checkout( $body );
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
        try {
            $userdata = [
                'user_login' => $body['email'],
                'user_pass' => $body['password'],
                'user_email' => $body['email'],
                'first_name' => $body['firstname'],
                'last_name' => $body['lastname'],
                'display_name' => $body['firstname'].' '.$body['lastname'],
                'show_admin_bar_front' => 'false',
                'role' => 'student'
            ];
    
            $user_id = wp_insert_user( $userdata );

            $customer = new \STTV\Checkout\Customer( 'create', [
                'description' => $body['firstname'].' '.$body['lastname'],
                'source' => $body['source'] ?: null,
                'email' => $body['email'],
                'coupon' => $body['coupon'] ?: null,
                'metadata' => [ 'wp_id' => $user_id ],
                'shipping' => [
                    "name" => "shipping",
                    "address" => [
                        "line1" => $body['shipping_address1'],
                        "line2" => $body['shipping_address2'],
                        "city" => $body['shipping_city'],
                        "state" => $body['shipping_state'],
                        "postal_code" => $body['shipping_pcode'],
                        "country" => $body['shipping_country']
                    ],
                    'phone' => $body['phone']
                ]
            ]);
            $customer = $customer->response();
    
            if ( is_wp_error( $user_id ) ) {
                $customer->delete();
                return sttv_rest_response(
                    'user_insert_error',
                    'There was an error adding you as a user. Please check your registration form and try again.',
                    400,
                    [ 'data' => $user_id ]
                );
            }
            
            //Begin Order Processing
            $course = get_post_meta( $body['course'], 'sttv_course_data', true );
        
            $items = [
                [
                    'customer' => $customer['id'],
                    'currency' => 'usd',
                    'amount' => $course['pricing']['price'],
                    'description' => $course['name'],
                    'discountable' => true
                ]
            ];

            $this->set_tax( $body['shipping_pcode'] );

            if ( $this->tax > 0 ) {
                $items[99] = [
                    'customer' => $customer['id'],
                    'amount' => round( $course['pricing']['taxable_amt'] * ( $this->tax / 100 ) ),
                    'currency' => 'usd',
                    'description' => 'Sales tax',
                    'discountable' => false
                ];
            }

            if ( $body['shipping'] ) {
                $items[100] = [
                    'customer' => $customer['id'],
                    'amount' => 1285,
                    'currency' => 'usd',
                    'description' => 'Priority Shipping',
                    'discountable' => false
                ];
            }

            $trial = ($body['trial']) ? $course['pricing']['trial_period'] : 0;
            $order = new \STTV\Checkout\Order( 'create', [
                'customer' => $customer['id'],
                'trial' => $trial,
                'metadata' => [
                    'wp_id' => $user_id,
                    'course' => $course['id'],
                    'start' => time(),
                    'end' => time() + (MONTH_IN_SECONDS * 6)
                ],
                'items' => $items
            ]);
            $response = $order->response();
            //$response = ($body['trial']) ? $order : $order->pay();

            if ( ! is_wp_error( wp_signon( [
                'user_login'    => $body['email'],
                'user_password' => $body['password'],
                'remember'      => true
            ], is_ssl() ) ) ) {
                return sttv_rest_response(
                    'checkout_success',
                    'Thank you for signing up! You will be redirected shortly.',
                    200,
                    [
                        'user' => get_userdata( $user_id ),
                        'data' => $response
                    ]
                );
            }

        } catch ( \Exception $e ) {
            $err = $e->getJsonBody()['error'];
            switch ( $err['code'] ) {
                case 'resource_missing':
                case 'card_declined':
                    if ( ( $err['param'] == 'coupon' || $err['param'] == 'source' ) || isset($err['decline_code']) ) {
                        require_once( ABSPATH.'wp-admin/includes/user.php' );
                        wp_delete_user( $user_id );
                    }
                break;
            }
            return sttv_rest_response(
                'stripe_error',
                'There was an error',
                403,
                [ 'data' => $e->getJsonBody() ]
            );
        }
    }

    private function checkout_init( $body ) {
        // save cart in db

        ob_start();

        sttv_get_template('checkout','checkout',[
            'countrydd' => $this->countrydd,
            'user' => wp_get_current_user(),
            'type' => 'checkout'
        ]);

        return sttv_rest_response(
            'checkout',
            'Here\'s your checkout, bitch!',
            200,
            [ 
                'data' => $body,
                'html' => ob_get_clean()
            ]
        );
    }

    private function check_email( $email = '' ) {
        if ( !is_email( $email ) ) {
            return $this->checkout_generic_response( 'bad_request', 'Email cannot be empty or blank, and must be a valid email address.', 400 );
        }
        
        if ( wp_get_current_user()->user_email === $email ) {
            return $this->checkout_generic_response( 'email_current_user', 'Email address is the same as the currently logged in user', 200 );
        }

        if ( email_exists( $email ) ) {
            return $this->checkout_generic_response( 'email_taken', 'Email address is already in use', 200 );
        }
        
        return $this->checkout_generic_response( 'email_available', 'Email address available', 200 );
    }

    private function check_coupon( $coupon = '' ) {
        if ( empty( $coupon ) ) {
            return $this->checkout_generic_response( 'bad_request', 'Coupon cannot be empty or blank.', 400 );
        }
        try {
            $coupon = \Stripe\Coupon::retrieve( $coupon );
            if ( $coupon->valid ) {
                return $this->checkout_generic_response( 'coupon_valid', 'valid coupon', 200, [
                    'percent_off' => $coupon->percent_off ?? 0,
                    'amount_off' => $coupon->amount_off ?? 0,
                    'id' => $coupon->id
                ]);
            } else {
                return $this->checkout_generic_response( 'coupon_expired', 'expired coupon', 200 );
            }
        } catch ( \Exception $e ) {
            $body = $e->getJsonBody();
            return $this->checkout_generic_response( 'coupon_invalid', 'invalid coupon', 200, [
                'error' => $body['error']
            ]);
        }
    }

    private function check_zip( $zip = '' ) {
        if ( empty( $zip ) ) {
            return $this->checkout_generic_response( 'bad_request', 'ZIP/Postal code cannot be empty or blank.', 400 );
        }

        $this->set_tax( $zip );
        $msg = ($this->tax > 0) ? "CA tax ($this->tax%)" : "";

        return $this->checkout_generic_response(
            'checkout_tax',
            $msg,
            200,
            [ 'tax' => $this->tax ]
        );
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

    public function checkout_origin_verify( WP_REST_Request $request ) {
        return true;
        return !!wp_verify_nonce( $request->get_header('X-WP-Nonce'), STTV_REST_AUTH );
    }

    private function checkout_generic_response( $code = '', $msg = '', $status = 200, $extra = [] ) {
        $data = [
            'code'    => $code,
            'message' => $msg,
            'data'    => [ 
                'status' => $status
            ]
        ];
        $data = array_merge($data, (array) $extra);
        return new WP_REST_Response( $data, $status );
    }
}