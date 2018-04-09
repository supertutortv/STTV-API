<?php 

namespace STTV;

if ( ! defined( 'ABSPATH' ) ) {exit;}

class Scripts {
    public static function init() {
        wp_dequeue_script('jquery');
        wp_deregister_script('jquery');
        
        //jquery scripts
        wp_enqueue_script('jquery','https://ajax.googleapis.com/ajax/libs/jquery/3.3.1/jquery.min.js',false,null);
        
        //styles
        wp_enqueue_style('sttv-main', get_stylesheet_directory_uri().'/style.css', false, null);
    }
}