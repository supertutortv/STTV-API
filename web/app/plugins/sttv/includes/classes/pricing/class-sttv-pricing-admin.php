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
            ]);
            \Stripe\Product::create([
                'id' => 'ACT',
                "name" => 'The Best ACT Prep Course Ever',
                "type" => "service",
            ]);
            \Stripe\Product::create([
                'id' => 'COMBO',
                "name" => 'SAT and ACT Combo',
                "type" => "service",
            ]);
        } catch ( \Exception $e ) {}
    }
}