<?php
namespace STTV\Checkout;

defined( 'ABSPATH' ) || exit;

abstract class Stripe {

    protected $type = '\\Stripe\\';

    public $response = null;

    abstract public function save();

    abstract protected function sanitize( $obj );

    public function __call( $name, $arguments ) {
        return [
            'error' => true,
            'code' => 'invalid_class_method',
            'errMsg' => "The method {$name} does not exist."
        ];
    }

    protected function init( $obj = null, $action = 'retrieve', $type = 'Customer' ) {
        if ( is_null( $obj ) || empty( $obj ) ) {
            return [
                'error' => true,
                'code' => 'null_body',
                'errMsg' => 'The request body cannot be null or empty'
            ];
        }
        $this->type .= $type;
        $this->response = $this->type::$action( $obj );
        return $this;
    }

    public function response() {
        return $this->response;
    }
}