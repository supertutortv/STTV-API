<?php

namespace STTV\Pricing;

defined( 'ABSPATH' ) || exit;

class Admin {
    public function __construct() {
        try {
            \Stripe\Product::create([
                'id' => 'SAT',
                "name" => 'The Best SAT Prep Course Ever',
                "type" => "service",
                'metadata' => '[the_best_sat_prep_course_ever]'
            ]);
            \Stripe\Product::create([
                'id' => 'ACT',
                "name" => 'The Best ACT Prep Course Ever',
                "type" => "service",
                'metadata' => '[the_best_act_prep_course_ever]'
            ]);
            \Stripe\Product::create([
                'id' => 'COMBO',
                "name" => 'SAT and ACT Combo',
                "type" => "service",
                'metadata' => '[the_best_sat_prep_course_ever,the_best_act_prep_course_ever]'
            ]);
        } catch ( \Exception $e ) {}
    }
}