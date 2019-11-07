<?php

defined( 'ABSPATH' ) || exit;

###################
##### HELPERS #####
###################

function __stJ2A($object) {
    return json_decode(json_encode($object),true);
}

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
    update_user_meta( $user_id, 'sttv_user_data', [
        'user' => [
            'subscription' => '',
            'history' => [],
            'downloads' => [],
            'type' => 'standard',
            'trialing' => null,
            'settings' => [
                'autoplay' => [
                    'msl' => false,
                    'playlist' => false
                ],
                'dark_mode' => false
            ],
            'userdata' => [
                'login_timestamps' => []
            ]
        ],
        'courses' => json_decode('{}',true)
    ]);

    return $wpdb->update($wpdb->users, [
        'user_login' => str_replace('cus_','',$customer['id']),
        'user_nicename' => str_replace(' ','-',strtolower($customer['description'])).'-'.str_replace('cus_','',$customer['id'])
    ],
    [
        'ID' => $customer['metadata']['wp_id']
    ]);
}

// customer.updated
function customer_updated( $data ) {
    global $wpdb;
    $customer = $data['data']['object'];
    $user_id = $customer['metadata']['wp_id'];
    return $wpdb->update($wpdb->users, [
        'user_login' => str_replace('cus_','',$customer['id']),
        'user_nicename' => str_replace(' ','-',strtolower($customer['description'])).'-'.str_replace('cus_','',$customer['id'])
    ],
    [
        'ID' => $customer['metadata']['wp_id']
    ]);
}

// customer.deleted
function customer_deleted( $data ) {
    require_once( ABSPATH.'wp-admin/includes/user.php' );
    
    $obj = $data['data']['object'];
    $user = get_user_by('login', str_replace('cus_','',$obj['id']));

    return wp_delete_user( $user->ID == 1 ? 0 : $user->ID );
}

//customer.subscription.created
function customer_subscription_created( $data ) {
    $obj = $data['data']['object'];
    $meta = $obj['metadata'];
    $cus = \Stripe\Customer::retrieve($obj['customer']);
    $sub = \Stripe\Subscription::retrieve($obj['id']);

    $umeta = get_user_meta( $meta['wp_id'], 'sttv_user_data', true );

    $umeta['user']['subscription'] = $obj['id'];

    if ( isset( $meta['wp_id'] ) ) {
        $uid = $meta['wp_id'];
    } else {
        $uid = $cus->metadata->wp_id;
        $sub->metadata = [
            'priship' => "false",
            'wp_id' => $uid
        ];
        $sub->save();
    }

    $user = get_userdata( $uid );
    $courses = json_decode($obj['plan']['metadata']['courses'],true);
    $plan = $obj['plan'];

    delete_user_meta($user->ID, "invoiceFailFlag-all");

    if ($plan['product'] === 'COMBO') {
        update_user_meta( $uid, 'sub_id-sat', $obj['id']);
        update_user_meta( $uid, 'sub_id-act', $obj['id']);
    } else {
        $crslc = strtolower($plan['product']);
        update_user_meta( $uid, "sub_id-$crslc", $obj['id']);
    }

    $prod = \Stripe\Product::retrieve($plan['product']);
    $fullname = $user->first_name.' '.$user->last_name;
    $shipping = __stJ2A($cus->shipping);
    $umeta = get_user_meta( $meta['wp_id'], 'sttv_user_data', true );

    $umeta['user']['subscription'] = $obj['id'];

    $roles = explode('|',$obj['plan']['metadata']['roles']);

    if ( $obj['status'] === 'trialing' )
        foreach ( $roles as $role ) $user->add_role($role.'_trial');
    else
        foreach ( $roles as $role ) $user->add_role($role);

    preg_match('/The Best (\w+) Prep Course Ever/m',$prod->name,$matches);

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

    return [
        'email' => new \STTV\Email\Template([
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
        ]),
        'meta' => get_user_meta( $meta['wp_id'], 'sttv_user_data', true )
    ];
}

// customer.subscription.updated
function customer_subscription_updated( $data ) {
    $obj = $data['data']['object'];
    $meta = $obj['metadata'];
    $plan = $obj['plan'];
    $prev = $data['data']['previous_attributes'];
    $sub = \Stripe\Subscription::retrieve($obj['id']);
    $cus = \Stripe\Customer::retrieve($obj['customer']);
    $prod = \Stripe\Product::retrieve($plan['product']);
    $user = get_userdata( $meta['wp_id'] );
    $umeta = get_user_meta( $meta['wp_id'], 'sttv_user_data', true );

    if (!$user) return $user;

    $fullname = $user->first_name.' '.$user->last_name;

    // take action based on any previous attributes
    foreach ($prev as $attr => $val) {
        switch($attr) {
            case 'status':
                if ($val === 'trialing' && $obj['status'] === 'active') {
                    new \STTV\Email\Template([
                        'template' => 'trial-ended',
                        'email' => $user->user_email,
                        'name' => $fullname,
                        'subject' => 'Your SupertutorTV Course is now UNLOCKED! ',
                        'content' => [
                            [
                                'name' => 'fname',
                                'content' => $user->first_name
                            ],
                            [
                                'name' => 'coursename',
                                'content' => $prod->name
                            ]
                        ]
                    ]);
                }
            break;
        }
    }

    $umeta['user']['trialing'] = ($obj['status'] === 'trialing');
    $umeta['user']['subscription'] = $obj['id'];
    update_user_meta($meta['wp_id'],'sttv_user_data',$umeta);
    update_user_meta($meta['wp_id'],'subscription_id',$obj['id']);
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

    $fullname = ''.$user->first_name.' '.$user->last_name;
    $manual_cancelled = $meta['cancelled'] ?? false;
    if ($manual_cancelled) return new \STTV\Email\Template([
        'template' => 'course-cancelled',
        'email' => $user->user_email,
        'name' => $fullname,
        'subject' => 'Cancellation Confirmation',
        'content' => [
            [
                'name' => 'fname',
                'content' => $user->first_name
            ]
        ]
    ]);

    return update_user_meta($user->ID,"invoiceFailFlag-all",true);
}

// invoice.payment_succeeded
function invoice_payment_succeeded( $data ) {
    $obj = $data['data']['object'];
    $priship = null;
    $ret = [];

    if ($obj['paid'] == true && $obj['amount_paid'] > 0) {
        $cus = \Stripe\Customer::retrieve($obj['customer']);
        $sub = \Stripe\Subscription::retrieve($obj['subscription']);
        $meta = __stJ2A($cus->metadata);
        $submeta = __stJ2A($sub->metadata);
        $planmeta = __stJ2A($sub->plan->metadata);
        $user = get_userdata( $meta['wp_id'] );
        $umeta = get_user_meta( $meta['wp_id'], 'sttv_user_data', true );
        $fullname = $user->first_name.' '.$user->last_name;

        $roles = explode('|',$planmeta['roles']);

        foreach ( $roles as $role ) {
            $user->remove_role($role.'_trial');
            $user->add_role($role);
        }

        $sub->cancel_at_period_end = true;
        $sub->save();

        foreach ($obj['lines']['data'] as $line) {
            $course = strtolower($line['plan']['product']);
            $ret[$course] = delete_user_meta($user->ID,"invoiceFailFlag-$course");
        }
        delete_user_meta($user->ID,"invoiceFailFlag-all");

        $email = new \STTV\Email\Standard([
            'to' => 'info@supertutortv.com',
            'subject' => $fullname.'\'s trial has ended - PAID',
            'message' => 'Please wait 24 hrs before shipping their book(s).'
        ]);
        $email->send();

        if ( $submeta['priship'] == 'true' ) {
            $priship = \Stripe\Charge::create([
                "amount" => $planmeta['priship'] ?? 795,
                "currency" => "usd",
                "customer" => $obj['customer'],
                "description" => "Priority shipping for ".$fullname,
                "metadata" => [
                    "webhook" => "invoice.payment_succeeded"
                ],
                "shipping" => $obj['customer_shipping']
            ]);
        }
    }

    return $ret;
}

// invoice.created
function invoice_created( $data ) {}

// invoice.sent
function invoice_sent( $data ) {}

// invoice.updated
function invoice_updated( $data ) {}

// invoice.finalized
function invoice_finalized( $data ) {}

// invoice.payment_failed
function invoice_payment_failed( $data ) {
    $obj = $data['data']['object'];
    $user = get_user_by('login', str_replace('cus_','',$obj['customer']));
    $ret = [];

    foreach ($obj['lines']['data'] as $line) {
        $course = strtolower($line['plan']['product']);
        $ret[$course] = update_user_meta($user->ID,"invoiceFailFlag-$course",true);
    }

    return $ret;
}

// charge.succeeded
function charge_succeeded( $data ) {
    $obj = $data['data']['object'];
    if ($obj['paid'] == true) {
        $cus = \Stripe\Customer::retrieve($obj['customer']);
        $meta = __stJ2A($cus->metadata);
        $user = get_userdata( $meta['wp_id'] );
        $umeta = get_user_meta( $meta['wp_id'], 'sttv_user_data', true );
        $fullname = $user->first_name.' '.$user->last_name;

        if (strpos($obj['description'],'Payment for invoice') > -1) {
            $umeta['user']['trialing'] = false;
            update_user_meta( $meta['wp_id'], 'sttv_user_data', $umeta );
        }

        if (strpos($obj['description'],'Priority shipping') > -1) {
            $email = new \STTV\Email\Standard([
                'to' => 'info@supertutortv.com',
                'subject' => $fullname.' paid for priority shipping',
                'message' => '<pre>'.json_encode($cus->shipping,JSON_PRETTY_PRINT).'</pre>'
            ]);
            $email->send();
        }
    }
}

// charge.refunded
function charge_refunded( $data ) {}

// charge.failed
function charge_failed( $data ) {}

// coupon.created
function coupon_created( $data ) {}

// coupon.updated
function coupon_updated( $data ) {}