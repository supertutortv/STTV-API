<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class MultiUserREST extends WP_REST_Controller {

    private $countrydd;

    private $price_table;

    /**
     * Instantiates the Multi User REST class.
     *
     * @since 1.4.0
     */
    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'mukey_endpoint' ] );

        $this->countrydd = get_option('sttv_country_options');
        if (!$this->countrydd) {
            $this->countrydd = wp_remote_get('https://gist.githubusercontent.com/enlightenedpie/888ba7972fa617579c374e951bd7eab9/raw/b987e55ddc4cde75f50298559e3a173a132657af/gistfile1.txt');
            update_option('sttv_country_options',$this->countrydd);
        }

        $this->price_table = get_option( 'sttv_mu_pricing_table' );
    }

    /**
     * Sets up the 'multi-user' endpoint and its transport methods.
     *
     * @since 1.4.0
     */
    public function mukey_endpoint() {
        register_rest_route( STTV_REST_NAMESPACE , '/multi-user', [
            [
                'methods' => WP_REST_Server::ALLMETHODS,
                'callback' => [ $this, 'init' ],
                'permission_callback' => 'sttv_verify_rest_nonce',
                'args' => [
                    'key' => [
                        'required' => false,
                        'type' => 'string',
                        'description' => 'Can be a mukey or user id'
                    ]
                ]
            ]
        ]);
    }

    /**
     * Catch-all class method for routing the request based on transport method. All request methods are passed here.
     *
     * @since 1.4.0
     *
     * @param WP_REST_Request $request The current request, an instance of WP_REST_Request
     * 
     * @return WP_REST_Response All matching methods and the default case return an instance of WP_REST_Response
     *
     */
    public function init( WP_REST_Request $request ) {
		switch ( $request->get_method() ) {
			case 'GET' :
				return $this->roll_key( $request[ 'key' ] );
			case 'POST' :
                return $this->multi_user_verify( $request );
			case 'PUT' :
				return $this->multi_user_keygen( $request );
            case 'PATCH' :
                return $this->reset_key( $request[ 'key' ] );
			case 'DELETE' :
				break;
			default:
                return sttv_rest_invalid_method( $request->get_method() );
        }
    }

    /**
     * Deletes the supplied multi-user master invitation key and generates new a new one.
     *
     * @since 1.4.0
     *
     * @param string $key Existing MD5 hashed invitation key.
     * @return string|null Returns a new MD5 hash string from class MultiUserPermissions, null if hash failed
     *
     */
    private function roll_key( $key ) {
        return (new MultiUserPermissions( $key ))->keygen()->get_current_key();
    }

    /**
     * Resets the supplied multi-user student key to default status and demotes the student to basic account privileges.
     *
     * @since 1.4.0
     *
     * @param string $key Multi-user student key.
     * @return WP_REST_Response Includes the reset key data.
     *
     */
    private function reset_key( $token ) {
        $k = new MultiUser( $token );
        $key = $k->get_current_key();
        if ( 0 === $key[$token]['active_user'] ) {
            return false;
        }

        get_userdata( $key[$token]['active_user'] )->set_role( sttv_default_role() );
        return sttv_rest_response(
            'key_reset',
            $token.' was reset to default status.',
            200,
            [ 'key' => $k->reset_key() ]
        );
    }

    private function multi_user_keygen( WP_REST_Request $req ) {
        $params = json_decode($req->get_body(),true);
        $mu = new MultiUser( $params[ 'user' ], $params[ 'course' ] );
        $keys = $mu->keygen( $params['qty'] );
        $msg = "\r\n";

        foreach ( $keys as $key ) {
            $msg .= $key."\r\n";
        }

        $saved = get_user_meta( $params[ 'user' ], 'mu_keys' ) ?: [];
        update_user_meta( $params[ 'user' ], 'mu_keys', array_merge( $saved, $keys ) );

        wp_mail(
            $params[ 'email' ],
            'Your generated multi-user keys',
            "The keys below were generated for you. Thank you for your purchase! Sign into your SupertutorTV account to see more info on the keys, including their active status and expiration dates.".$msg,
            ['Bcc: info@supertutortv.com']
        );

        return $keys;
    }

    public function multi_user_verify( WP_REST_Request $req ) {
        $params = json_decode($req->get_body(),true);
        if ( !isset( $params[ 'mukey' ] ) ) {
            return sttv_rest_response( 'null_key', 'You must provide an invitation code with this request.', 400 );
        }

        if ( !isset( $params[ 'email' ] ) ) {
            return sttv_rest_response( 'null_email', 'You must provide the email associated with the invitation code.', 400 );
        }

        $mup = new MultiUserPermissions( $params[ 'mukey' ] );

        if ( !$mup->verify_key( $params[ 'email' ] )->is_valid ) {
            return sttv_rest_response( 'invalid_key', 'The invitation code provided is invalid.', 403 );
        }

        $usekey = $mup->usekey()->output;
        ob_start();

        sttv_get_template('checkout','checkout',[
            'countrydd' => $this->countrydd,
            'user' => $mup->get_current_user()
        ]);

        return sttv_rest_response(
            'success',
            'Permission granted.',
            200,
            [
                'data' => [
                    'id' => $mup->get_current_key(),
                    'type' => 'multi-user',
                    'price' => $this->price_table[ $params[ 'license' ][ 'id' ] ][ $params[ 'license' ][ 'qty' ] ],
                    'qty' => $params[ 'license' ][ 'qty' ],
                    'course_id' => $params[ 'license' ][ 'id' ],
                    'name' => $params[ 'license' ][ 'title' ],
                    'taxable' => false
                ],
                'html' => ob_get_clean()
            ]
        );
    }
}
new MultiUserREST;