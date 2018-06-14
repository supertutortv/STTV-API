<?php
namespace STTV\Checkout;

defined( 'ABSPATH' ) || exit;

abstract class Stripe {

    protected $namespace = '\\Stripe\\';

    public $response = null;

    abstract protected function create( $obj );

    abstract protected function update( $obj );

    abstract protected function retrieve( $id );

    public function __call( $name, $arguments ) {
        return [
            'error' => true,
            'code' => 'invalid_class_method',
            'errMsg' => "The method {$name} does not exist."
        ];
    }

    public function __construct( $action = 'create', $obj = null ) {
        $obj = $this->init( $obj );
        $this->response = $this->$action( $obj );
        return $this;
    }

    protected function init( $obj = null ) {
        if ( is_null( $obj ) || empty( $obj ) ) {
            return [
                'error' => true,
                'code' => 'null_body',
                'errMsg' => 'The request body cannot be null or empty'
            ];
        }
        return $obj;
    }

    public function response() {
        return $this->response;
    }
}