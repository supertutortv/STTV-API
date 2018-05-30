<?php

namespace STTV\Courses;

if ( ! defined( 'ABSPATH' ) ) {exit;}

class Trial {

    public static function create() {
        global $wpdb;

        return $wpdb->get_results( "INSERT INTO sttvapp_trial_reference (charge_id,wp_id,exp_date) VALUES ('ch_xxxx',1,DATE_ADD(CURRENT_TIMESTAMP,INTERVAL 5 DAY))", ARRAY_A );
    }

    public static function delete( $rows = [] ) {
        global $wpdb;
        $results = [];
        foreach ($rows as $row) {
            $results[] = $row['id'];
        }
        $results = implode( ',', $results );
        return $wpdb->get_results( "DELETE FROM sttvapp_trial_reference WHERE id IN ($results)", ARRAY_A );
    }

    public static function cleanup() {
        global $wpdb;

        return $wpdb->get_results( "SELECT * FROM sttvapp_trial_reference WHERE exp_date <= CURRENT_TIMESTAMP", ARRAY_A );
    }
}