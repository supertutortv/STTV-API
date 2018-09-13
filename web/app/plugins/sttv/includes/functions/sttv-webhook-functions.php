<?php

defined( 'ABSPATH' ) || exit;

##############################
##### STTV CUSTOM EVENTS #####
##############################

// trial.expiration.checker
function trial_expiration_checker() {
    global $wpdb;
    $time = time();
    $ref_table = "{$wpdb->prefix}trial_reference";
    $returned = [];

    // Garbage Collection
    $garbage = $wpdb->get_results(
        $wpdb->prepare( "SELECT * FROM $ref_table WHERE exp_date < %d AND is_trash = %d", [ $time, 1 ] )
    , ARRAY_A );

    if ( !empty( $garbage ) ) {
        foreach ( $garbage as $g ) {
            $umeta = get_user_meta( $g['wp_id'], 'sttv_user_data', true );
            if ( ( $g['exp_date'] > 0 ) && $umeta ) {
                try {
                    $customer = \Stripe\Customer::retrieve( $umeta['customer'] );
                    $customer->delete();
                } catch ( Exception $e ) {
                    $returned[] = $e->getJsonBody();
                    continue;
                }
            }
            
            $wpdb->delete( $ref_table,
                [
                    'invoice_id' => $g['invoice_id']
                ]
            );
        }
        $returned[] = $garbage;
    }

    //Invoices
    $invs = $wpdb->get_results( 
        $wpdb->prepare( "SELECT invoice_id,exp_date FROM $ref_table WHERE exp_date < %d", [ $time ] )
    , ARRAY_A );

    if ( !empty( $invs ) ) {
        foreach ( $invs as $inv ) {
            if ( $inv['exp_date'] !== 0 ) {
                try {
                    $pay = \Stripe\Invoice::retrieve( $inv['invoice_id'] );
                    $pay->pay();
                } catch ( Exception $e ) {
                    continue;
                }
            }
        }
        $returned[] = $invs;
    }

    return $returned ?: false ;
}

#########################
##### STRIPE EVENTS #####
#########################

// customer.created
function customer_created( $data ) {
    global $wpdb;
    $customer = $data['data']['object'];
    $user_id = $customer['metadata']['wp_id'];
    $wpdb->update($wpdb->users, [
        'user_login' => str_replace('cus_','',$customer['id']),
        'user_nicename' => str_replace(' ','-',strtolower($customer['description'])).'-'.str_replace('cus_','',$customer['id'])
    ],
    [
        'ID' => $customer['metadata']['wp_id']
    ]);

    return update_user_meta( $user_id, 'sttv_user_data', [
        'user' => [
            'type' => 'standard',
            'trialing' => 0,
            'settings' => [
                'autoplay' => false,
                'default_course' => '',
                'dark_mode' => false
            ],
            'userdata' => [
                'customer' => $customer['id'],
                'uid' => $customer['invoice_prefix'],
                'orders' => [],
                'login_timestamps' => []
            ]
        ],
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
    return wp_delete_user( $id );
}

// invoice.created
function invoice_created( $data ) {
    $obj = $data['data']['object'];
    $meta = $obj['metadata'];
    $user = get_userdata( $meta['wp_id'] );
    $umeta = get_user_meta( $meta['wp_id'], 'sttv_user_data', true );
    $courses = json_decode($meta['plan'],true);

    foreach ( $courses as $course ) {
        $cmeta = get_post_meta( $course, 'sttv_course_data', true );
        $umeta['courses'][$cmeta['slug']] = [];
        foreach($cmeta['capabilities'] as $cap) $user->add_cap( $cap );
    }
    update_user_meta( $meta['wp_id'], 'sttv_user_data', $umeta );

    global $wpdb;
    return $wpdb->insert( $wpdb->prefix.'trial_reference',
        [
            'invoice_id' => $obj['id'],
            'wp_id' => $obj['metadata']['wp_id'] ?? 0,
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
    $umeta = get_user_meta( $obj['metadata']['wp_id'], 'sttv_user_data', true );

    if ( isset($data['data']['previous_attributes']['hosted_invoice_url']) )
        $umeta['user']['userdata']['orders'][$obj['id']]['invoice_url'] = $obj['hosted_invoice_url'] ?? '';
    if ( isset($data['data']['previous_attributes']['invoice_pdf']) )
        $umeta['user']['userdata']['orders'][$obj['id']]['invoice_pdf'] = $obj['invoice_pdf'] ?? '';

    if ( $obj['closed'] === true && $obj['amount_remaining'] > 0 )
        return $wpdb->update( $wpdb->prefix.'trial_reference',
            [
                'exp_date' => time(),
                'is_trash' => 1
            ],
            [
                'invoice_id' => $obj['id']
            ]
        );
    return false;
}

// invoice.payment_succeeded
function invoice_payment_succeeded( $data ) {
    global $wpdb;

    $meta = $data['data']['object']['metadata'];
    $user = get_userdata( $meta['wp_id'] );
    $umeta = get_user_meta( $meta['wp_id'], 'sttv_user_data', true );
    $courses = json_decode($meta['course'],true);

    foreach ( $courses as $course ) {
        $cmeta = get_post_meta( sttv_id_decode($course), 'sttv_course_data', true );
        $test = strtolower( $course['test'] );
        $user->remove_cap( "course_{$test}_trial" );
    }
    $umeta['user']['data']['orders'][$obj['id']] = [];
    update_user_meta( $meta['wp_id'], 'sttv_user_data', $umeta );

    return $wpdb->update( $wpdb->prefix.'trial_reference',
        [
            'exp_date' => 0,
            'is_trash' => 1
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

    $user = get_userdata( $record[0]['wp_id'] );
    foreach(['course_act_access','course_sat_access'] as $thecap)
        $user->remove_cap( $thecap );

    if ( $record[0]['retries'] < 2 ) {
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
        $delete = time() + (HOUR_IN_SECONDS * 48);
        return $wpdb->update( $wpdb->prefix.'trial_reference',
            [
                'is_trash' => 1,
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