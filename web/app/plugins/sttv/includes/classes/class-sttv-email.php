<?php

namespace STTV;

defined( 'ABSPATH' ) || exit;

class Email {

    private $to;

    private $subject = 'Default subject';

    private $message = 'Default message';

    public function __construct($args) {
        $this->to = $args['to'] ?? get_option('admin_email');

        foreach( $args as $k => $v ) {
            if ( $k == 'to' ) continue;
            $this->$k = $v; 
        }
        return $this;
    }

    public function get_params() {
        return get_object_vars($this);
    }
}