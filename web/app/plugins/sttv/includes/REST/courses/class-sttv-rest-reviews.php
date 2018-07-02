<?php
namespace STTV\REST\Courses;

defined( 'ABSPATH' ) || exit;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * SupertutorTV course user reviews.
 *
 * REST endpoints for adding, displaying, and updating course reviews submitted by subscribed students.
 *
 * @class 		Reviews
 * @version		2.0.0
 * @package		STTV
 * @category	Class
 * @author		Supertutor Media, inc.
 */

class Reviews extends \WP_REST_Controller {
	
	public function __construct() {}
	
	public function register_routes() {
		$routes = [
			'/reviews' => [
				[
					'methods' => 'PUT',
					'callback' => [ $this, 'post_product_review' ],
					'permission_callback' => [ $this, 'can_post_reviews' ]
				],
				[
					'methods' => 'POST',
					'callback' => [ $this, 'get_review_template' ],
					'permission_callback' => [ $this, 'can_post_reviews' ]
				]
			],
			'/reviews/(?P<id>[\d]+)' => [
				[
					'methods' => 'GET',
					'callback' => [ $this, 'get_product_reviews' ],
					'args' => [
						'id' => [
							'validate_callback' => 'absint',
							'required' => true
						]
					]
				]
			]
		];

		foreach ( $routes as $route => $endpoint ) {
			register_rest_route( 'courses', $route, $endpoint );
		}
	}

	public function can_post_reviews( WP_REST_Request $req ) {
		$valid = sttv_verify_web_token( $req );
		if ( is_wp_error( $valid ) )
			return $valid;
		return current_user_can('course_post_reviews');
	}
	
	public function get_product_reviews( $data ) {
		return get_comments([
			'post_id' => $data['id'],
			'status' => 'approve'
		]);
	}
	
	public function post_product_review(WP_REST_Request $request) {
		if ($this->review_exists($request)) return false;

		$body = json_decode( $request->get_body() );
		$user = wp_get_current_user();
		$full_name = explode( ' ', $user->display_name );
		$name = $full_name[0].' '.substr( $full_name[1], 0, 1 ).'.';
		
		$comment = wp_insert_comment([
			'comment_post_ID' => $body['post'],
			'comment_approved' => 0,
			'comment_karma' => $body['rating'],
			'comment_content' => $body['comment_content'],
			'comment_agent' => $body['UA'],
			'comment_author' => $name,
			'comment_author_IP' => $_SERVER['REMOTE_ADDR'],
			'comment_author_email' => $user->user_email,
			'user_id' => $user->ID
		]);
		
		if ( is_wp_error($comment) ) return $comment;
			return sttv_rest_response(
				'course_review_posted',
				'Your review was successfully posted.',
				200,
				[ 'templateHtml' => file_get_contents( STTV_TEMPLATE_DIR.'courses/ratings_thankyou.html' ) ] );
	}
	
	public function review_exists($wpr) {
		if ( $wpr instanceof \WP_REST_Request ){
			$body = json_decode( $wpr->get_body(), true );
			return !!get_comments([
				'post_id'=>$body['post'],
				'user_id'=>$body['user_id'],
				'count'=>true,
				'post_status'=>'any'
			]);
		}
		return false;
	}
	
	public function get_review_template(WP_REST_Request $request) {
		$template = (!$this->review_exists($request)) ? file_get_contents(STTV_TEMPLATE_DIR.'courses/ratings_post.html') : file_get_contents(STTV_TEMPLATE_DIR.'courses/ratings_thankyou.html');
		return sttv_rest_response(['templateHtml'=>$template]);
	}
	
}