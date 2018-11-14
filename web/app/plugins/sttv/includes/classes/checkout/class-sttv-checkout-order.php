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
        return \Stripe\Subscription::create([
            'customer' => $obj['customer'],
            'cancel_at_period_end' => true,
            'metadata' => $obj['metadata'],
            'trial_period_days' => $obj['trial'],
            'items' => $obj['items']
        ]);
    }

    protected function update( $obj ) {
        
    }

    protected function retrieve( $id ) {
        return \Stripe\Subscription::retrieve( $id );
    }

    public function save() {

    }

}