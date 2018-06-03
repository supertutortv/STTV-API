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
        $ref_array = [
            'eng' => 'English',
            'mth' => 'Math',
            'rea' => 'Reading',
            'sci' => 'Science',
            'ess' => 'Essay',
            'bq' => 'Bonus Questions',
            'ft' => 'Test 0',
            'nb' => 'New Book',
            'rb' => 'Red Book'
        ];
        $objcache = [];

        try {
            
            $vimeo = new Vimeo( $this->vclient, $this->vsec, $this->vtok );
            $path = dirname( __DIR__ ) . '/cache/';

            foreach ( $this->tests as $test ) {
                $objcache[strtolower($test)] = null;
                $alb_data = $vimeo->request( "/me/albums?query=$test&fields=uri,name&per_page=100" );
                $albs = (array) $alb_data['body']['data'];
                
                foreach ($albs as $alb) { // MAIN CACHE LOOP (LOOP THROUGH ALBUMS)
                    $route = explode( '|', $alb['name'] );

                    $objcache[strtolower($test)][$route[0]] = [
                        $route[1] => [
                            $route[2] => [
                                $route[3] => [
                                    $route[4] => [
                                        $route[5] => null
                                    ]
                                ]
                            ]
                        ]
                    ];

                    
                    /* $qstring = 'fields=name,description,duration,link,embed.color,tags.tag,pictures.sizes.link,stats.plays&per_page=75&sort=alphabetical&direction=asc';
                    $albid = str_replace( '/albums/', '', stristr($alb['uri'], '/albums/') );
                    $video_data = $vimeo->request( '/me/albums/'.$albid.'/videos?'.$qstring );

                    $video_data_2 = $vids = [];

                    if ( intval( $video_data['body']['total'] ) > 75 ) {
                        $video_data_2 = $vimeo->request( $video_data['body']['paging']['next'].'&'.$qstring );
                        $vids = array_merge( $video_data['body']['data'], $video_data_2['body']['data'] );
                    } else {
                        $vids = $video_data['body']['data'];
                    } */
                }
            }

            print_r($objcache);
        } catch ( Exception $e ) {

            print_r( $e );

        }
    }

}

$cron = new Cron( $argv[1] );