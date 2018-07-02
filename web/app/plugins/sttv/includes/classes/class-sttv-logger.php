<?php

namespace STTV;

if ( ! defined( 'ABSPATH' ) ) {exit;}

class Log {

    private static function garbage_collector( $dir = '' ) {
        if ( $dir === '' ) {
            return;
        }

        $files = scandir( $dir );
        $allow = time() - (DAY_IN_SECONDS * 7);
        $f = [];
        foreach ( $files as $file ) {
            if ( !is_file( $dir . '/' . $file ) ) {
                continue;
            }
            
            $name = substr( $file, 0, -4 );
            if ( $allow > strtotime( $name ) ) {
                $f[] = $name;
                unlink( $dir . '/' . $file );
            }
        }
        return $f;
    }

    public static function webhook( $vars ) {

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

        return self::put( $dir . '/' . date('Y-m-d') . $ext, implode( ' | ', $input ) );
    }
    
    public static function access( $vars ) {
        $data = date( 'c' ).' | '.$_SERVER['REMOTE_ADDR'].' | '.$vars['email'].' | '.$_SERVER['HTTP_USER_AGENT'].' | '.$_SERVER['HTTP_REFERER'];
        $path = STTV_LOGS_DIR . 'courses/';
        if ( !is_dir($path) ) mkdir( $path, 0777, true );
		return self::put( $path . $vars['id'] . '.log', $data );
    }

    private static function put( $path, $data ) {
        return file_put_contents( $path, $data . PHP_EOL, FILE_APPEND | LOCK_EX );
    }
}