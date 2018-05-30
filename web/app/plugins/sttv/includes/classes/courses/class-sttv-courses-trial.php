<?php

namespace STTV\Courses;

if ( ! defined( 'ABSPATH' ) ) {exit;}

class Trial {

    public static function create() {
        global $wpdb;

        return $wpdb->get_results( "INSERT INTO sttvapp_trial_reference (charge_id,wp_id,exp_date) VALUES ('ch_xxxx',1,DATE_ADD(CURRENT_TIMESTAMP,INTERVAL 1 SECOND))" );
    }

    public static function delete() {

    }

    public static function cleanup() {
        global $wpdb;

        return $wpdb->get_results( "SELECT * FROM sttvapp_trial_reference WHERE exp_date <= CURRENT_TIMESTAMP", ARRAY_A );
    }
}