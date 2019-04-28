<?php

$version = file_get_contents(__DIR__.'/VERSION');

/*
Plugin Name:  STTV API
Plugin URI:   https://app.supertutortv.com
Description:  Separate standalone API for Supertutor Media web resources
Version:      {$version}
Author:       David Paul
License:      MIT License
*/

if ( ! defined( 'ABSPATH' ) ) exit;

date_default_timezone_set('America/Los_Angeles');

if ( ! defined( 'STTV_PLUGIN_FILE' ) ) {
	define( 'STTV_PLUGIN_FILE', __FILE__ );
}

require_once __DIR__ . '/includes/class-sttv.php';

$sttv = \STTV\STTV::instance($version);

//end of line, man.