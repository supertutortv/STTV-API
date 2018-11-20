<?php
namespace STTV\Checkout;

defined( 'ABSPATH' ) || exit;

class Order extends Stripe {

    public function __construct( $action = 'create', $obj = null ) {
        $obj = $this->init( $obj );
        $this->response = $this->$action( $obj );
        return $this;
    }

    protected function create( $obj ) {
    }

    protected function update( $obj ) {
        
    }

    protected function retrieve( $id ) {
        return \Stripe\Subscription::retrieve( $id );
    }

    public function save() {

    }

}