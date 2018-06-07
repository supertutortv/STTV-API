<?php
namespace STTV\Checkout;

defined( 'ABSPATH' ) || exit;

class Order extends Stripe {

    public function __construct( $obj = null, $action = 'create' ) {
        $obj = $this->sanitize( $obj );
        return $this->init( $obj, $action, $type = 'Order' );
    }

    public function save() {
        global $wpdb;
        $res = $this->response;
    }

    protected function sanitize( $obj ) {
        $sanitized = $obj;
        return $sanitized;
    }

}