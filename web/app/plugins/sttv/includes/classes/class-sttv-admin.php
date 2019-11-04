<?php

namespace STTV;

defined( 'ABSPATH' ) || exit;

class Admin {

    private static $tests = [
        'ACT',
        'SAT'
    ];

    public static function init() {
        add_action( 'admin_menu', [ __CLASS__, 'vimeo_cache_page']);
    }

    public static function vimeo_cache_page() {
        add_menu_page(
            'Vimeo Cache Refresh',
            'Vimeo Cache',
            'manage_options',
            'vimeo-cache-refresh',
            [ __CLASS__, 'vimeo_cache_refresh'],
            'dashicons-redo',
            70
        );
    }

    public static function vimeo_cache_refresh() {
        echo '<div>Updating cache</div>';
        /* $argv = [
            __FILE__,
            'vcache'
        ];
        echo '<div>';
        require_once STTV_CRON_DIR.'sttv-cron.php';
        echo '</div>'; */

        try {
            
            $vimeo = new \Vimeo\Vimeo( VIMEO_CLIENT, VIMEO_SECRET, VIMEO_TOKEN );
            $alb_data = $vimeo->request( "/me/albums?fields=uri,name&per_page=75" );
            $alb_data_2 = $albs = [];

            if ( intval( $alb_data['body']['total'] ) > 75 ) {
                $alb_data_2 = $vimeo->request( $alb_data['body']['paging']['next'].'&fields=uri,name&per_page=75' );
                $albs = array_merge( $alb_data['body']['data'], $alb_data_2['body']['data'] );
            } else {
                $albs = (array) $alb_data['body']['data'];
            }
            
            foreach ($albs as $alb) { // MAIN CACHE LOOP (LOOP THROUGH ALBUMS)
                $pieces = preg_split('/:|~/',$alb['name']);
                if (!in_array($pieces[0], $this->tests)) continue;

                $test_abbrev = strtolower( $pieces[0] );
                $path = dirname( __DIR__ ) . '/cache/' . $test_abbrev . '/';
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
                        'index' => $i
                    ];
                    if ($i == 0) $embcolor = $vid['embed']['color'];
                    
                    $i++;
                    
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
}