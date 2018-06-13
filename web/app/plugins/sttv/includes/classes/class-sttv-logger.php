<?php

namespace STTV;

if ( ! defined( 'ABSPATH' ) ) {exit;}

class Log {

    private static function garbage_collector( $dir = '' ) {
        if ( $dir === '' ) {
            return;
        }

        $files = scandir( $dir );
        $f = [];
        foreach ( $files as $file ) {
            if ( !is_file( $dir . '/' . $file ) ) {
                continue;
            }
            
            $name = substr( $file, 0, -4 );
            $f[] = $name;
            if ( strtotime( $name ) < strtotime( date('m-d-Y') ) - (DAY_IN_SECONDS * 7) ) {
                unlink( $dir . '/' . $file );
            }
        }
        return $f;
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
            'gc' => json_encode( $gc ),
            'data' => json_encode( $vars['data'] )
        ];

        return file_put_contents( $dir . '/' . date('m-d-Y') . $ext,
			implode( ' | ', $input ) . "\r\n",
			FILE_APPEND | LOCK_EX
		);
    }
    
}