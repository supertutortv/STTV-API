<?php
if ( ! defined( 'ABSPATH' ) ) exit;
/**
 * SupertutorTV checkout class.
 *
 * Properties, methods, and endpoints for the frontend checkout form to interact with.
 *
 * @class 		STTV_Checkout
 * @version		1.4.0
 * @package		STTV
 * @category	Class
 * @author		Supertutor Media, inc.
 */

class STTV_Checkout extends WP_REST_Controller {

    private $zips = [];

    private $countrydd = '';

    private $tax = 0;

    private $timestamp;

    const BOOK_PRICE = 2500;

    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_checkout_endpoints' ] );

        $zips = get_transient('sttv_ca_zips');
        if ($zips === false) {
            $zips = wp_remote_get('https://gist.githubusercontent.com/enlightenedpie/99139b054dd9e4ad3f81689e2326d198/raw/69b654b47a01d2dc9e9ac34816c05ab5aa9ad355/ca_zips.json')['body'];
            set_transient('sttv_ca_zips',$zips,MONTH_IN_SECONDS);
        }

        $countrydd = get_transient('sttv_country_options');
        if ($countrydd === false) {
            $countrydd = wp_remote_get('https://gist.githubusercontent.com/enlightenedpie/888ba7972fa617579c374e951bd7eab9/raw/426359f78a9074b9e42fb68c30a583e8997736fe/gistfile1.txt')['body'];
            set_transient('sttv_country_options',$countrydd,MONTH_IN_SECONDS);
        }

        $this->zips = json_decode($zips);
        $this->countrydd = $countrydd;
        $this->timestamp = time();
    }

    public function register_checkout_endpoints() {
        register_rest_route( STTV_REST_NAMESPACE , '/checkout', [
            [
                'methods' => 'GET',
                'callback' => [ $this, 'sttv_parameter_checker' ],
                'permission_callback' => 'sttv_verify_rest_nonce',
                'args' => [
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
                    ],
                    'uid' => [
                        'required' => false,
                        'type' => 'string',
                        'description' => 'Request a generated unique id'
                    ]
                ]
            ],
            [
                'methods' => 'POST',
                'callback' => [ $this, 'sttv_checkout' ],
                'permission_callback' => 'sttv_verify_rest_nonce',
            ]
        ]);
    }

    public function sttv_parameter_checker( WP_REST_Request $request ) {
        $pars = $request->get_params();

        if ( isset( $pars['email'] ) ) {
            return $this->check_email( sanitize_email($pars['email']) );
        } elseif ( isset( $pars['coupon'] ) ) {
            return $this->check_coupon( sanitize_text_field($pars['coupon']) );
        } elseif ( isset( $pars['zip'] ) ) {
            return $this->check_zip( sanitize_text_field($pars['zip']) );
        } elseif ( isset( $pars['sid'] ) ) {
            return (new MultiUserPermissions('1c74e69ef1f4f0388bc6da713a599142'))->roll_key()->get_current_key();
        } else {
            return $this->checkout_generic_response( 'bad_request', 'Valid parameters are required to use this method/endpoint combination. Only one parameter is allowed per request, and parameters must have value.', 400 );
        }
    }

    public function sttv_checkout( WP_REST_Request $request ) {
        $body = json_decode($request->get_body(),true);
        
        if ( empty($body) ){
            return $this->checkout_generic_response( 'bad_request', 'Request body cannot be empty', 400 );
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
            return $this->checkout_generic_response(
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
                return $this->checkout_generic_response(
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

            return $this->checkout_generic_response(
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
            return $this->checkout_generic_response(
                'registration_error',
                'There was an error setting up your account. If you are an existing user, please log in first and try again..',
                400,
                $student
            );
        }

    }

    private function _checkout( $body ){

        //set tax rate based on postal code
        if ( in_array( $body['shipping_pcode'], $this->zips->losangeles ) ) {
            $this->tax = 9.5;
        } else {
            foreach ( $this->zips as $array ) {
                if ( in_array( $body['shipping_pcode'], $array ) ) {
                    $this->tax = 7.5;
                    break;
                }
            }
        }
        $body['taxrate'] = $this->tax;

        $order = \STTV\Order::create( $body );

        if ( isset( $order['error'] ) ) {
            return $this->checkout_generic_response(
                'error',
                'There was an error. See the error response for more information.',
                420,
                $order
            );
        }

        if ( isset( $body['mailinglist'] ) && $body['mailinglist'] == 'on' ) {
            sttv_mailinglist_subscribe( $body['email'], $body['firstname'], $body['lastname'] );
        }

        return $this->checkout_generic_response(
            'success',
            'Success! Thank you for your purchase, you will be redirected to your account shortly.',
            200,
            [
                'order' => $order,
                'cart' => $body['cart']
            ]
        );
        
    }

    private function checkout_init( $body ) {
        // save cart in db

        ob_start();

        sttv_get_template('checkout','checkout',[
            'countrydd' => $this->countrydd,
            'user' => wp_get_current_user(),
            'type' => 'checkout'
        ]);

        return $this->checkout_generic_response(
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
        } catch (Exception $e) {
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
        
        $tax = 0;
        $msg = '';

        if ( in_array( $zip, $this->zips->losangeles ) ) {
            $tax = 9.5;
            $msg = "CA tax ($tax%)";
        } else {
            foreach ($this->zips as $array) {
                if ( in_array( $zip, $array ) ) {
                    $tax = 7.5;
                    $msg = "CA tax ($tax%)";
                    break;
                }
            }
        }
        return $this->checkout_generic_response( 'checkout_tax', $msg, 200, [ 'tax' => $tax ] );
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
new STTV_Checkout;