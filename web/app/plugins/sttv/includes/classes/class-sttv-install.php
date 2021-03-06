<?php

namespace STTV;

if ( ! defined( 'ABSPATH' ) ) {exit;}

class Install {

    private static $tables = [
        'mu_keys' => '(
            id int(10) NOT NULL AUTO_INCREMENT,
            mu_key varchar(255) NOT NULL DEFAULT 0,
            root_user int(10) UNSIGNED,
            active_user int(10) UNSIGNED,
            date_created int UNSIGNED,
            date_activated int UNSIGNED,
            date_expires int UNSIGNED,
            course_id int(10) UNSIGNED,
            PRIMARY KEY (id),
            UNIQUE KEY (mu_key)
        )',
        'sub_reference' => '(
            id int(10) NOT NULL AUTO_INCREMENT,
            invoice_id tinytext,
            exp_date int UNSIGNED,
            wp_id int(10) UNSIGNED,
            is_trialing boolean NOT NULL DEFAULT 0,
            retries tinyint(1) UNSIGNED NOT NULL DEFAULT 0,
            is_trash boolean NOT NULL DEFAULT 0,
            UNIQUE KEY id (id)
        )',
        'course_udata' => '(
            id int(10) NOT NULL AUTO_INCREMENT,
            wp_id int(10) UNSIGNED,
            udata_type tinytext,
            udata_timestamp int UNSIGNED,
            udata_id tinytext,
            udata_name tinytext,
            udata_thumb tinytext,
            udata_path tinytext,
            udata_test tinytext,
            UNIQUE KEY id (id),
            INDEX wp_id (wp_id)
        )'
    ];

    private static $roles = [
        'teacher' => [ 'multi-user_teacher' => true, 'multi-user' => true ],
        'student' => []
    ];

    private static $dirs = [
        STTV_CACHE_DIR,
        STTV_RESOURCE_DIR,
        STTV_LOGS_DIR,
        STTV_CRON_DIR . 'logs'
    ];

    public static function install() {
		if ( 'yes' === get_transient( 'sttv_installing' ) ) return;
		set_transient( 'sttv_installing', 'yes', 10 );

        self::options();
        self::tables();
        self::roles();
        self::dirs();
    }

    public static function uninstall() {
        delete_transient( 'sttv_installing' );
    }

    private static function options() {
        // all california zip codes
        $zips = wp_remote_get('https://gist.githubusercontent.com/enlightenedpie/99139b054dd9e4ad3f81689e2326d198/raw/69b654b47a01d2dc9e9ac34816c05ab5aa9ad355/ca_zips.json')['body'];
        update_option( 'sttv_ca_zips', $zips, true );

        // <select> elements for all countries with country code values
        $countrydd = wp_remote_get('https://gist.githubusercontent.com/enlightenedpie/888ba7972fa617579c374e951bd7eab9/raw/426359f78a9074b9e42fb68c30a583e8997736fe/gistfile1.txt')['body'];
        update_option( 'sttv_country_options', $countrydd, true );

        // crytpo dictionary, just a bunch of random words
        $crypto = wp_remote_get( 'https://raw.githubusercontent.com/first20hours/google-10000-english/master/google-10000-english-usa.txt')['body'];
        update_option( 'sttv_crypto_dictionary', $crypto, true );

    }

    private static function tables() {
        global $wpdb;

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

        foreach ( self::$tables as $name => $statement ) {
            $collate = $wpdb->get_charset_collate();
            $sql = "CREATE TABLE IF NOT EXISTS ".$wpdb->prefix.$name." $statement $collate;";
            dbDelta( $sql );
        }
    }

    private static function roles() {
        foreach ( self::$roles as $role => $caps ) {
            add_role( $role, ucwords( str_replace('_',' ',$role ) ), $caps );
        }
    }

    private static function dirs() {
        foreach ( self::$dirs as $dir ) {
            if ( ! is_dir( $dir ) ) {
                mkdir( $dir, 0777, true );
            }
        }
    }
}