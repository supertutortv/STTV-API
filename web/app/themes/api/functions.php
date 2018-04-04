<?php 

add_action( 'init', 'redirect_to_frontend', 0 );
function redirect_to_frontend() {
    global $pagenow;
    if ( $pagenow === 'wp-login.php' && !is_user_logged_in() ) {
        wp_redirect(home_url());
        exit;
    }
}