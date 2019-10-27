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
		$userid = $user->ID;
		
		$umeta = get_user_meta( $userid, 'sttv_user_data', true );

		if ( empty( $umeta ) ) return sttv_rest_response(
			'dataInvalid',
			'We\'re still building your account for you. Please wait...',
			200,
			[ 'retry' => 3000 ]
		);

		//if (is_null($umeta['user']['trialing'])) $umeta['user']['trialing'] = current_user_can( "course_trialing" );
		
		$crss = [];
		if ( current_user_can( "course_sat_access" ) ) $crss['the-best-sat-prep-course-ever'] = [];
		if ( current_user_can( "course_act_access" ) ) $crss['the-best-act-prep-course-ever'] = [];
		$umeta['courses'] = $crss;

		if ( !isset($umeta['user']['subscription']) || empty(isset($umeta['user']['subscription'])) ) $umeta['user']['subscription'] = get_user_meta( $userid, 'subscription_id', true );

		if ( !is_array( $umeta['user']['settings']['autoplay']) )
			$umeta['user']['settings']['autoplay'] = [
				'msl' => false,
				'playlist' => false
			];
		
		$umeta['user']['data'] = [
			'fullname' => $user->display_name,
			'firstname' => $user->user_firstname,
			'lastname' => $user->user_lastname,
			'email' => $user->user_email,
			'ID' => $userid,
			'uuid' => $user->user_login
		];
		
		$admin = (current_user_can('manage_options') || current_user_can('course_editor'));

		$access = $admin ? ['the-best-act-prep-course-ever'=>[],'the-best-sat-prep-course-ever'=>[]] : $umeta['courses'];

		foreach( $access as $slug => $data ) {
			$course = get_posts([
				'name' => $slug,
				'post_status' => 'publish',
				'posts_per_page' => 1,
				'post_type' => 'courses'
			]);
			$meta = get_post_meta( $course[0]->ID, 'sttv_course_data', true );
			if ( !$meta ) return sttv_rest_response(
				'course_not_found',
				'The course requested was not found or does not exist. Please try again.',
				404
			);

			$test_code = strtolower($meta['test']);

			if ( !$admin && !current_user_can( "course_{$test_code}_access" ) ) continue;

			$trialing = !!(!$admin && current_user_can( "course_{$test_code}_trial_access" ));

			$failFlag = !!get_user_meta($userid, "invoiceFailFlag-$test_code", true);

			$umeta['courses'][$slug] = (function() use (&$meta,$trialing,$user,$failFlag) {
				$meta['trialing'] = $trialing;
				
				foreach ( $meta['collections'] as $sec => &$val ) {
					if ( $sec === 'practice' ) continue;

					if ( $failFlag || !current_user_can($val['permissions']) ) {
						unset( $meta['collections'][$sec] );
						continue;
					}

					foreach ( $val['collection'] as $k => &$subsec ) {
						if ( $failFlag || !current_user_can($subsec['permissions']) ) {
							unset($val['collection'][$k]);
							continue;
						}

						if ( $subsec['in_trial'] === false && $trialing ) {
							foreach ( $subsec['videos'] as &$vid ) {
								$vid['id'] = 0;
							}
						}

						unset( $subsec['permissions'] );
						unset( $subsec['in_trial'] );
					}

					foreach ( $val['downloads'] as &$dl ) {
						if ( $dl['in_trial'] === false && $trialing ) $dl['file'] = 0;
						unset( $dl['in_trial'] );
					}

					unset( $val['permissions'] );

				}

				foreach ( $meta['collections']['practice']['collection'] as $k => &$book ) {
					if ( $failFlag || !current_user_can($book['permissions']) ) {
						unset($meta['collections']['practice']['collection'][$k]);
						continue;
					}

					foreach ( $book['tests'] as $b => &$test ) {
						if ( $failFlag || !current_user_can($test['permissions']) ) {
							unset($book['tests'][$b]);
							continue;
						}

						foreach ( $test['collection'] as $t => &$sec ) {
							foreach ( $sec['videos'] as $s => &$vid ) {
								if ( $book['in_trial'] === false && $trialing ) {
									$vid['id'] = 0;
								}
							}
						}

						unset( $test['permissions'] );
					}

					unset( $book['permissions'] );
					unset( $book['in_trial'] );
				}

				foreach ( $meta['collections']['practice']['downloads'] as &$dl ) {
					if ( $dl['in_trial'] === false && $trialing ) $dl['file'] = 0;
					unset( $dl['in_trial'] );
				}

				return $meta;
			})();

			$umeta['courses'][$slug]['subId'] = get_user_meta( $userid, "sub_id-$test_code", true);
			$umeta['courses'][$slug]['failFlag'] = $failFlag;

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
						'udata_id' => $udata_id ?? $udata_hash,
						'udata_name' => $udata_name,
						'udata_thumb' => $udata_thumb,
						'udata_path' => $udata_path ?? $udata_file,
						'udata_test' => $udata_test ?? ''
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
					break;
				case 'settings':
					$allowed['settings'] = [
						'autoplay'
					];

					if ( $udata_autoplay ) {
						if ( !is_array( $umeta['user']['settings']['autoplay']) )
							$umeta['user']['settings']['autoplay'] = [
								'msl' => false,
								'playlist' => false
							];

						$umeta['user']['settings']['autoplay'] = $updated['settings']['autoplay'] = array_merge($umeta['user']['settings']['autoplay'],$udata_autoplay);
						$updated['settings']['autoplay'] = $umeta['user']['settings']['autoplay'];
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
