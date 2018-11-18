<?php

namespace STTV\Pricing;

defined( 'ABSPATH' ) || exit;

class Admin {
    public function __construct() {
        add_action( 'save_post_courses', [ $this, 'makeplans'] );
    }

    public function makeplans() {
        try {
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
        } finally {
            $satplans = \Stripe\Plan::all(['product'=>'SAT']);

            $sat->plans = $satplans->data;
            $sat->price = 0;

            update_option('pricingplan_sat', json_encode($sat) );
        }

        try {
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
        } finally {
            $actplans = \Stripe\Plan::all(['product'=>'ACT']);

            $act->plans = $actplans->data;
            $act->price = 0;

            update_option('pricingplan_act', json_encode($act) );
        }

        try {
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
        } finally {
            $comboplans = \Stripe\Plan::all(['product'=>'COMBO']);

            $combo->plans = $comboplans->data;
            $combo->price = 0;

            update_option('pricingplan_combo', json_encode($combo) );
        }
    }
}