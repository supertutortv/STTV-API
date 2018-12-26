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
				],
				[
					'methods' => 'DELETE',
					'callback' => [ $this, 'reset' ],
					'permission_callback' => 'sttv_verify_web_token'
				]
			],
			'/notification' => [
				[
					'methods' => 'POST',
					'callback' => [ $this, 'single' ],
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
		$posts = get_posts([
			'post_type' => 'notifications',
			'post__not_in' => $notin
		]);

		return sttv_rest_response(
			'notification',
			'All valid notifications for this user',
			200,
			[ 'data' => array_map(function($post) {
				$d = new DateTime($post->post_date);
				return [
					'id' => $post->ID,
					'date' => $d->format('m/d/Y'),
					'title' => $post->post_title
				];
			},$posts) ]
		);
	}

	public function update(WP_REST_Request $req) {
		extract(json_decode($req->get_body(),true));
		$notin = get_user_meta( get_current_user_id(), 'cn_dismissed', true ) ?: [];
		$notin[] = $id;
		return sttv_rest_response(
			'notification',
			'Notification dismissed',
			200,
			[ 'updated' => update_user_meta( get_current_user_id(), 'cn_dismissed', $notin ) ]
		);
	}

	public function single(WP_REST_Request $req) {
		extract(json_decode($req->get_body(),true));
		$post = get_post($id);
		return sttv_rest_response(
			'notification',
			"Single notification $post->ID",
			200,
			[ 'data' => $post ]
		);
	}

	public function reset() {
		return update_user_meta( get_current_user_id(), 'cn_dismissed', [] );
	}
}