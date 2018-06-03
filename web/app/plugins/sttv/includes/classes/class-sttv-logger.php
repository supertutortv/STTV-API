<?php

namespace STTV;

if ( ! defined( 'ABSPATH' ) ) {exit;}

class Log {

    public static function webhook( $vars = [] ) {
        if ( empty( $vars ) ) {
            throw new InvalidArgumentException( 'Log variables cannot be empty.' );
        }

        $ext = ( $vars['error'] ) ? '.err' : '.log';

        $input = [
            'time' => date('G:i:s', time()),
            'event' => $vars['event'] ?: 'error',
            'forwarded_IP' => getenv('HTTP_X_FORWARDED_FOR') ?: '0.0.0.0',
            'IP' => getenv('REMOTE_ADDR'),
            'UA' => getenv('HTTP_USER_AGENT'),
        ];

        if ( isset( $vars['err_obj'] ) ) {
            $input['data'] = $vars['err_obj'];
        }

        if ( !is_dir( STTV_LOGS_DIR . 'webhooks/' . $vars['direction'] ) ) {
            mkdir( STTV_LOGS_DIR . 'webhooks/' . $vars['direction'], 0777, true );
        }

        return file_put_contents( STTV_LOGS_DIR . 'webhooks/' . $vars['direction'] . '/' . date('m-d-Y') . $ext,
			json_encode( $input ) . "\n",
			FILE_APPEND | LOCK_EX
		);
    }
    
}