<?php

namespace STTV;

defined( 'ABSPATH' ) || exit;

class Email {

    private static $email_sent = false;

    private $to;

    private $subject;

    private $message;

    private $headers;

    public function __construct( $args=[] ) {
        $this->to = $args['to'] ?? get_option('admin_email');

        foreach( $args as $k => $v ) {
            if ( $k == 'to' ) continue;
            $this->$k = $v; 
        }
        return $this;
    }

    public function send() {
        $params = $this->get_params();

        self::$email_sent = $this->_send();
        return $this;
    }

    public function get_params() {
        return get_object_vars($this);
    }

    public function email_sent() {
        return self::$email_sent;
    }

    private function _send() {
        return wp_mail(
            $this->to
        );
    }
}