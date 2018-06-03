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

        try {
            
            $vimeo = new Vimeo( $this->vclient, $this->vsec, $this->vtok );
            $path = dirname( __DIR__ ) . '/cache/';

            foreach ( $this->tests as $test ) {
                $test_abbrev = strtolower( $test );
                $path .= $test_abbrev . '/';
                $alb_data = $vimeo->request( "/me/albums?query=$test&fields=uri,name&per_page=100" );
                $albs = (array) $alb_data['body']['data'];
                
                foreach ($albs as $alb) { // MAIN CACHE LOOP (LOOP THROUGH ALBUMS)
                    $name = str_replace( '|', '', $alb['name'] );
                    
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
                        
                        $vidobj[$slug] = [
                            'ID' => $vidid,
                            'name' => $vidname,
                            'slug' => $slug,
                            'time' => $vid['duration'],
                            'tags' => $tags,
                            'text' => $vid['description'],
                            'thumb' => $vid['pictures']['sizes'][2]['link'],
                            'views' => $vid['stats']['plays']
                        ];
                        if ($i == 0) {$embcolor = $vid['embed']['color'];$i++;}
                        
                    } // END VIDEO LOOP
                    $albobj = [
                        'timestamp' => date("F d, Y H:i:s"),
                        'albumName' => $alb['name'],
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