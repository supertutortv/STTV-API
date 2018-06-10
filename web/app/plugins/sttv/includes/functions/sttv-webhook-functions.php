<?php

defined( 'ABSPATH' ) || exit;

##############################
##### STTV CUSTOM EVENTS #####
##############################

// trial.expiration.checker
function trial_expiration_checker() {
    global $wpdb;
    $time = time();
    $invs = $wpdb->get_results( "SELECT charge_id FROM sttvapp_trial_reference WHERE exp_date < $time", ARRAY_N );
    return $invs;
}

#########################
##### STRIPE EVENTS #####
#########################

// invoice.created
function invoice_created( $data ) {
    global $wpdb;
    $obj = $data['data']['object'];
    return $wpdb->insert( $wpdb->prefix.'trial_reference',
        [
            'charge_id' => $obj['id'],
            'wp_id' => $obj['metadata']['wp_id'] ?? 1,
            'exp_date' => $obj['due_date']
        ]
    );
}

// invoice.payment_succeeded
function invoice_payment_succeeded( $data ) {
    global $wpdb;
    $id = $data['data']['object']['id'];
    return $wpdb->get_results( "SELECT * FROM sttvapp_trial_reference WHERE charge_id = '$id'");
}