<?php

defined( 'ABSPATH' ) || exit;

##############################
##### STTV CUSTOM EVENTS #####
##############################

// trial.expiration.checker
function trial_expiration_checker() {}

//api.duplicate.user
function api_duplicate_user($data) {
    return $data;
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
            'subscription' => '',
            'history' => [],
            'downloads' => [],
            'type' => 'standard',
            'trialing' => false,
            'settings' => [
                'autoplay' => false,
                'dark_mode' => false
            ],
            'userdata' => [
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

function customer_subscription_created( $data ) {
    $obj = $data['data']['object'];
    $meta = $obj['metadata'];
    $user = get_userdata( $meta['wp_id'] );
    $cus = \Stripe\customer::retrieve($obj['customer']);
    $umeta = get_user_meta( $meta['wp_id'], 'sttv_user_data', true );
    $courses = json_decode($obj['plan']['metadata']['courses'],true);

    $umeta['courses'] = $courses;
    $umeta['user']['trialing'] = ( $obj['status'] === 'trialing' );
    $umeta['user']['subscription'] = $obj['id'];
    update_user_meta( $meta['wp_id'], 'sttv_user_data', $umeta );

    $roles = explode('|',$obj['plan']['metadata']['roles']);
    foreach ( $roles as $role ) $user->add_role($role);
    if ( $obj['status'] === 'trialing' )
        $user->add_cap('course_trialing');
    elseif ($obj['status'] === 'active') {
        if ( $meta['priship'] == 'true' ) {
            \Stripe\Charge::create([
                "amount" => 795,
                "currency" => "usd",
                "customer" => $obj['customer'],
                "description" => "Priority shipping for ".$cus['shipping']['name']
            ]);
        }
    }

    $message = 'Welcome';

    switch ($obj['plan']['product']) {
        case 'COMBO':
            $message = 'Welcome to the <b>BEST ACT PREP COURSE EVER</b> and <b>THE BEST SAT PREP COURSE EVER!</b>
            <br/><br/>
            Keep this email for your records. To login to the course platform, go to <a href="https://courses.supertutortv.com/login">courses.supertutortv.com/login</a> and enter your email address and password.
            <br/><br/>
            If you’re in the US, and not on the free limited access trial, we’ll be shipping out your books soon. If you’re international, and not on the free limited access trial, we’ll send you a code for a digital book by the next business day for the ACT series only.
            <br/><br/>
            We recommend you start by taking a full practice test and then logging in to the course to review your answers for each test. For the ACT, you can find link to the first free test, so you can get started right away, here:
            <a href="https://supertutortv.com/actcourseresources">https://supertutortv.com/actcourseresources</a>
            <br/>
            While you’re on that page, you can also access our pacing guides (i.e. to do lists) and ACT spreadsheet so you can start making a study game plan to get the most out of this course.
            <br/><br/>
            For the SAT, We recommend you start by taking a full practice test from the official tests #1-8 and reviewing your answers. You can also find links to all 8 practice tests we offer video explanations for on our website (see SAT tests labelled #1-8):
            <a href="http://supertutortv.com/resources">http://supertutortv.com/resources</a>.
            <br/>
            You can also check out our SAT spreadsheet for a list of what’s on the course to help customize your own study schedule!
            <br/><br/>
            We just launched a new platform, so thanks for your patience as we optimize the course. 
            <br/><br/>
            We love feedback. Should you have any questions or comments always feel free to reach out to us here or at info@supertutortv.com!
            <br/><br/>
            Remember, if you’re on the free limited trial, you’ll only have access to a few videos until you elect to unlock the full course or your five day trial is up and your card is charged.
            <br/><br/>
            Thanks and now it’s time to CRUSH THESE TESTS!
            <br/><br/>
            Brooke';
            break;
        case 'SAT':
            $message = 'Welcome to the <b>BEST SAT PREP COURSE EVER!</b>
            <br/><br/>
            Keep this email for your records. To login to the course platform, go to <a href="https://courses.supertutortv.com/login">courses.supertutortv.com/login</a> and enter your email address and password.
            <br/><br/>
            If you’re in the US, and not on the free limited access trial, we’ll be shipping out your book soon. We recommend you start by taking a full practice test from the official tests #1-8 and reviewing your answers. You can also find links to all 8 practice tests we offer video explanations for on our website (see SAT tests labelled #1-8):
            <a href="http://supertutortv.com/resources">http://supertutortv.com/resources</a>
            <br/><br/>
            Get organized! <a href="https://docs.google.com/spreadsheets/d/1t8uNSWfbUQPQhD569OGlM6Poo0B03P3jA0vYu8JFoH8/edit?usp=sharing">Check out our spreadsheet</a> for a list of what’s on the course to help customize your own study schedule!
            <br/><br/>
            We just launched, so thanks for your patience as we optimize the course.  We love feedback. Should you have any questions or comments always feel free to reach out to us here or at info@supertutortv.com!
            <br/><br/>
            Remember, if you’re on the free limited trial, you’ll only have access to a few videos until you elect to unlock the full course or your five day trial is up and your card is charged.
            <br/><br/>
            Thanks and now it’s time to CRUSH THE SAT!
            <br/><br/>
            Brooke';
            break;
        case 'ACT':
            $message = 'Welcome to the <b>BEST ACT PREP COURSE EVER!</b>
            <br/><br/>
            Keep this email for your records. To login to the course platform, go to <a href="https://courses.supertutortv.com/login">courses.supertutortv.com/login</a> and enter your email address and password.
            <br/><br/>
            If you’re in the US, and not on the free limited access trial, we’ll be shipping out your book soon. If you’re international, and not on the free limited access trial, we’ll send you a code for a digital book by the next business day.
            <br/><br/>
            We recommend you start by taking a full practice test and then logging in to the course to review your answers. You can find link to the first free test, so you can get started right away, here:
            <a href="https://supertutortv.com/actcourseresources">https://supertutortv.com/actcourseresources</a>
            <br/><br/>
            While you’re on that page, you can also access our pacing guides (i.e. to do lists) so you can start making a study game plan to get the most out of this course.
            <br/><br/>
            We just launched a new platform, so thanks for your patience as we optimize the course. 
            <br/><br/>
            We love feedback. Should you have any questions or comments always feel free to reach out to us here or at info@supertutortv.com!
            <br/><br/>
            Remember, if you’re on the free limited trial, you’ll only have access to a few videos until you elect to unlock the full course or your five day trial is up and your card is charged.
            <br/><br/>
            Thanks and now it’s time to CRUSH THE ACT!
            <br/><br/>
            Brooke';
            break;
    }

    $welcome = new \STTV\Email([
        'to' => $user->user_email,
        'subject' => 'Welcome!',
        'message' => $message
    ]);
    $welcome->send();

    $newuser = new \STTV\Email([
        'to' => 'info@supertutortv.com',
        'subject' => 'New Customer!',
        'message' => '<pre>'.json_encode($data,JSON_PRETTY_PRINT).'</pre><pre>'.json_encode($cus,JSON_PRETTY_PRINT).'</pre>'
    ]);
    $newuser->send();

    return $umeta;
}

function customer_subscription_updated( $data ) {
    $obj = $data['data']['object'];
    $meta = $obj['metadata'];
    $prev = $data['data']['previous_attributes'];
    $user = get_userdata( $meta['wp_id'] );
    $umeta = get_user_meta( $meta['wp_id'], 'sttv_user_data', true );

    if (!$user) return $user;

    foreach ($prev as $attr => $val) {
        switch($attr) {
            case 'status':
                if ($val === 'trialing' && $obj['status'] === 'active') {
                    $user->remove_cap('course_trialing');
                    $umeta['user']['trialing'] = false;
                    update_user_meta( $meta['wp_id'], 'sttv_user_data', $umeta );
                }
            break;
        }
    }
}

// customer.subscription.deleted
function customer_subscription_deleted( $data ) {
    $obj = $data['data']['object'];
    $roles = explode('|',$obj['plan']['metadata']['roles']);
    $meta = $obj['metadata'];
    $user = get_userdata( $meta['wp_id'] );

    foreach ($roles as $role) {
        $user->remove_role( $role );
    }

    return $roles;
}

// charge.succeeded
function charge_succeeded( $data ) {}

// invoice.created
function invoice_created( $data ) {}

// invoice.updated
function invoice_updated( $data ) {}

// invoice.finalized
function invoice_finalized( $data ) {}

// invoice.payment_succeeded
function invoice_payment_succeeded( $data ) {
    global $wpdb;

    $meta = $data['data']['object']['metadata'];
    $user = get_userdata( $meta['wp_id'] );
    $umeta = get_user_meta( $meta['wp_id'], 'sttv_user_data', true );
    $courses = json_decode($meta['plan '],true);

    foreach ( $courses as $course ) {
        $cmeta = get_post_meta( sttv_id_decode($course), 'sttv_course_data', true );
        $test = strtolower( $course['test'] );
        $user->remove_cap( "course_{$test}_trial" );
    }
    $umeta['user']['data']['orders'][$data['data']['object']['id']] = [];
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
