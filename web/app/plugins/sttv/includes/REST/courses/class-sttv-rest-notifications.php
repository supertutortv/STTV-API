<?php

namespace STTV\REST\Courses;

defined( 'ABSPATH' ) || exit;

use \WP_REST_Request;

class Notifications extends \WP_REST_Controller {
	
	public function __construct() {}

	public function register_routes() {
		$routes = [
			'/notifications' => [
				[
					'methods' => 'GET',
					'callback' => [ $this, 'retrieve' ],
					'permission_callback' => 'sttv_verify_web_token'
				],
				[
					'methods' => 'PUT',
					'callback' => [ $this, 'update' ],
					'permission_callback' => 'sttv_verify_web_token'
				]
			]
		];

		foreach ( $routes as $route => $endpoint ) {
			register_rest_route( 'courses', $route, $endpoint );
		}
	}

	public function retrieve(WP_REST_Request $req) {
		$notin = get_user_meta( get_current_user_id(), 'cn_dismissed', true ) ?: [];
		return get_posts(['post_type'=>'notifications','post__not_in'=>$notin]);
	}

	public function update(WP_REST_Request $req) {
		extract(json_decode($req->get_body(),true));
		$notin = get_user_meta( get_current_user_id(), 'cn_dismissed', true ) ?: [];
		$notin[] = $id;
		return update_user_meta( get_current_user_id(), 'cn_dismissed', $notin );
	}
}