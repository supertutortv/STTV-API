<?php

namespace STTV;

defined( 'ABSPATH' ) || exit;

class Email {

    private static $error = false;

    private $to = null;

    private $subject = null;

    private $message = null;

    private $headers = [];

    private $attachments = [];

    public function __construct( $args=[], $template = '' ) {
        $admin = get_option('admin_email');
        $this->to = $args['to'] ?? $admin;

        foreach( $args as $k => $v ) {
            if ( $k == 'to' ) continue;
            $this->$k = $v; 
        }

        if ( !isset( $args['headers'] ) || array_search( 'From: ', $args['headers'] ) === false ) {
            $this->headers[] = "From: SupertutorTV <{$admin}>";
        }

        return $this;
    }

    public function __toString() {
        return json_encode($this);
    }

    public function send() {
        foreach (get_object_vars($this) as $par => $val) {
            if ( is_null( $this->$par ) ) {
                self::$error = $par.' must be set to send email';
                break;
            }
        }
        return self::$error ?: $this->_send();
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