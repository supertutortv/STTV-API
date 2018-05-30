<?php

namespace STTV;

if ( ! defined( 'ABSPATH' ) ) {exit;}

class Install {

    public static function install() {

		if ( 'yes' === get_transient( 'sttv_installing' ) ) {
			return;
		}
		set_transient( 'sttv_installing', 'yes', MINUTE_IN_SECONDS * 2 );

        self::options();
        self::tables();
        
        flush_rewrite_rules();
    }

    private static function options() {
        // all california zip codes
        $zips = wp_remote_get('https://gist.githubusercontent.com/enlightenedpie/99139b054dd9e4ad3f81689e2326d198/raw/69b654b47a01d2dc9e9ac34816c05ab5aa9ad355/ca_zips.json')['body'];
        update_option( 'sttv_ca_zips', $zips, true );

        // <select> elements for all countries with country code values
        $countrydd = wp_remote_get('https://gist.githubusercontent.com/enlightenedpie/888ba7972fa617579c374e951bd7eab9/raw/426359f78a9074b9e42fb68c30a583e8997736fe/gistfile1.txt')['body'];
        update_option( 'sttv_country_options', $countrydd, true );

        // crytpo dictionary, just a bunch of random words
        $crypto = file( 'https://raw.githubusercontent.com/first20hours/google-10000-english/master/google-10000-english-usa.txt', FILE_IGNORE_NEW_LINES );
        update_option( 'sttv_crypto_dictionary', $crypto, true );

    }

    private static function tables() {
        global $wpdb;

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

        $tables = [
            //'mu_keys' => '',
            'trial_reference' => '(
                id int(10) NOT NULL AUTO_INCREMENT,
                charge_id tinytext,
                exp_date int(10) UNSIGNED,
                wp_id int(10) UNSIGNED,
                UNIQUE KEY id (id)
           )',
            //'sub_ref' => '',
            //'nonce' => ''
        ];

        foreach ( $tables as $name => $statement ) {
            $collate = $wpdb->get_charset_collate();
            $sql = "CREATE TABLE IF NOT EXISTS ".$wpdb->prefix.$name." $statement $collate;";
            dbDelta( $sql );
        }
    }

}