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
		$userid = get_current_user_id();
		$umeta = get_user_meta( $userid, 'sttv_user_data', true );

		if ( empty( $umeta['courses'] ) ) return sttv_rest_response(
			'dataInvalid',
			'We\'re building the course data for you. Please wait...',
			200,
			[ 'retry' => 5 ]
		);

		foreach( $umeta['courses'] as $slug => $data ) {
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
			if ( !current_user_can( "course_{$test_code}_access" ) ) continue;

			$trialing = current_user_can( "course_{$test_code}_trialing" );
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

				foreach ( $meta['downloads'] as &$dlObj ) {
					foreach ( $dlObj['files'] as &$dl ) {
						if ( $dl['in_trial'] === false && $trialing ) $dl['file'] = 0;
						unset( $dl['in_trial'] );
					}
				}

				unset($meta['capabilities']);

				return $meta;
			})();
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

		global $wpdb;
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
		if ( current_user_can( 'course_platform_access' ) ) {
			global $wpdb;
			$userid = get_current_user_id();
			extract(json_decode( $request->get_body(), true ), EXTR_PREFIX_ALL, 'udata');
			$patch = $request->get_param( 'patch' );
			$umeta = get_user_meta( $userid, 'sttv_user_data', true );
			switch ( $patch ) {
				case 'history':
				case 'bookmarks':
				case 'downloads':
					$allowed = [
						'wp_id' => $userid,
						'udata_type' => $patch,
						'udata_timestamp' => $timestamp,
						'udata_id' => $udata_id,
						'udata_name' => $udata_name
					];
					return $allowed;
					$wpdb->insert( $wpdb->prefix.'course_udata', $allowed, ['%d','%s','%d','%s'] );
					$updated = [
						'id' => (int) $wpdb->insert_id,
						'timestamp' => $timestamp,
						'data' => json_decode($allowed['udata_record'])
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
				'resource_updated',
				'The resource has been updated',
				200,
				['data' => $updated]
			);
		}
	}

	public function delete_user_course_data( WP_REST_Request $request ) {
		if ( current_user_can( 'course_access_cap' ) ) {
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
	}

	public function parse_practice_data( WP_REST_Request $request ) {
		$body = $request->get_body();
		//return gettype($body);
		$file = STTV_SCRIPTS_DIR . 'python/grade.py';
		$output = shell_exec("sudo python3 $file '$body'");
		return $output;
	}
}