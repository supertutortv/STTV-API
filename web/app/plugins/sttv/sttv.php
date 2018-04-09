<?php
/*
Plugin Name:  STTV API
Plugin URI:   https://api.supertutortv.com
Description:  Separate standalone API for Supertutor Media web resources
Version:      1.0.0
Author:       David Paul
License:      MIT License
*/

if ( ! defined( 'ABSPATH' ) ) exit;

date_default_timezone_set('America/Los_Angeles');

require_once __DIR__ . '/includes/class-sttv.php';

$sttv = \STTV\STTV::instance();

//end of line, man.