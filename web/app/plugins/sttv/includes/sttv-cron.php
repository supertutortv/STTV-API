<?php

require_once dirname( __DIR__ ) . '/vimeo/autoload.php';
use Vimeo\Vimeo;

class Cron {

    private $tests = [
        'ACT'
    ];

    private $vtok = '57e52c4bb16997b539ebed506a099c36';

    private $vsec = '8ZwrHod1K6aec7ZAPETVPgcshokYimu9Jt9Ms+zOfvFetzogUlNHAOvPWm1Emn5iw2BrVuEl/UyCnt/gny6W1iJKadmqDXGTR/PgFj25p/2t65uZdVkJEBsxxs/ZkUf0';

    private $vclient = '5fc7293a02bf8a93179503d7bf72fb190cf8e9af';

    public function __construct( $method ) {
        $this->$method();
    }

    public function __call( $method, $args ) {
        echo "Method '$method' does not exist.";
    }

    public function vcache() {
        $objcache = [];

        try {
            
            $vimeo = new Vimeo( $this->vclient, $this->vsec, $this->vtok );
            $path = dirname( __DIR__ ) . '/cache/';

            foreach ( $this->tests as $test ) {
                $alb_data = $vimeo->request( "/me/albums?query=$test&fields=uri,name&per_page=100" );
                $albs = (array) $alb_data['body']['data'];
                print_r( $albs );
            }

        } catch ( Exception $e ) {

            print_r( $e );

        }
    }

}

$cron = new Cron( $argv[1] );