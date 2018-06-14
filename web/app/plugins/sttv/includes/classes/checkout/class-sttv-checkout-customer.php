<?php
namespace STTV\Checkout;

defined( 'ABSPATH' ) || exit;

class Customer extends Stripe {

    public function __construct( $action = 'create', $obj = null ) {
        return parent::__construct( $action, $obj );
    }

    protected function create( $obj ) {
        return \Stripe\Customer::create( $obj );
    }

    protected function update( $obj ) {
        
    }
    
    protected function retrieve( $id ) {

    }

}