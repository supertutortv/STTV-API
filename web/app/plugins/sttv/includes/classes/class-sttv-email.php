<?php

namespace STTV;

defined( 'ABSPATH' ) || exit;

class Email {

    private static $email_sent = false;

    private static $error = '';

    private $to = null;

    private $subject = null;

    private $message = null;

    private $headers = [];

    private $attachments = [];

    public function __construct( $args=[] ) {
        $this->to = $args['to'] ?? get_option('admin_email');

        foreach( $args as $k => $v ) {
            if ( $k == 'to' ) continue;
            $this->$k = $v; 
        }
        return $this;
    }

    public function send() {
        $params = get_object_vars($this);
        foreach ($params as $par => $val) {
            if ( is_null( $this->$par ) ) {
                self::$error = $par.' must be set to send email';
            }
        }

        //self::$email_sent = $this->_send();
        return $this;
    }

    public function email_sent() {
        return self::$email_sent;
    }

    public function get_last_error() {
        return self::$error;
    }

    private function _send() {
        return wp_mail(
            $this->to,
            $this->subject,
            $this->message,
            $this->headers,
            $this->attachments
        );
    }
}