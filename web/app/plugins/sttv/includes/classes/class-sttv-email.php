<?php

namespace STTV;

defined( 'ABSPATH' ) || exit;

class Email {

    private static $error = false;

    private static $content_type = 'text/html';

    private static $from = 'info@supertutortv.com';

    private static $from_name = 'SupertutorTV';

    private $to = null;

    private $subject = null;

    private $message = null;

    private $headers = [];

    private $attachments = [];

    public function __construct( $args=[], $template = '' ) {
        $this->to = $args['to'] ?? get_option('admin_email');

        foreach( $args as $k => $v ) {
            if ( $k == 'to' ) continue;
            $this->$k = $v; 
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

    public function from_email() {
        return self::$from;
    }

    public function from_email_name() {
        return self::$from_name;
    }

    public function content_type() {
        return self::$content_type;
    }

    private function _send() {
        add_filter( 'wp_mail_from', [ $this, 'from_email' ] );
        add_filter( 'wp_mail_from_name', [ $this, 'from_email_name' ] );
        add_filter( 'wp_mail_content_type', [ $this, 'content_type' ] );

        $mailed = wp_mail(
            $this->to,
            $this->subject,
            $this->message,
            $this->headers,
            $this->attachments
        );

        remove_filter( 'wp_mail_from', [ $this, 'from_email' ] );
        remove_filter( 'wp_mail_from_name', [ $this, 'from_email_name' ] );
        remove_filter( 'wp_mail_content_type', [ $this, 'content_type' ] );
    }
}