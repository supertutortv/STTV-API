<?php

require_once dirname( __DIR__ ) . '/vimeo/autoload.php';
use Vimeo\Vimeo;

class Cron {

    private $tests = [
        'ACT',
        'SAT'
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
        $data = json_encode(
            [
                'type' => 'trial.expiration.checker',
                'rand' => uniqid( 'st_' )
            ]
        );
        $opts = [ 
            'http' => [
                'method'  => 'POST',
                'ignore_errors' => '1',
                'header'  =>
                    "User-Agent: STTVCron (BUDDHA 2.0.0 / VPS)\r\n".
                    "Content-Type: application/json\r\n".
                    "X-STTV-WHSEC: " . hash_hmac( 'sha256', $data, $this->seckeys['sttvwhsec'] ) . "\r\n",
                'content' => $data
            ]
        ];
        $context  = stream_context_create( $opts );
        echo file_get_contents( 'https://api.supertutortv.com/?sttvwebhook', false, $context );
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
            
            $vimeo = new Vimeo( $this->seckeys['vclient'], $this->seckeys['vsec'], $this->seckeys['vtok'] );
            $alb_data = $vimeo->request( "/me/albums?fields=uri,name&per_page=100" );
            $albs = (array) $alb_data['body']['data'];
            
            foreach ($albs as $alb) { // MAIN CACHE LOOP (LOOP THROUGH ALBUMS)
                $path = dirname( __DIR__ ) . '/cache/';
                $pieces = explode(':',$alb['name']);
                if (!in_array($pieces[0], $this->tests)) continue;

                $test_abbrev = strtolower( $pieces[0] );
                $path .= $test_abbrev . '/';
                $name = implode(' ', $pieces );
                $qstring = 'fields=name,description,duration,link,embed.color,tags.tag,pictures.sizes.link,stats.plays&per_page=75&sort=manual';
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
                    $out = substr( substr( $vid['pictures']['sizes'][2]['link'], 0, strpos( $vid['pictures']['sizes'][2]['link'], '_' ) ), strpos( $vid['pictures']['sizes'][2]['link'], 'eo/' )+3 );
                    $vidobj[$slug] = [
                        'id' => $vidid,
                        'name' => $vidname,
                        'slug' => $slug,
                        'time' => $vid['duration'],
                        'tags' => $tags,
                        'text' => $vid['description'] ?? '',
                        'thumb' => $out,
                        'views' => $vid['stats']['plays'],
                        'index' => $i
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

                    if ( ! is_dir( $path ) ) mkdir( $path, 0755, true );
                    
                    $contents = json_encode( $albobj, JSON_FORCE_OBJECT | JSON_PRETTY_PRINT );
                    
                    $filew = fopen( $path . str_replace( ' ', '-', $alb['name'] ) .'.cache', 'w+' );
                    fwrite( $filew, $contents );
                    fclose( $filew );

                echo "Album ".$alb['name']." has been updated \r\n";
            }
            echo "The Vimeo JSON cache has been updated \r\n";
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