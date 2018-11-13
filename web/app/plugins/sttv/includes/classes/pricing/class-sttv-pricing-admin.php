<?php

namespace STTV\Pricing;

defined( 'ABSPATH' ) || exit;

class Admin {
    public function __construct() {
        add_action( 'save_post_courses', [ $this, 'makeplans'] );
    }

    public function makeplans() {
        try {
            \Stripe\Product::retrieve([
                'id' => 'SAT',
            ]);
        } catch( \Exception $e ) {
            \Stripe\Product::create([
                'id' => 'SAT',
                "name" => 'The Best SAT Prep Course Ever',
                "type" => "service",
                'metadata' => [ 'roles' => '[the_best_sat_prep_course_ever]' ]
            ]);
        }

        try {
            \Stripe\Product::retrieve([
                'id' => 'ACT',
            ]);
        } catch( \Exception $e ) {
            \Stripe\Product::create([
                'id' => 'ACT',
                "name" => 'The Best ACT Prep Course Ever',
                "type" => "service",
                'metadata' => [ 'roles' => '[the_best_act_prep_course_ever]' ]
            ]);
        }

        try {
            \Stripe\Product::retrieve([
                'id' => 'COMBO',
            ]);
        } catch( \Exception $e ) {
            \Stripe\Product::create([
                'id' => 'COMBO',
                "name" => 'SAT and ACT Combo',
                "type" => "service",
                'metadata' => [ 'roles' => '[the_best_sat_prep_course_ever,the_best_act_prep_course_ever]' ]
            ]);
        }
    }
}