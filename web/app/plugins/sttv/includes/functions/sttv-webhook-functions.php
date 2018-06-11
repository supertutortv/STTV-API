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
    if ( is_null( $invs ) ) {
        return 'noActionTaken';
    }
    foreach ( $invs as $inv ) {
        try {
            $pay = \Stripe\Invoice::retrieve( $inv[0] );
            $pay->pay();
            $wpdb->delete( $wpdb->prefix.'trial_reference',
                [
                    'charge_id' => $inv[0]
                ]
            );
        } catch (Exception $e) {
            continue;
        }
    }
    return [ 'completed' => date('c') ];
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
        ],
        [
            '%s',
            '%d',
            '%d'
        ]
    );
}

// invoice.payment_succeeded
function invoice_payment_succeeded( $data ) {
    global $wpdb;
    $id = $data['data']['object']['id'];
    return $wpdb->get_results( "SELECT * FROM sttvapp_trial_reference WHERE charge_id = '$id'");
}

// invoice.payment_failed
function invoice_payment_failed( $data ) {
    global $wpdb;
    $id = $data['data']['object']['id'];
    $record = $wpdb->get_results( "SELECT * FROM sttvapp_trial_reference WHERE charge_id = '$id'", ARRAY_A );

    $user = wp_set_current_user( $record[0]['wp_id'] );

    if ( $record[0]['retries'] < 3 ) {
        /* $caps = $user->allcaps;
        foreach ( $caps as $cap => $g ) {
            $ret = $cap;
        } */
        return $wpdb->update( $wpdb->prefix.'trial_reference',
            [
                'retries' => ++$record[0]['retries'],
                'exp_date' => time() + 300
            ],
            [
                'charge_id' => $data['data']['object']['id']
            ],
            [
                '%d',
                '%d'
            ]
        );
    } else {
        $wpdb->delete( $wpdb->prefix.'trial_reference',
            [
                'charge_id' => $id
            ]
        );
    }
    return $user->caps;
}