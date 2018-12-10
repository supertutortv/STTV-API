<?php

if ( ! defined( 'ABSPATH' ) ) {exit;}

function sttv_lostpw_url(){
    return STTV_FRONTEND.'/password/reset';
}

function sttv_diff( $file1, $file2 ) {
	return ( file_get_contents($file1) !== file_get_contents($file2) );
}

function sttv_get_template($temp,$dir='',$sgt=null) {
	$dir = (!empty($dir))?"{$dir}/":"";
	$path = STTV_TEMPLATE_DIR.$dir.$temp;
	$extension = file_exists($path.'.php') ? '.php': '.html';
	
	require $path.$extension;
}

function sttv_array_map_recursive($callback, &$array) {
	$func = function ($item) use (&$func, &$callback) {
		return is_array($item) ? array_map($func, $item) : call_user_func($callback, $item);
	};
  return array_map($func, $array);
}

function sttvhashit($input,$num = 9) {
	return base64_encode(substr(md5($input),0,$num));
}

function sttv_uid ( $prefix = '', $random = '', $entropy = false, $length = 0 ){
	$string = trim( $prefix . preg_replace('/[^A-Za-z0-9\-]/', '', base64_encode( uniqid( $random, $entropy ) ) ), '=');
	return substr( $string, 0, ($length ?: strlen($string)) );
}

function sttv_id_encode( $hid ) {
	return dechex($hid*100000);
}

function sttv_id_decode( $hid ) {
	return hexdec($hid) / 100000;
}

function sttv_404_redirect() {
	global $wp_query;
	$wp_query->set_404();
	status_header( 404 );
	get_template_part( 404 );
}

function __return_email_from() {
	return get_option('admin_email');
}

function __return_email_from_name() {
	return STTV_BRANDNAME;
}

function __return_email_content_type() {
	return 'text/html';
}

/* Subscribe the user to SupertutorTV's Mailchimp mailing list */
function sttv_mailinglist_subscribe( $email = '', $firstname = '', $lastname = '' ) {
	if ( empty( $email ) || empty( $firstname ) || empty( $lastname ) ) {
		return new \WP_Error( 'null_body', 'The request body cannot be empty for this endpoint.', 400 );
	}
	
	return wp_remote_post( 'https://us7.api.mailchimp.com/3.0/lists/df497b5cbd/members/'.md5( strtolower( $email ) ),
		[
			'headers' => [
				'Authorization' => 'apikey '.MAILCHIMP_API_KEY,
				'Content-Type' => 'application/json',
				'X-HTTP-Method-Override' => 'PUT',
				'User Agent' => STTV_UA
			],
			'body' => json_encode([
				'email_address' => $email,
				'status' => 'subscribed',
				'status_if_new' => 'subscribed',
				'merge_fields' => [
					'FNAME' => $firstname,
					'LNAME' => $lastname
				],
				'ip_signup' => $_SERVER['REMOTE_ADDR']
			])
		]
	);

}

function email_user_meta( $null, $object_id, $meta_key, $meta_value, $prev_value ) {
	if ( $meta_key === "sttv_user_data" ) {
		$email = new \STTV\Email\Standard([
			'to' => 'dave@supertutortv.com',
			'subject' => 'User meta updated',
			'message' => '<pre>'.json_encode(func_get_args(),JSON_PRETTY_PRINT).'</pre><pre>'.json_encode(debug_backtrace(),JSON_PRETTY_PRINT).'</pre>'
		]);
		$email->send();
	}
}