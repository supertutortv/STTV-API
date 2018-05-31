<?php
if ( ! defined( 'ABSPATH' ) ) exit;
/**
 * SupertutorTV product page reviews.
 *
 * REST endpoints for adding, displaying, and updating product reviews.
 *
 * @class 		STTV_Product_Reviews
 * @version		1.1.0
 * @package		STTV
 * @category	Class
 * @author		Supertutor Media, inc.
 */

class STTV_Product_Reviews extends WP_REST_Controller {
	
	public function __construct() {
		add_action( 'rest_api_init', array($this,'sttv_product_reviews_api') );
		
		register_shutdown_function( array( $this, '__destruct' ) );
	}
	
	public function __destruct() {
        return true;
    }
	
	public function sttv_product_reviews_api() {
 		register_rest_route( STTV_REST_NAMESPACE, '/reviews/(?P<id>[\d]+)', 
			array(
				array(
					'methods' => 'GET',
					'callback' => array($this,'get_product_reviews'),
					'permission_callback' => 'is_user_logged_in',
					'args' => array(
						'id' => array(
							'validate_callback' => 'absint',
							'required' => true
						)
					)
				)
			)
		);
		register_rest_route( STTV_REST_NAMESPACE, '/reviews/',
			array(
				array(
					'methods' => 'PUT',
					'callback' => array($this,'post_product_review'),
					'permission_callback' => array($this,'can_post_reviews')
				),
				array(
					'methods' => 'POST',
					'callback' => array($this,'get_review_template'),
					'permission_callback' => 'is_user_logged_in'
				)
			)
		);
	} // end sttv_product_reviews_api
	
	public function get_product_reviews($data) {
		$comments = get_comments(array('post_id'=>$data['id'],'status'=>'approve'));
		return $comments;
	}
	
	public function post_product_review(WP_REST_Request $request) {
		if ($this->review_exists($request)){
			return false;
			exit();
		}
		$body = json_decode($request->get_body());
		
		$user = get_user_by('id', $body->user_id);
		$full_name = explode(' ',$user->display_name);
		$name = $full_name[0].' '.substr($full_name[1],0,1).'.';
		
		$args = array(
			'comment_post_ID'=>$body->post,
			'comment_approved'=>0,
			'comment_karma'=>$body->rating,
			'comment_content'=>$body->comment_content,
			'comment_agent'=>$body->UA,
			'comment_author'=>$name,
			'comment_author_IP'=>$_SERVER['REMOTE_ADDR'],
			'comment_author_email'=>$user->user_email,
			'user_id'=>$user->ID
		);
		$comment = wp_insert_comment($args);
		
		if (!is_wp_error($comment)){
			return rest_ensure_response(array('templateHtml'=>file_get_contents(STTV_TEMPLATE_DIR.'courses/ratings_thankyou.html')));
		} else {
			return rest_ensure_response(array('error'=>$comment));
		}
		die;
	}
	
	public function can_post_reviews() {
		return current_user_can( 'course_post_reviews' )?:is_user_logged_in();
	}
	
	public function review_exists($wpr) {
		if (is_object($wpr)){
			$body = json_decode($wpr->get_body());
			return !!get_comments(array('post_id'=>$body->post,'user_id'=>$body->user_id,'count'=>true,'post_status'=>'any'));
		} else {
			return false;
		}
	}
	
	public function get_review_template(WP_REST_Request $request) {
		$template = (!$this->review_exists($request)) ? file_get_contents(STTV_TEMPLATE_DIR.'courses/ratings_post.html') : file_get_contents(STTV_TEMPLATE_DIR.'courses/ratings_thankyou.html');
		return array('templateHtml'=>$template);
	}
	
}
new STTV_Product_Reviews;