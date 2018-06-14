<?php
namespace STTV\Checkout;

defined( 'ABSPATH' ) || exit;

class Customer extends Stripe {

    protected function create( $obj ) {
        return \Stripe\Customer::create( $obj );
    }

    protected function update( $obj ) {
        
    }
    
    protected function retrieve( $id ) {

    }

}