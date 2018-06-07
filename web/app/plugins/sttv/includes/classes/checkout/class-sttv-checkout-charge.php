<?php
namespace STTV\Checkout;

defined( 'ABSPATH' ) || exit;

class Charge extends Stripe {

    public function save() {
        global $wpdb;
        $res = $this->response;
    }

    protected function sanitize( $obj ) {
        $sanitized = $obj;
        return $sanitized;
    }
    
}