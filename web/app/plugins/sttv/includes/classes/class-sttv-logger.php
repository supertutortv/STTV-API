<?php

namespace STTV;

if ( ! defined( 'ABSPATH' ) ) {exit;}

class Log {

    private static function garbage_collector( $dir = '' ) {
        if ( $dir === '' ) {
            return;
        }

        $files = scandir( $dir );
        foreach ( $files as $file ) {
            //if ()
        }

    }

    public static function webhook( $vars = [] ) {
        if ( empty( $vars ) ) {
            return null;
        }

        $dir = STTV_LOGS_DIR . 'webhooks/' . $vars['direction'];
        $ext = ( $vars['error'] ) ? '.err' : '.log';

        $input = [
            'time' => date('G:i:s', time()),
            'event' => $vars['event'] ?? 'error',
            'forwarded_IP' => getenv('HTTP_X_FORWARDED_FOR') ?: '0.0.0.0',
            'IP' => getenv('REMOTE_ADDR'),
            'UA' => getenv('HTTP_USER_AGENT'),
            'data' => json_encode( $vars['data'] )
        ];

        if ( !is_dir( $dir ) ) {
            mkdir( $dir, 0777, true );
        }

        return file_put_contents( $dir . '/' . date('m-d-Y') . $ext,
			implode( ' | ', $input ) . "\r\n",
			FILE_APPEND | LOCK_EX
		);
    }
    
}