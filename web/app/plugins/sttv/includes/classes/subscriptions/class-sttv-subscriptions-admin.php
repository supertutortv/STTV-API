<?php

namespace STTV\Subscriptions;

defined( 'ABSPATH' ) || exit;

class Admin {
    public function __construct() {
        add_action('admin_init',function(){
            add_action( 'save_post_pricing', [ $this, 'sttv_sub_plan' ], 10, 2 );
        });
    }

    public function sttv_sub_plan( $post_id, $post ) {

		// Stop WP from clearing custom fields on autosave
		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;

		// Prevent quick edit from clearing custom fields
        if (defined('DOING_AJAX') && DOING_AJAX) return;

        $pricing = get_fields( $post_id );

        if (!$pricing['sub_pricing']) return false;

        $data[sttv_id_encode($post_id)] = array_merge( $pricing['sub_pricing'], [
            'courses' => $pricing['courses'],
            'name' => $post->post_title
        ]);
        
        update_post_meta( $post_id, 'pricing_data', $data );

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
    }
}