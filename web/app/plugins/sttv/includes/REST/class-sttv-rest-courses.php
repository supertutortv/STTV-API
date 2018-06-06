<?php

namespace STTV\REST;

defined( 'ABSPATH' ) || exit;

use \WP_REST_Request;

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
		$routes = [
			'/courses' => [
				[
					'methods' => 'GET',
					'callback' => [ $this, 'get_course_info' ]
				]
			],
			'/courses/(?P<id>[\d]+)' => [
				[
					'methods' => 'GET',
					'callback' => [ $this, 'get_course_meta' ],
					'permission_callback' => '__return_true'
				]
			],
			'/courses/log' => [
				[
					'methods' => 'POST',
					'callback' => [ $this, 'course_access_log' ],
					'permission_callback' => 'is_user_logged_in'
				]
			],
			'/courses/alert' => [
				[
					'methods' => 'GET',
					'callback' => [ $this, 'get_course_meta' ],
					'permission_callback' => [ $this, 'course_permissions_check' ]
				]
			],
			'/courses/feedback' => [
				[
					'methods' => 'GET',
					'callback' => [ $this, 'get_user_feedback' ],
					'permission_callback' => '__return_true'
				],
                [
					'methods' => 'POST',
					'callback' => [ $this, 'post_feedback' ],
					'permission_callback' => [ $this, 'can_post_feedback' ]
				],
				[
                    'methods' => 'PUT',
                    'callback' => [ $this, 'post_feedback_reply' ],
                    'permission_callback' => [ $this, 'can_post_feedback' ]
				]
			]
		];

		foreach ( $routes as $route => $endpoint ) {
			register_rest_route( STTV_REST_NAMESPACE, $route, $endpoint );
		}
	}

	##########################
	##### COURSE METHODS #####
	##########################

	public function get_course_info() {

	}

	public function get_course_meta( $req ) {		
		$meta = get_post_meta( $req['id'], 'sttv_course_data' , true );
		if ( ! $meta ) {
			return sttv_rest_response(
				'course_not_found',
				'The course requested was not found or does not exist. Please try again.',
				404
			);
		}

		$test_code = strtolower($meta['test']);
		$trialing = current_user_can( "course_{$test_code}_trial" );
		
		$data = [
			'id' => $meta['id'],
			'name' => $meta['name'],
			'slug' => $meta['slug'],
			'link' => $meta['link'],
			'test' => $meta['test'],
			'intro' => $meta['intro'],
			'version' => STTV_VERSION,
			'lastFetched' => time(),
			'thumbUrls' => [
				'plain' => 'https://i.vimeocdn.com/video/||_295x166.jpg?r=pad',
				'withPlayButton' => 'https://i.vimeocdn.com/filter/overlay?src0=https%3A%2F%2Fi.vimeocdn.com%2Fvideo%2F||_295x166.jpg&src1=http%3A%2F%2Ff.vimeocdn.com%2Fp%2Fimages%2Fcrawler_play.png'
			]
		];
		
		foreach ( $meta['sections'] as $sec => $val ) {
			foreach ( $val['resources']['files'] as &$file ) {
				if ( ! $file['in_trial'] && $trialing ) {
					$file['file'] = 0;
				}
				unset( $file['in_trial'] );
			}
			foreach ( $val['subsec'] as $k => &$subsec ) {
				if ( ! $subsec['in_trial'] && $trialing ) {
					foreach ( $subsec['videos'] as &$vid ) {
						$vid['ID'] = 0;
					}
				}
				unset( $subsec['in_trial'] );
			}
			$data['sections'][$sec] = $val;
		}

		foreach ( $meta['practice']['resources']['files'] as &$file ) {
			if ( ! $file['in_trial'] && $trialing ) {
				$file['file'] = 0;
				unset( $file['in_trial'] );
			}
		}
		foreach ( $meta['practice']['books'] as $k => &$book ) {
			if ( ! $book['in_trial'] && $trialing ) {
				foreach ( $book['tests'] as $b => &$test ) {
					foreach ( $test['sections'] as $t => &$sec ) {
						foreach ( $sec['videos'] as $s => &$vid ) {
							$vid['ID'] = 0;
						}
					}
				}
				unset( $test['in_trial'] );
			}
		}

		$data['practice'] = $meta['practice'];
		
		$data['size'] = ( mb_strlen( json_encode( $data ), '8bit' )/1000 ) . 'KB';
		
		return $data;

	}

	#######################
	##### LOG METHODS #####
	#######################

	public function course_access_log( WP_REST_Request $request ) {
		$data = [
			date('c',time()),
			$_SERVER['REMOTE_ADDR']
		];
		$data = array_merge($data, json_decode($request->get_body(), true));
		$data = implode(' | ',$data);
		return file_put_contents( STTV_LOGS_DIR . get_current_user_id() . '.log', PHP_EOL . $data . PHP_EOL, FILE_APPEND );
	}

	############################
	##### FEEDBACK METHODS #####
	############################

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
		$delete = get_post( $id );
		return delete_transient( 'sttv_cfbrp:' . $delete->post_author );
	}
	
}