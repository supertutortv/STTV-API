<?php

namespace STTV\Pricing;

defined( 'ABSPATH' ) || exit;

class Admin {
    public function __construct() {
        add_action( 'admin_init', [ $this, 'makeplans'] );
    }

    public function makeplans() {
        try {
            \Stripe\Product::retreive([
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
            \Stripe\Product::retreive([
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
            \Stripe\Product::retreive([
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