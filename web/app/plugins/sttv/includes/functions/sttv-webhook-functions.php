<?php

defined( 'ABSPATH' ) || exit;

##############################
##### STTV CUSTOM EVENTS #####
##############################

// trial.expiration.checker
function trial_expiration_checker() {
    return 'Hello world!';
}

#########################
##### STRIPE EVENTS #####
#########################

// charge.succeeded
function charge_succeeded( $data ) {

}

// invoice.created
function invoice_created( $data ) {
    return $data;
}

// invoice.payment_succeeded
function invoice_payment_succeeded( $data ) {
    global $wpdb;
    $id = $data['data']['object']['id'];
    return $wpdb->get_results( "SELECT * FROM sttvapp_trial_reference WHERE charge_id = '$id'");
}