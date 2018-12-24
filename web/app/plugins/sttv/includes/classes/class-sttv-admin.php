<?php

namespace STTV;

defined( 'ABSPATH' ) || exit;

class Admin {

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
        $pth = STTV_CRON_DIR.'sttv-cron.php';
        echo passthru("php -f $pth vcache 2>&1");
    }
}