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
        foreach( $obj['items'] as $item ) {
            \Stripe\InvoiceItem::create( $item );
        }
        return \Stripe\Invoice::create([
            'customer' => $obj['customer'],
            'billing' => 'send_invoice',
            'due_date' => time() + (DAY_IN_SECONDS * 5)
        ]);
    }

    protected function update( $obj ) {
        
    }

    protected function retrieve( $id ) {

    }

    public function save() {

    }

}