<?php

namespace STTV\Pricing;

defined( 'ABSPATH' ) || exit;

class Admin {
    public function __construct() {
        add_action( 'save_post_courses', [ $this, 'makeplans'] );
    }

    public function makeplans() {
        try {
            if ( !get_option('pricingplan_sat') )
                $sat = \Stripe\Product::retrieve([
                    'id' => 'SAT',
                ]);
        } catch( \Exception $e ) {
            $sat = \Stripe\Product::create([
                'id' => 'SAT',
                "name" => 'The Best SAT Prep Course Ever',
                "type" => "service",
                'metadata' => [ 'roles' => '[the_best_sat_prep_course_ever]' ]
            ]);
        }
        $satplans = \Stripe\Plan::all(['product'=>'SAT']);

        $sat->plans = $satplans->data;

        update_option('pricingplan_sat', json_encode($sat) );

        try {
            if ( !get_option('pricingplan_act') )
            $act = \Stripe\Product::retrieve([
                'id' => 'ACT',
            ]);
        } catch( \Exception $e ) {
            $act = \Stripe\Product::create([
                'id' => 'ACT',
                "name" => 'The Best ACT Prep Course Ever',
                "type" => "service",
                'metadata' => [ 'roles' => '[the_best_act_prep_course_ever]' ]
            ]);
        }
        $actplans = \Stripe\Plan::all(['product'=>'ACT']);

        $act->plans = $actplans->data;

        update_option('pricingplan_act', json_encode($act) );

        try {
            if ( !get_option('pricingplan_combo') )
            $combo = \Stripe\Product::retrieve([
                'id' => 'COMBO',
            ]);
        } catch( \Exception $e ) {
            $combo = \Stripe\Product::create([
                'id' => 'COMBO',
                "name" => 'SAT and ACT Combo',
                "type" => "service",
                'metadata' => [ 'roles' => '[the_best_sat_prep_course_ever,the_best_act_prep_course_ever]' ]
            ]);
        }
        $comboplans = \Stripe\Plan::all(['product'=>'COMBO']);

        $combo->plans = $comboplans->data;

        update_option('pricingplan_combo', json_encode($combo) );
    }
}