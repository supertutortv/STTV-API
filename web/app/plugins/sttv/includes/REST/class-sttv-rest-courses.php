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
			'/data/(?P<patch>[\w]+)' => [
				[
					'methods' => 'GET',
					'callback' => [ $this, 'get_course_data' ],
					'permission_callback' => 'sttv_verify_web_token',
					'args' => [
						'patch' => [
							'required' => false,
							'type' => 'string'
						]
					]
				]
			],
			'/udata/(?P<patch>[\w]+)' => [
				[
					'methods' => 'PUT',
					'callback' => [ $this, 'update_user_course_data' ],
					'permission_callback' => 'sttv_verify_web_token',
					'args' => [
						'patch' => [
							'required' => true,
							'type' => 'string'
						]
					]
				],
				[
					'methods' => 'DELETE',
					'callback' => [ $this, 'delete_user_course_data' ],
					'permission_callback' => 'sttv_verify_web_token'
				]
			],
			'/feedback' => [
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
			register_rest_route( 'courses', $route, $endpoint );
		}
	}

	##########################
	##### COURSE METHODS #####
	##########################

	public function get_course_data( $req ) {
		global $wpdb;
		$userid = get_current_user_id();
		$umeta = get_user_meta( $userid, 'sttv_user_data', true );

		if ( !isset( $umeta['courses'] ) ) return sttv_rest_response(
			'course_data_invalid',
			'The course data requested for this user is invalid or corrupted. Please contact SupertutorTV for further assistance.',
			400
		);

		$dbtable = $wpdb->prefix.'course_udata';
		$cu_data = $wpdb->get_results("SELECT id,udata_type,udata_timestamp,udata_record FROM $dbtable WHERE wp_id = $userid;",ARRAY_A);

		foreach ($cu_data as $rec) {
			$ind = (int) $rec['udata_timestamp'];
			$umeta['user'][$rec['udata_type']][] = [
				'id' => (int) $rec['id'],
				'timestamp' => $ind,
				'data' => json_decode($rec['udata_record'])
			];
		}

		foreach( $umeta['courses'] as $slug => $data ) {
			$course = get_posts([
				'name' => $slug,
				'post_status' => 'publish',
				'posts_per_page' => 1,
				'post_type' => 'courses'
			]);
			$meta = get_post_meta( $course[0]->ID, 'sttv_course_data', true );
			if ( ! $meta ) {
				return sttv_rest_response(
					'course_not_found',
					'The course requested was not found or does not exist. Please try again.',
					404
				);
			}

			$test_code = strtolower($meta['test']);
			$trialing = current_user_can( "course_{$test_code}_trial" );
			
			$umeta['courses'][$slug] = [
				'id' => $meta['id'],
				'name' => $meta['name'],
				'slug' => $meta['slug'],
				'link' => $meta['link'],
				'test' => $meta['test'],
				'intro' => $meta['intro'],
				'version' => STTV_VERSION,
				'thumbUrls' => [
					'plain' => 'https://i.vimeocdn.com/video/||ID||_295x166.jpg?r=pad',
					'withPlayButton' => 'https://i.vimeocdn.com/filter/overlay?src0=https%3A%2F%2Fi.vimeocdn.com%2Fvideo%2F||ID||_295x166.jpg&src1=http%3A%2F%2Ff.vimeocdn.com%2Fp%2Fimages%2Fcrawler_play.png'
				],
				'sections' => (function() use (&$meta,$trialing) {
					$sections = [];
					foreach ( $meta['sections'] as $sec => $val ) {
						foreach ( $val['resources']['files'] as &$file ) {
							if ( $file['in_trial'] === false && $trialing ) {
								$file['file'] = 0;
							}
							unset( $file['in_trial'] );
						}
						foreach ( $val['subsec'] as $k => &$subsec ) {
							if ( $subsec['in_trial'] === false && $trialing ) {
								foreach ( $subsec['videos'] as &$vid ) {
									$vid['ID'] = 0;
								}
							}
							unset( $subsec['in_trial'] );
						}
						$sections[$sec] = $val;
					}
					foreach ( $meta['practice']['resources']['files'] as &$file ) {
						if ( ! $file['in_trial'] && $trialing ) {
							$file['file'] = 0;
							unset( $file['in_trial'] );
						}
					}
					foreach ( $meta['practice']['subsec'] as $k => &$book ) {
						if ( ! $book['in_trial'] && $trialing ) {
							foreach ( $book['subsec'] as $b => &$test ) {
								foreach ( $test['subjects'] as $t => &$sec ) {
									foreach ( $sec['videos'] as $s => &$vid ) {
										$vid['ID'] = 0;
									}
								}
							}
						}
						unset( $book['in_trial'] );
					}
					$sections['practice'] = $meta['practice'];
					return $sections;
				})()
			];
		}

		$umeta['size'] = ( mb_strlen( json_encode( $umeta ), '8bit' )/1000 ) . 'KB';
		
		return sttv_rest_response(
			'user_course_data_success',
			'Here is your data.',
			200,
			['data' => $umeta]
		);
	}

	public function update_user_course_data( WP_REST_Request $request ) {
		global $wpdb;
		$userid = get_current_user_id();
		$body = json_decode( $request->get_body(), true );
		$patch = $request->get_param( 'patch' );
		$updated = [];
		$timestamp = time();
		switch ( $patch ) {
			case 'history':
			case 'bookmarks':
			case 'downloads':
				$updated = [
					'wp_id' => $userid,
					'udata_type' => $patch,
					'udata_timestamp' => $timestamp,
					'udata_record' => json_encode($body)
				];
				$wpdb->insert( $wpdb->prefix.'course_udata', $updated, ['%d','%s','%d','%s'] );
				$updated['udata_record'] = json_decode($updated['udata_record']);
				break;
			case 'userdata':
			case 'options':
				$updated = get_user_meta( $userid, 'sttv_user_data', true );
				update_user_meta( $userid, 'sttv_user_data', $umeta );
			default:
				return sttv_rest_response(
					'invalid_patch_parameter',
					'The route you are trying to reach was not found due to an invalid patch parameter.',
					404
				);
		}

		return sttv_rest_response(
			'resource_updated',
			'The resource has been updated',
			200,
			['data' => $updated]
		);
	}

	public function delete_user_course_data( WP_REST_Request $request ) {
		global $wpdb;
		$body = json_decode($request->get_body(),true);
		$deleted = [];
		if ( ! isset($body['id'] ) ) return sttv_rest_response(
			'resource_delete_no_id',
			'You must provide a valid resource id to this endpoint.',
			403
		);
		if ( !is_array($body['id']) ) $body['id'] = [$body['id']];
		foreach( $body['id'] as $v ) {
			$delete = [
				'id' => $v
			];
			$result = $wpdb->delete( $wpdb->prefix.'course_udata', $delete, ['%d'] );
			if ( $result === false ) {
				$deleted[] = [
					'id' => 'There was an error. ID '.$v.' not deleted.'
				];
				continue;
			};
			if ( $result === 0 ) {
				$deleted[] = [
					'id' => 'ID '.$v.' could not be found. No action taken.'
				];
				continue;
			}
			$deleted[] = [
				'id' => $v
			];
		}
		return sttv_rest_response(
			'resource_delete_success',
			'The resource was deleted.',
			200,
			['data' => $deleted]
		);
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

	public function can_post_feedback( WP_REST_Request $req ) {
		$valid = sttv_verify_web_token( $req );
		if ( is_wp_error( $valid ) )
			return $valid;
		return current_user_can('course_post_feedback');
	}
	
	public function get_user_feedback() {
		return true;
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