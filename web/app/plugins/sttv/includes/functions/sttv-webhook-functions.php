<?php

defined( 'ABSPATH' ) || exit;

##############################
##### STTV CUSTOM EVENTS #####
##############################

// trial.expiration.checker
function trial_expiration_checker() {
    global $wpdb; $time = time();

    // Garbage Collection
    $garbage_col = $wpdb->get_results( $wpdb->prepare( "DELETE FROM sttvapp_trial_reference WHERE exp_date <= %d AND active = 0", $time ) );

    //Invoices
    $invs = $wpdb->get_results( "SELECT invoice_id FROM sttvapp_trial_reference WHERE exp_date <= $time AND active = 1", ARRAY_A );
    if ( is_empty( $invs ) ) {
        return 'noActionTaken';
    }
    foreach ( $invs as $inv ) {
        try {
            $pay = \Stripe\Invoice::retrieve( $inv['invoice_id'] );
            $pay->pay();
        } catch (Exception $e) {
            continue;
        }
    }
    return true;
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
            'invoice_id' => $obj['id'],
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
    $wpdb->delete( $wpdb->prefix.'trial_reference',
        [
            'invoice_id' => $id
        ]
    );
    return $wpdb->get_results( "SELECT * FROM sttvapp_trial_reference WHERE invoice_id = '$id'");
}

// invoice.payment_failed
function invoice_payment_failed( $data ) {
    global $wpdb;
    $id = $data['data']['object']['id'];
    $record = $wpdb->get_results( "SELECT * FROM sttvapp_trial_reference WHERE invoice_id = '$id'", ARRAY_A );

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
                'invoice_id' => $data['data']['object']['id']
            ],
            [
                '%d',
                '%d'
            ]
        );
    } else {
        $delete = time() + (DAY_IN_SECONDS * 3);
        return $wpdb->update( $wpdb->prefix.'trial_reference',
            [
                'active' => 0,
                'exp_date' => $delete
            ],
            [
                'invoice_id' => $data['data']['object']['id']
            ],
            [
                '%d'
            ]
        );
    }
    return $user;
}