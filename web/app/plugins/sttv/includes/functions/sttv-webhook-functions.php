<?php

defined( 'ABSPATH' ) || exit;

##############################
##### STTV CUSTOM EVENTS #####
##############################

// trial.expiration.checker
function trial_expiration_checker() {
    global $wpdb;
    $time = time();
    $returned = [];

    //Invoices
    $invs = $wpdb->get_results( 
        $wpdb->prepare( "SELECT invoice_id FROM sttvapp_trial_reference WHERE exp_date < %d AND active = %d", [ $time, 1 ] )
    , ARRAY_A );

    if ( empty( $invs ) ) {
        $returned = false;
    } else {
        $returned[] = $invs;
    }

    foreach ( $invs as $inv ) {
        try {
            $pay = \Stripe\Invoice::retrieve( $inv['invoice_id'] );
            $pay->pay();
        } catch ( Exception $e ) {
            continue;
        }
    }

    // Garbage Collection
    $garbage = $wpdb->get_results(
        $wpdb->prepare( "SELECT invoice_id,wp_id,active FROM sttvapp_trial_reference WHERE exp_date < %d", [ $time ] )
    , ARRAY_A );

    if ( !empty( $garbage ) ) {
        foreach ( $garbage as $g ) {
            if ( $g['wp_id'] > 1 && !$g['active'] ) {
                $umeta = get_user_meta( $g['wp_id'], 'sttv_user_data', true );
                $customer = \Stripe\Customer::retrieve( $umeta['customer'] );
                $customer->delete();
            }
            $wpdb->delete( $wpdb->prefix.'trial_reference',
                [
                    'invoice_id' => $g['invoice_id']
                ]
            );
        }
        $returned[] = $garbage;
    }

    return $returned;
}

#########################
##### STRIPE EVENTS #####
#########################

// customer.created
function customer_created( $data ) {
    $customer = $data['data']['object'];
    return update_user_meta( $customer['metadata']['wp_id'], 'sttv_user_data', [
        'customer' => $customer['id'],
        'uid' => $customer['invoice_prefix'],
        'orders' => [],
        'courses' => []
    ]);
}

// customer.updated
function customer_updated( $data ) {
    return false;
}

// customer.deleted
function customer_deleted( $data ) {
    require_once( ABSPATH.'wp-admin/includes/user.php' );
    $id = ($data['data']['object']['metadata']['wp_id'] == 1) ? 0 : $data['data']['object']['metadata']['wp_id'];
    $user = get_userdata( $id );
    return wp_delete_user( $user->ID );
}

// invoice.created
function invoice_created( $data ) {
    global $wpdb;
    $obj = $data['data']['object'];
    $meta = $obj['metadata'];
    $course = get_post_meta( $meta['course'], 'sttv_course_data', true );
    $user = get_userdata( $meta['wp_id'] );

    $test = strtolower( $course['test'] );

    foreach ( $course['capabilities']['trial'] as $cap ) {
        $user->add_cap( $cap );
    }

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

// invoice.updated
function invoice_updated( $data ) {
    global $wpdb;
    $obj = $data['data']['object'];
    if ( $obj['closed'] === true && $obj['amount_remaining'] > 0 ) {
        return $wpdb->update( $wpdb->prefix.'trial_reference',
            [
                'exp_date' => 0,
                'active' => false
            ],
            [
                'invoice_id' => $obj['id']
            ]
        );
    }
    return false;
}

// invoice.payment_succeeded
function invoice_payment_succeeded( $data ) {
    global $wpdb;

    $meta = $data['data']['object']['metadata'];
    $course = get_post_meta( $meta['course'], 'sttv_course_data', true );
    $user = get_userdata( $meta['wp_id'] );

    $test = strtolower( $course['test'] );

    $user->remove_cap( "course_{$test}_trial" );
    foreach ( $course['capabilities']['full'] as $cap ) {
        $user->add_cap( $cap );
    }

    return $wpdb->update( $wpdb->prefix.'trial_reference',
        [
            'exp_date' => 0,
            'active' => false
        ],
        [
            'invoice_id' => $data['data']['object']['id']
        ]
    );
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
                'exp_date' => 0
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