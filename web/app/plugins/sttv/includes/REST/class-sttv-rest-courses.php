<?php

namespace STTV\REST;

defined( 'ABSPATH' ) || exit;

/**
 * SupertutorTV feedback mechanism.
 *
 * All REST routes and endpoints for SupertutorTV's prep courses.
 *
 * @class 		\STTV\REST\Courses
 * @version		2.0.0
 * @package		STTV
 * @category	Class
 * @author		Supertutor Media, inc.
 */

class Courses extends \WP_REST_Controller {
	
	public function __construct() {
		add_action( 'save_post_feedback', [ $this, 'update_feedback_with_uniqueid' ], 0, 3);
		add_action( 'wp_trash_post', [ $this, 'delete_feedback_transient' ] );
	}

	public function register_routes() {
 		register_rest_route( STTV_REST_NAMESPACE, '/feedback', 
			array(
				array(
					'methods' => 'GET',
					'callback' => array($this,'get_user_feedback'),
					'permission_callback' => 'is_user_logged_in'
                ),
                array(
					'methods' => 'POST',
					'callback' => array($this,'post_feedback'),
					'permission_callback' => array($this,'can_post_feedback')
				)
			)
        );
        register_rest_route( STTV_REST_NAMESPACE, '/feedback/reply', 
            array(
                array(
                    'methods' => 'POST',
                    'callback' => array($this,'post_feedback_reply'),
                    'permission_callback' => array($this,'can_post_feedback')
                )
            )
        );
	} // end sttv_feedback_api

	public function can_post_feedback() {
		return current_user_can('course_post_feedback') ?: is_user_logged_in();
    }
	
	public function get_user_feedback() {
		$user = wp_get_current_user();
		ob_start();
		require STTV_TEMPLATE_DIR.'courses/feedback_post.php';
		return [ 'templateHtml'=>ob_get_clean(),'user'=>$user ];
	}

	public function post_feedback(WP_REST_Request $req){
		$body = json_decode($req->get_body());
		if (get_transient('sttv_cfbrp:'.$body->student)){return false;}

		return !!wp_insert_post(
			[
				'post_type'=>'feedback',
				'post_status'=>'publish',
				'post_author'=>$body->student,
				'post_content'=>sanitize_text_field($body->content),
				'post_parent'=>$body->postID
			]
		);
	}

	public function update_feedback_with_uniqueid($post_ID,$post,$update) {
		$parent = get_post($post->post_parent);
		if (!$update && $parent) :
			global $wpdb;
			$uid = sttvhashit(STTV_PREFIX.'/'.STTV_VERSION.'/'.$post->post_author.'/'.$post_ID.'/'.$post->post_parent,12);
			$title = 'Feedback - '.$parent->post_title.' ('.$uid.')';

			$uu = $wpdb->update('wp_posts',
				[ 'post_excerpt'=>$uid,'post_title'=>$title ],
				[ 'ID'=>$post_ID ]
			);

			if ($uu) {
				$user = get_user_by('ID',$post->post_author);
				$subj = 'Customer '.$user->first_name.' '.$user->last_name.' left feedback about '.$parent->post_title;

				$headers[] = 'Content-Type: text/html; charset=UTF-8';
				$headers[] = 'From: '.$user->first_name.' '.$user->last_name.' <'.$user->user_email.'>';
				$headers[] = 'Sender: SupertutorTV Website <info@supertutortv.com>';

				wp_mail(
					get_option('admin_email'),
					$subj,
					$post->post_content,
					$headers
				);
				set_transient('sttv_cfbrp:'.$post->post_author,true,DAY_IN_SECONDS);
			}
		endif;
	}

	public function delete_feedback_transient($id) {
		$delete = get_post($id);
		return delete_transient('sttv_cfbrp:'.$delete->post_author);
	}
	
}