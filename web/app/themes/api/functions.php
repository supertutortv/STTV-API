<?php 
add_theme_support( 'custom-header' );
remove_action( 'wp_head', '_admin_bar_bump_cb' );
remove_action( 'wp_head', 'wp_generator' );
add_filter( 'ls_meta_generator', '__return_false' );
add_filter( 'show_admin_bar', '__return_false' );

add_filter( 'lostpassword_url', 'new_lostpw_url', 10, 2 );
function new_lostpw_url( $url, $redir ){
    $url = home_url().'/?lostpw';
    return $url;
}

add_action( 'init', 'redirect_to_frontend', 0 );
function redirect_to_frontend() {
    global $pagenow;
    if ( $pagenow === 'wp-login.php' && !is_user_logged_in() ) {
        wp_redirect(home_url());
        exit;
    }
}

add_action('wp_enqueue_scripts','sttv_enqueue_all');
function sttv_enqueue_all() {
	//dequeue
	wp_dequeue_script('jquery');
	wp_deregister_script('jquery');
	
	//jquery scripts
	wp_enqueue_script('jquery','https://ajax.googleapis.com/ajax/libs/jquery/3.3.1/jquery.min.js',false,null);
	
	//styles
    wp_enqueue_style('sttv-main', get_stylesheet_directory_uri().'/style.css', false, null);
}