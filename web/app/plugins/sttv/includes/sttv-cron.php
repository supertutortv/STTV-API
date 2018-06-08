<?php

require_once dirname( __DIR__ ) . '/vimeo/autoload.php';
use Vimeo\Vimeo;

class Cron {

    private $tests = [
        'ACT'
    ];

    private $seckeys = [];

    public function __construct( $method ) {
        $vars = file( dirname( __DIR__ ) . '/vimeo/.seckeys', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
        foreach ( $vars as $v ) {
            $line = explode( '=', $v );
            $this->seckeys[$line[0]] = $line[1];
        }
        $this->$method();
    }

    public function __call( $method, $args ) {
        echo "Method '$method' does not exist.";
    }

    private function subchaser() {
        $data = http_build_query(
            [
                'from' => 'sttvcron'
            ]
        );
        $opts = [ 
            'http' => [
                'method'  => 'GET',
                'header'  => [
                    'User-Agent' => 'STTVCron (BUDDHA 2.0.0 / VPS)',
                    'Content-Type' => '',
                    'X-STTV-WHSEC' => hash_hmac( 'sha256', json_encode( $data ), $this->seckeys['sttvwhsec'] )
                ],
                'content' => $data
            ]
        ];
        $context  = stream_context_create( $opts );
        $result = file_get_contents( 'https://app.supertutortv.com/?sttvwebhook', false, $context );
        print_r( $result );
        //echo "All your sub belong to us!";
    }

    private function rename_albums() {
        try {
            $vimeo = new Vimeo( $this->seckeys['vclient'], $this->seckeys['vsec'], $this->seckeys['vtok'] );
            $alb_data = $vimeo->request( "/me/albums?fields=uri,name&per_page=100" );
            $albs = (array) $alb_data['body']['data'];

            foreach ( $albs as $alb ) {
                $vimeo->request( '/me/albums/' . str_replace( '/albums/', '', stristr( $alb['uri'], '/albums/' ) ), [ 'name' => str_replace( '|', ':', $alb['name'] ) ], 'PATCH' );
            }
        } catch ( Exception $e ) {

            print_r( $e );

        }
    }

    private function vcache() {

        try {
            
            $vimeo = new Vimeo( $this->vclient, $this->vsec, $this->vtok );
            $path = dirname( __DIR__ ) . '/cache/';

            foreach ( $this->tests as $test ) {
                $test_abbrev = strtolower( $test );
                $path .= $test_abbrev . '/';
                $alb_data = $vimeo->request( "/me/albums?query=$test&fields=uri,name&per_page=100" );
                $albs = (array) $alb_data['body']['data'];
                
                foreach ($albs as $alb) { // MAIN CACHE LOOP (LOOP THROUGH ALBUMS)
                    $name = str_replace( ':', ' ', $alb['name'] );
                    
                    $qstring = 'fields=name,description,duration,link,embed.color,tags.tag,pictures.sizes.link,stats.plays&per_page=75&sort=alphabetical&direction=asc';
                    $albid = str_replace( '/albums/', '', stristr($alb['uri'], '/albums/') );
                    $video_data = $vimeo->request( '/me/albums/'.$albid.'/videos?'.$qstring );

                    $video_data_2 = $vids = [];

                    if ( intval( $video_data['body']['total'] ) > 75 ) {
                        $video_data_2 = $vimeo->request( $video_data['body']['paging']['next'].'&'.$qstring );
                        $vids = array_merge( $video_data['body']['data'], $video_data_2['body']['data'] );
                    } else {
                        $vids = $video_data['body']['data'];
                    }

                    $vidobj = $albobj = [];
                    $embcolor = '';
                    $i = 0;
                    
                    foreach ($vids as $vid) { // LOOP THROUGH VIDEOS PER ALBUM
                        $vidid = str_replace('https://vimeo.com/','',$vid['link']);
                        $vidname = $vid['name'];
                        $slug = $this->sanitize_this_title($vidname);
                        $tags = [];
                        $stags = $vid['tags'];
                        foreach ($stags as $tag) {
                            $tags[] = $tag['tag'];
                        }
                        preg_match('/video\/\s*([^\_]+)/', $vid['pictures']['sizes'][2]['link'], $out);
                        $vidobj[$slug] = [
                            'ID' => $vidid,
                            'name' => $vidname,
                            'type' => 'video',
                            'slug' => $slug,
                            'time' => $vid['duration'],
                            'tags' => $tags,
                            'text' => $vid['description'],
                            'thumb' => $out[1],
                            'views' => $vid['stats']['plays']
                        ];
                        if ($i == 0) {$embcolor = $vid['embed']['color'];$i++;}
                        
                    } // END VIDEO LOOP
                    $albobj = [
                        'timestamp' => date("F d, Y H:i:s"),
                        'albumName' => $name,
                        'albumID' => $albid,
                        'embedColor' => $embcolor,
                        'videos' => $vidobj,
                    ];
                    
                    ##################################
                    ##### WRITE RESULTS TO FILES #####
                    ##################################

                        if ( ! is_dir( $path ) ) {
                            mkdir( $path, 0777, true );
                        }
                        
                        $contents = json_encode( $albobj, JSON_FORCE_OBJECT | JSON_PRETTY_PRINT );
                        
                        $filew = fopen( $path . $alb['name'] .'.cache', w );
                        fwrite( $filew, $contents );
                        fclose( $filew );

                    print_r( "Album ".$alb['name']." updated \r\n");
                }
            }

            print_r( 'The video data cache has been updated' );
        } catch ( Exception $e ) {

            print_r( $e );

        }
    }

    private function sanitize_this_title( $title='' ) {
        $title = strip_tags( $title );
        $title = preg_replace('|%([a-fA-F0-9][a-fA-F0-9])|', '---$1---', $title);
        $title = str_replace('%', '', $title);
        $title = preg_replace('|---([a-fA-F0-9][a-fA-F0-9])---|', '%$1', $title);
        $title = strtolower($title);
        $title = preg_replace('/&.+?;/', '', $title);
        $title = str_replace('.', '-', $title);
        $title = preg_replace('/[^%a-z0-9 _-]/', '', $title);
        $title = preg_replace('/\s+/', '-', $title);
        $title = preg_replace('|-+|', '-', $title);
        $title = trim($title, '-');
     
        return $title;
    }

}

$cron = new Cron( $argv[1] );