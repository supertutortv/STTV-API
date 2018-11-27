<?php

namespace STTV\Email;

defined( 'ABSPATH' ) || exit;

class Standard {

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

class Template {

    private $mandrill;

    public $response = null;

    public $error = null;

    public function __construct($args=[]) {
        if ( empty($args) || !isset($args['email'], $args['name'], $args['subject'], $args['template'], $args['content']) ) {
            $this->error = 'The required parameters were not set.';
            return $this;
        }

        $this->mandrill = new \Mandrill(MANDRILL_API_KEY);

        $from = get_option('admin_email');
        
        $message = [
            'from_email' => $from,
            'from_name' => 'SupertutorTV',
            'subject' => $args['subject'],
            'to'=> [
                [
                    'type' => 'to',
                    'email' => $args['email'],
                    'name' => $args['name']
                ]
            ],
            'headers' => [
                'Reply-To' => $from,
            ],
            'metadata' => [
                'website' => 'https://supertutortv.com'
            ],
            'inline_css' => true,
            'track_opens' => true,
            'track_clicks' => true,
            'bcc_address' => 'dave@supertutortv.com'
        ];
        
        try {
            $this->response = $this->mandrill->messages->sendTemplate(
                $args['template'],
                $args['content'],
                $message
            );
        } catch ( \Mandrill_Error $e ) {
            $this->error = $e;
        }
    }
}