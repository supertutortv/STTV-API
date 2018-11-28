<?php

defined( 'ABSPATH' ) || exit;

##############################
##### STTV CUSTOM EVENTS #####
##############################

// trial.expiration.checker
function trial_expiration_checker() {
    
}

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

    $umeta = [
        'user' => [
            'subscription' => '',
            'history' => [],
            'downloads' => [],
            'type' => 'standard',
            'trialing' => null,
            'settings' => [
                'autoplay' => false,
                'dark_mode' => false
            ],
            'userdata' => [
                'login_timestamps' => []
            ]
        ],
        'courses' => []
    ];
    update_user_meta( $user_id, 'sttv_user_data', $umeta );

    return $umeta;
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
    $umeta = get_user_meta( $meta['wp_id'], 'sttv_user_data', true );
    $courses = json_decode($obj['plan']['metadata']['courses'],true);
    $cus = \Stripe\Customer::retrieve($obj['customer']);
    $plan = $obj['plan'];
    $prod = \Stripe\Product::retrieve($plan['product']);
    $fullname = $user->first_name.' '.$user->last_name;

    $umeta['courses'] = $courses;
    $umeta['user']['trialing'] = $obj['status'] == 'trialing' ? true : false;
    $umeta['user']['subscription'] = $obj['id'];
    update_user_meta( $meta['wp_id'], 'sttv_user_data', $umeta );

    $roles = explode('|',$obj['plan']['metadata']['roles']);
    foreach ( $roles as $role ) $user->add_role($role);

    if ( $obj['status'] === 'trialing' ) {

    }
    elseif ($obj['status'] === 'active') {

        if ( $meta['priship'] == 'true' ) {
            \Stripe\Charge::create([
                "amount" => $obj['plan']['metadata']['priship'] ?? 795,
                "currency" => "usd",
                "customer" => $obj['customer'],
                "description" => "Priority shipping for ".$fullname,
                "shipping" => $cus->shipping
            ]);
        }
    }

    preg_match('/The Best (\w+) Prep Course Ever/gm',$prod->name,$matches);

    $testname = $matches[1];
    $coursename = $prod->name;
    $bookname = $getstarted = '';

    switch ($obj['plan']['product']) {
        case 'COMBO':
            $testname = 'SAT and ACT';
            $coursename = 'The Best SAT and The Best ACT Prep Courses';
            $bookname = 'The Official SAT Study Guide and The Official ACT Prep Guide';
            $getstarted = 'We recommend you start by taking a full practice test and then logging in to the course to review your answers for each test. For the ACT, you can find link to the first free test, so you can get started right away, here:
            <a href="https://supertutortv.com/actcourseresources">https://supertutortv.com/actcourseresources</a>
            <br/>
            While you’re on that page, you can also access our pacing guides (i.e. to do lists) and ACT spreadsheet so you can start making a study game plan to get the most out of this course.
            <br/><br/>
            For the SAT, We recommend you start by taking a full practice test from the official tests #1-8 and reviewing your answers. You can also find links to all 8 practice tests we offer video explanations for on our website (see SAT tests labelled #1-8):
            <a href="http://supertutortv.com/resources">http://supertutortv.com/resources</a>.';
            break;
        case 'SAT':
            $bookname = 'The Official SAT Study Guide';
            $getstarted = 'We recommend you start by taking a full practice test from the official tests #1-8 and reviewing your answers. You can also find links to all 8 practice tests we offer video explanations for on our website (see SAT tests labelled #1-8):
            <a href="http://supertutortv.com/resources">http://supertutortv.com/resources</a>.';
            break;
        case 'ACT':
            $bookname = 'The Official ACT Prep Guide';
            $getstarted = 'We recommend you start by taking a full practice test and then logging in to the course to review your answers for each test. For the ACT, you can find link to the first free test, so you can get started right away, here:
            <a href="https://supertutortv.com/actcourseresources">https://supertutortv.com/actcourseresources</a>
            <br/>
            While you’re on that page, you can also access our pacing guides (i.e. to do lists) and ACT spreadsheet so you can start making a study game plan to get the most out of this course.';
            break;
    }

    $paid = $obj['status'] == 'trialing' ? 'TRIAL' : 'PAID';

    $order = new \STTV\Email\Template([
        'template' => 'new-order',
        'email' => 'supertutortv@gmail.com',
        'name' => 'SupertutorTV Course Orders',
        'subject' => "New Order ($paid) | $fullname - $user->user_email",
        'content' => [
            [
                'name' => 'sub',
                'content' => $obj['id']
            ],
            [
                'name' => 'name',
                'content' => $fullname
            ],
            [
                'name' => 'email',
                'content' => $user->user_email
            ],
            [
                'name' => 'cusid',
                'content' => $obj['customer']
            ],
            [
                'name' => 'wpid',
                'content' => $meta['wp_id']
            ],
            [
                'name' => 'plan',
                'content' => $plan['nickname'].' | '.$plan['product']
            ],
            [
                'name' => 'status',
                'content' => $obj['status']
            ],
            [
                'name' => 'priship',
                'content' => $meta['priship'] == 'true' ? 'yes' : 'no'
            ],
            [
                'name' => 'address',
                'content' => '<pre>'.json_encode($cus->shipping,JSON_PRETTY_PRINT).'</pre>'
            ],
            [
                'name' => 'datetime',
                'content' => date('l, F jS, Y \at h:ia')
            ]
        ]
    ]);

    return new \STTV\Email\Template([
        'template' => 'course-welcome',
        'email' => $user->user_email,
        'name' => $fullname,
        'subject' => 'Welcome to SupertutorTV! (Read this)',
        'content' => [
            [
                'name' => 'fname',
                'content' => $user->first_name
            ],
            [
                'name' => 'coursename',
                'content' => $coursename
            ],
            [
                'name' => 'testname',
                'content' => $testname
            ],
            [
                'name' => 'getstarted',
                'content' => $getstarted
            ],
            [
                'name' => 'bookname',
                'content' => $bookname
            ]
        ]
    ]);
}

// customer.subscription.updated
function customer_subscription_updated( $data ) {
    $obj = $data['data']['object'];
    $meta = $obj['metadata'];
    $prev = $data['data']['previous_attributes'];
    $cus = \Stripe\Customer::retrieve($obj['customer']);
    $user = get_userdata( $meta['wp_id'] );
    $umeta = get_user_meta( $meta['wp_id'], 'sttv_user_data', true );

    update_user_meta($meta['wp_id'],'subscription_id',$obj['id']);

    if (!$user) return $user;

    foreach ($prev as $attr => $val) {
        switch($attr) {
            case 'status':
                if ($val === 'trialing' && $obj['status'] === 'active') {
                    $user->remove_cap('course_trialing');
                    $umeta['user']['trialing'] = false;
                    update_user_meta( $meta['wp_id'], 'sttv_user_data', $umeta );

                    $priship = null;
                    if ( $meta['priship'] == 'true' ) {
                        $priship = \Stripe\Charge::create([
                            "amount" => $obj['plan']['metadata']['priship'] ?? 795,
                            "currency" => "usd",
                            "customer" => $obj['customer'],
                            "description" => "Priority shipping for ".$user->display_name,
                            "shipping" => $cus->shipping
                        ]);
                    }
                }
            break;
        }
    }
    return $prev;
}

// customer.subscription.trial_will_end
function customer_subscription_trial_will_end( $data ) {
    $obj = $data['data']['object'];
    $meta = $obj['metadata'];
    $user = get_userdata( $meta['wp_id'] );
    $amt = number_format((float)$obj['plan']['amount']/100, 2);

    $thedate = date('F d Y \at h:i:s a', $obj['trial_end']);

    if ( time()+(5*MINUTE_IN_SECONDS) > $obj['trial_end'] )
        return false;
    else
        $email = new \STTV\Email\Standard([
            'to' => $user->user_email,
            'subject' => 'Your free trial is expiring soon!',
            'message' => "<pre>On {$thedate}, your free trial will expire. This is just a reminder that the card on file with your account will be charged ${$amt}. If you elected to get priority shipping for your books, that will show up as a separate charge. Thank you for being a SupertutorTV customer and student!</pre>"
        ]);
        return $email->send();

}

// customer.subscription.deleted
function customer_subscription_deleted( $data ) {
    $obj = $data['data']['object'];
    $meta = $obj['metadata'];

    if ( !isset( $meta['wp_id'] ) ) return false;
    
    $user = get_userdata( $meta['wp_id'] );
    if ( is_wp_error($user) ) return $user;

    $roles = explode('|',$obj['plan']['metadata']['roles'] ?? $obj['plan']['metadata']['role']);

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
function invoice_payment_succeeded( $data ) {}

// invoice.payment_failed
function invoice_payment_failed( $data ) {}
