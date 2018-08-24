<?php

echo strpos($_SERVER['REQUEST_URI'],'/auth/verify');
die;

if (strpos($_SERVER['REQUEST_URI'],'/auth/verify') > -1 && !isset($_COOKIE['stAuth'])) echo json_encode(['data' => false]) && die;

/** WordPress view bootstrapper */
define('WP_USE_THEMES', true);
require(__DIR__ . '/wp/wp-blog-header.php');
