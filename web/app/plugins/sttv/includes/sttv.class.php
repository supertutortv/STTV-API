<?php
if ( ! defined( 'ABSPATH' ) ) {exit;}

#####################################
##### STTV INITIALIZATION CLASS #####
#####################################

final class STTV {

    protected static $_instance = null;

    public $restauth = 'wp_rest';

    public static function instance() {
        add_action( 'print_test', function() {
            print WP_CONTENT_DIR;
        });

		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
    }
    
}