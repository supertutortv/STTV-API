<?php

if (!isset($_GET['checksum'])){
	die;
} else {
	$test = strtolower(trim($_GET['test']));
	$sec = trim($_GET['section']);
	$file = trim($_GET['res']);
	$hash = trim($_GET['checksum']);
	
	$root_path = dirname(__DIR__).'/resources/'.$test.'/'.$sec.'/';
	
	if (is_file($root_path.$file) && $hash == md5_file($root_path.$file)) {
		header("Pragma: public");
    	header("Expires: 0");
    	header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
		header('Content-Description: File Transfer');
		header('Content-type: application/octet-stream');
		header('Content-disposition: attachment;filename="'.$file.'"');
		header("Content-Length: ".filesize($root_path.$file));
		header("Content-Transfer-Encoding: binary");
    	readfile($root_path.$file);
		
		http_response_code(200);
	} else {
		http_response_code(404);
		echo json_encode(array('fail'=>true));
	}
}
die;