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
            if ( !is_file( $file ) ) {
                continue;
            }
            
            if ( strtotime( substr( $file, 0, -4 ) ) + (DAY_IN_SECONDS * 7) < strtotime( date('m-d-Y') ) ) {
                unlink( $dir . '/' . $file );
            }
        }

    }

    public static function webhook( $vars = [] ) {
        if ( empty( $vars ) ) {
            return null;
        }

        $dir = STTV_LOGS_DIR . 'webhooks/' . $vars['direction'];

        if ( !is_dir( $dir ) ) {
            mkdir( $dir, 0777, true );
        }

        $ext = ( $vars['error'] ) ? '.err' : '.log';

        $gc = self::garbage_collector( $dir );

        $input = [
            'time' => date('G:i:s', time()),
            'event' => $vars['event'] ?? 'error',
            'forwarded_IP' => getenv('HTTP_X_FORWARDED_FOR') ?: '0.0.0.0',
            'IP' => getenv('REMOTE_ADDR'),
            'UA' => getenv('HTTP_USER_AGENT'),
            'gc' => $gc,
            'data' => json_encode( $vars['data'] )
        ];

        return file_put_contents( $dir . '/' . date('m-d-Y') . $ext,
			implode( ' | ', $input ) . "\r\n",
			FILE_APPEND | LOCK_EX
		);
    }
    
}