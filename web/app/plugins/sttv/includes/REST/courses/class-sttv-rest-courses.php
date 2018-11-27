<?php

namespace STTV\REST;

defined( 'ABSPATH' ) || exit;

use \WP_REST_Request;

/**
 * SupertutorTV feedback mechanism.
 *
 * All REST routes and endpoints for course and user data related to SupertutorTV's prep courses.
 *
 * @class 		\STTV\REST\Courses
 * @version		2.0.0
 * @package		STTV
 * @category	Class
 * @author		Supertutor Media, inc.
 */

class Courses extends \WP_REST_Controller {
	
	public function __construct() {}

	public function register_routes() {
		$routes = [
			'/data' => [
				[
					'methods' => 'GET',
					'callback' => [ $this, 'get_course_data' ],
					'permission_callback' => 'sttv_verify_web_token'
				],
				[
					'methods' => 'DELETE',
					'callback' => [ $this, 'delete_user_course_data' ],
					'permission_callback' => 'sttv_verify_web_token'
				]
			],
			'/data/(?P<patch>[\w]+)' => [
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
				]
			],
			'/practice' => [
				[
					'methods' => 'POST',
					'callback' => [ $this, 'parse_practice_data' ],
					'permission_callback' => 'sttv_verify_web_token'
				]
			],
			'/playlist' => [
				[

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
		$user = wp_get_current_user();
		$userid = get_current_user_id();
		
		$umeta = get_user_meta( $userid, 'sttv_user_data', true );

		if ( empty( $umeta ) ) return sttv_rest_response(
			'dataInvalid',
			'We\'re building the course data for you. Please wait...',
			200,
			[ 'retry' => 5 ]
		);

		if ( empty($umeta['courses']) ) {
			$crss = [];

			if ( current_user_can("course_sat_access") ) $crss['the-best-sat-prep-course-ever'] = [];
			if ( current_user_can("course_act_access") ) $crss['the-best-act-prep-course-ever'] = [];
			$umeta['courses'] = $crss;
		}

		$admin = current_user_can('manage_options');

		$access = $admin ? ['the-best-act-prep-course-ever'=>[],'the-best-sat-prep-course-ever'=>[]] : $umeta['courses'];

		foreach( $access as $slug => $data ) {
			$course = get_posts([
				'name' => $slug,
				'post_status' => 'publish',
				'posts_per_page' => 1,
				'post_type' => 'courses'
			]);
			$meta = get_post_meta( $course[0]->ID, 'sttv_course_data', true );
			if ( !$meta )
				return sttv_rest_response(
					'course_not_found',
					'The course requested was not found or does not exist. Please try again.',
					404
				);

			$test_code = strtolower($meta['test']);
			if ( !$admin && !current_user_can( "course_{$test_code}_access" ) ) continue;

			$trialing = !$admin && current_user_can( "course_trialing" );
			$umeta['user']['trialing'] = $trialing;

			$umeta['courses'][$slug] = (function() use (&$meta,$trialing) {
				foreach ( $meta['collections'] as $sec => &$val ) {
					if ( $sec === 'practice' ) continue;
					foreach ( $val['collection'] as $k => &$subsec ) {
						if ( $subsec['in_trial'] === false && $trialing ) {
							foreach ( $subsec['videos'] as &$vid ) {
								$vid['id'] = 0;
							}
						}
						unset( $subsec['in_trial'] );
					}

					foreach ( $val['downloads'] as &$dl ) {
						if ( $dl['in_trial'] === false && $trialing ) $dl['file'] = 0;
						unset( $dl['in_trial'] );
					}

				}

				foreach ( $meta['collections']['practice']['collection'] as $k => &$book ) {
					if ( $book['in_trial'] === false && $trialing ) {
						foreach ( $book['tests'] as $b => &$test ) {
							foreach ( $test['collection'] as $t => &$sec ) {
								foreach ( $sec['videos'] as $s => &$vid ) {
									$vid['id'] = 0;
								}
							}
						}
					}
					unset( $book['in_trial'] );
				}

				foreach ( $meta['collections']['practice']['downloads'] as &$dl ) {
					if ( $dl['in_trial'] === false && $trialing ) $dl['file'] = 0;
					unset( $dl['in_trial'] );
				}

				return $meta;
			})();

			$dbtable = $wpdb->prefix.'course_udata';
			$cu_data = $wpdb->get_results(
				$wpdb->prepare("SELECT * FROM $dbtable WHERE wp_id = %d AND udata_test = %s;",$userid,$meta['test'])
			,ARRAY_A);

			foreach ($cu_data as $rec) {
				$darray = $rec['udata_type'] !== 'playlist' ? $rec['udata_id'] : [
					'id' => (int) $rec['id'],
					'vidid' => $rec['udata_id'],
					'timestamp' => (int) $rec['udata_timestamp'],
					'name' => $rec['udata_name'],
					'thumb' => $rec['udata_thumb']
				];
				$umeta['courses'][$slug][$rec['udata_type']][] = $darray;
			}
			/* $umeta['courses'][$slug] = [
				'id' => $meta['id'],
				'name' => $meta['name'],
				'slug' => $meta['slug'],
				'test' => $meta['test'],
				'intro' => $meta['intro'],
				'type' => 'collection',
				'collections' => (function() use (&$meta,$trialing) {
					$sections = [];
					foreach ( $meta['sections'] as $sec => $val ) {
						foreach ( $val['files'] as &$file ) {
							if ( $file['in_trial'] === false && $trialing ) {
								$file['file'] = 0;
							}
							unset( $file['in_trial'] );
						}
						foreach ( $val['collection'] as $k => &$subsec ) {
							if ( $subsec['data']['in_trial'] === false && $trialing ) {
								foreach ( $subsec['collection'] as &$vid ) {
									$vid['ID'] = 0;
								}
							}
							unset( $subsec['data']['in_trial'] );
						}
						$sections[$sec] = $val;
					}
					foreach ( $meta['practice']['files'] as &$file ) {
						if ( ! $file['in_trial'] && $trialing ) {
							$file['file'] = 0;
							unset( $file['in_trial'] );
						}
					}
					foreach ( $meta['practice']['collection'] as $k => &$book ) {
						if ( ! $book['data']['in_trial'] && $trialing ) {
							foreach ( $book['collection'] as $b => &$test ) {
								foreach ( $test['collection'] as $t => &$sec ) {
									foreach ( $sec['collection'] as $s => &$vid ) {
										$vid['ID'] = 0;
									}
								}
							}
						}
						unset( $book['data']['in_trial'] );
					}
					$sections['practice'] = $meta['practice'];
					return $sections;
				})()
			]; */
		}

		$umeta['size'] = ( mb_strlen( json_encode( $umeta ), '8bit' )/1000 ) . 'KB';
		
		return sttv_rest_response(
			'user_course_data_success',
			'Boosh.',
			200,
			['data' => $umeta]
		);
	}

	public function update_user_course_data( WP_REST_Request $request ) {
		$updated = $allowed = [];
		$timestamp = time();
		if ( current_user_can('manage_options') || current_user_can( 'course_platform_access' ) ) {
			global $wpdb;

			extract(json_decode( $request->get_body(), true ), EXTR_PREFIX_ALL, 'udata');
			$userid = get_current_user_id();
			$patch = $request->get_param( 'patch' );
			$umeta = get_user_meta( $userid, 'sttv_user_data', true );
			$table = $wpdb->prefix.'course_udata';
			$exists = null;

			switch ( $patch ) {
				case 'playlist':
					$exists = $wpdb->get_row("SELECT * FROM $table WHERE wp_id = $userid AND udata_type = '".$patch."' AND udata_id = '".$udata_id."';");
				case 'history':
				case 'downloads':
					if ($exists) return sttv_rest_response(
						'resourceExists',
						'The resource already exists and cannot be duplicated.',
						200,
						['data' => $updated]
					);

					$allowed = [
						'wp_id' => $userid,
						'udata_type' => $patch,
						'udata_timestamp' => $timestamp,
						'udata_id' => isset($udata_id) ? $udata_id : $udata_hash,
						'udata_name' => $udata_name,
						'udata_thumb' => $udata_thumb,
						'udata_path' => isset($udata_path) ? $udata_path : $udata_file,
						'udata_test' => isset($udata_test) ? $udata_test : ''
					];
					
					$wpdb->insert( $table, $allowed, ['%d','%s','%d','%s','%s','%s','%s','%s'] );

					$updated = [
						'id' => (int) $wpdb->insert_id,
						'vidid' => isset($udata_id) ? $udata_id : $udata_hash,
						'timestamp' => $timestamp,
						'name' => $udata_name,
						'thumb' => $udata_thumb
					];
					break;
				case 'userdata':
					$allowed['userdata'] = [
						'firstname',
						'lastname',
						'address',
						'orders',
						'tests'
					];
				case 'settings':
					$allowed['settings'] = [
						'autoplay',
						'dark_mode',
						'default_course'
					];
					foreach( $body as $key => $val ) {
						if ( in_array($key,$allowed[$patch]) ) {
							$umeta['user'][$patch][$key] = $updated[$patch][$key] = $val;
						}
					}
					update_user_meta( $userid, 'sttv_user_data', $umeta );
					break;
				default:
					return sttv_rest_response(
						'invalid_patch_parameter',
						'The route you are trying to reach was not found due to an invalid patch parameter.',
						404
					);
			}

			return sttv_rest_response(
				'resourceUpdated',
				'The resource has been updated',
				200,
				['data' => $updated]
			);
		}
	}

	public function delete_user_course_data( WP_REST_Request $request ) {
		global $wpdb;
		$userid = get_current_user_id();
		$body = json_decode($request->get_body(),true);
		$deleted = [];
		if ( ! isset($body['id'] ) ) return sttv_rest_response(
			'resource_delete_no_id',
			'You must provide a valid resource id to this endpoint.',
			403
		);

		$delete = [
			'id' => $body['id'],
			'wp_id' => $userid
		];
		$result = $wpdb->delete( $wpdb->prefix.'course_udata', $delete, ['%d'] );

		if ( !$result ) {
			return sttv_rest_response(
				'resourceDeleteFail',
				'Delete error. No action taken.',
				200
			);
		} else {
			return sttv_rest_response(
				'resourceDeleteSuccess',
				'The resource was deleted.',
				200,
				['data' => [
					'id' => $body['id']
				]]
			);
		}
	}

	public function parse_practice_data( WP_REST_Request $request ) {
		$body = $request->get_body();
		//return gettype($body);
		$file = STTV_SCRIPTS_DIR . 'python/grade.py';
		$output = shell_exec("sudo python3 $file '$body'");
		return $output;
	}
}
