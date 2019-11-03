<?php

namespace STTV\Courses;

defined( 'ABSPATH' ) || exit;

class Admin {
    public function __construct() {
		add_action( 'save_post_courses', [ $this, 'sttv_build_course' ], 999, 2 );
		add_action( 'user_register', [ $this, 'admin_course_meta' ] );
		add_action( 'edit_user_profile_update', [ $this, 'correct_user_perms' ]);
	}

	public function correct_user_perms( $user ) {
		$email = $user->user_email;

		try {
			$cus = \Stripe\Customer::all(['email'=>$email]);
			$obj = $cus->data;
			if (!empty($obj)) {
				$obj = $obj[0];
				$obj->metadata = ['wp_id'=>$user->ID];
				$obj->save();
			}

		} catch (\Exception $e) {
			print_r($e);
		}
	}
	
	public function admin_course_meta( $user_id ) {
		if ( current_user_can('manage_options') ) 
			return update_user_meta( $user_id, 'sttv_user_data', [
				'user' => [
					'subscription' => '',
					'history' => [],
					'downloads' => [],
					'type' => 'admin',
					'trialing' => false,
					'settings' => [
						'autoplay' => [
							'msl' => false,
							'playlist' => false
					],
						'dark_mode' => false
					],
					'userdata' => [
						'login_timestamps' => []
					]
				],
				'courses' => []
			]);
	}
    
    public function sttv_build_course( $post_id, $post ) {

		// Stop WP from clearing custom fields on autosave
		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;

		// Prevent quick edit from clearing custom fields
		if (defined('DOING_AJAX') && DOING_AJAX) return;
		
		$course = get_fields( $post_id );
		if (!$course['course_meta']['course_abbrev']) return false;

		$exam = strtolower( $course['course_meta']['course_abbrev'] );
		$cache_dir = STTV_CACHE_DIR . $exam .'/';

		$caps = [
			'course_platform_access' => true,
			"course_{$exam}_access" => true,
			"course_{$exam}_feedback" => true,
			"course_{$exam}_reviews" => true
		];

		$test5Patch = false;

		$intr_thumb = explode('|',$course['course_meta']['intro_vid']);
		
		$data = [
			'resDirs' => [],
			'id' => $post_id,
			'name' => $post->post_title,
			'slug' => $post->post_name,
			'created' => strtotime( $post->post_date ),
			'modified' => strtotime( $post->post_modified ),
			'test' => strtoupper( $exam ),
			'type' => 'collection',
			'resourceLink' => $course['course_meta']['course_resource_link'] ?? '#',
			'thumb' => $course['course_meta']['cover_image'] ?? '',
			'thumbUrls' => [
				'plain' => 'https://i.vimeocdn.com/video/||ID||_295x166.jpg?r=pad',
				'withPlayButton' => 'https://i.vimeocdn.com/filter/overlay?src0=https%3A%2F%2Fi.vimeocdn.com%2Fvideo%2F||ID||_295x166.jpg&src1=http%3A%2F%2Ff.vimeocdn.com%2Fp%2Fimages%2Fcrawler_play.png'
			],
			'history' => [],
			'downloads' => [],
			'playlist' => [
				[
					'id' => 0,
					'vidid' => $intr_thumb[0],
					'timestamp' => time(),
					'name' => strtoupper( $test ).' Intro',
					'thumb' => $intr_thumb[1]
				]
			],
			'collections' => []
		];
		
		// SECTIONS
		foreach( $course['sections'] as $ind => $sec) {
			$aslug = sanitize_title_with_dashes( $sec['section_info']['section_name'] );
			$resources = $videos = $subsec = [];
			$color = '';

			if ( $sec['uploads'] ) {
				$root_path = STTV_RESOURCE_DIR . $exam .'/'. $aslug .'/';

				foreach ( $sec['uploads'] as $file ) {
					$chunk = stristr( $file['file']['url'], '/uploads');
					if ( ! is_dir( $root_path ) ) {
						$data['resDirs'][$root_path] = mkdir( $root_path, 0775, true );
					}
					$fcopy = @copy( WP_CONTENT_DIR . $chunk, $root_path . $file['file']['filename'] );

					if ( $fcopy ){
						$resources[] = [
							'name' => $file['file']['title'],
							'file' => $exam .'/'. $aslug .'/' . $file['file']['filename'],
							'size' => round($file['file']['filesize'] / 1024) . ' KB',
							'thumb' => str_replace( '.pdf', '-pdf', $file['file']['url'] ) . '.jpg',
							'hash' => md5_file( $root_path . $file['file']['filename'] ),
							'updated' => strtotime( $file['file']['modified'] ),
							'in_trial' => (bool) $file['in_trial']
						];
					}
				}
			}

			foreach ( $sec['subsections'] as $sub ) {
				$newtitle = str_replace(' ','-',$sec['section_info']['section_name']);
				$calb = json_decode( file_get_contents( glob($cache_dir.'*'.$newtitle.'*'.$sub['subsection_name'].'*.cache')[0]), true );
				$subsecname = sanitize_title_with_dashes( $sub['subsection_name'] );

				if ( empty( $color ) ) $color = $calb['embedColor'];

				$subsec[$subsecname] = [
					'name' => $sub['subsection_name'],
					'type' => 'videos',
					'permissions' => "course_{$exam}_{$aslug}_{$subsecname}",
					'in_trial' => (bool) $sub['in_trial'],
					'videos' => $calb['videos']
				];

				$caps["course_{$exam}_{$aslug}_{$subsecname}"] = true;
			}

			$data['collections'][$aslug] = [
				'name' => $sec['section_info']['section_name'],
				'abbrev' => $sec['section_info']['section_code'],
				'type' => 'playlist',
				'permissions' => "course_{$exam}_{$aslug}",
				'description' => $sec['section_info']['description'],
				'color' => '#'.$color,
				'collection' => $subsec,
				'downloads' => $resources
			];

			$caps["course_{$exam}_{$aslug}"] = true;
		}
			
		// PRACTICE
		$presc = $psubsec = [];

		if ( $course['practice']['uploads'] ) {
			$proot_path = STTV_RESOURCE_DIR . $exam .'/practice/';
			if ( ! is_dir( $proot_path ) ) {
				mkdir( $proot_path, 0755, true );
			}

			foreach ( $course['practice']['uploads'] as $file ) {
				$pchunk = stristr( $file['file']['url'], '/uploads');
				$fcopy = copy( WP_CONTENT_DIR . $pchunk, $proot_path . $file['file']['filename'] );
				if ( $fcopy ){
					$presc[] = [
						'name' => $file['file']['title'],
						'file' => $exam . '/practice/' . $file['file']['filename'],
						'size' => round($file['file']['filesize'] / 1024) . ' KB',
						'thumb' => str_replace( '.pdf', '-pdf', $file['file']['url'] ) . '.jpg',
						'hash' => md5_file( $proot_path . $file['file']['filename'] ),
						'updated' => strtotime( $file['file']['modified'] ),
						'in_trial' => (bool) $file['in_trial']
					];
				}
			}
		}

		foreach ( $course['practice']['book'] as $book ) {
	
			$title = sanitize_title_with_dashes( $book['book_name'] );

			$caps["course_{$exam}_{$title}"] = true;
	
			// Main Practice Object
			$psubsec[$title] = [
				'name' => $book['book_name'],
				'permissions' => "course_{$exam}_{$title}",
				'in_trial' => (bool) $book['in_trial'],
				'type' => 'collection',
				'tests' => (function() use ( &$caps, &$test5Patch, $exam, $title, $cache_dir, $book ){
					$tests = glob( $cache_dir . '*Practice*' . str_replace( ' ', '-', $book['book_name'] ) . "*.cache" );
					$cache = [];

					foreach ( $tests as $test ) {

						$els = preg_split("/:|~/",$test);

						if ( strpos( $els[3], '.cache' ) ) {
							array_splice( $els, 3, 0, 'Test 1' );
						}

						$aTitle = str_replace( '.cache', '', $els[4] );

						$pvideos = json_decode( file_get_contents( $test ), true );
						$tsections[sanitize_title_with_dashes( $aTitle )] = [
							'name' => str_replace('-',' ',$aTitle),
							'parent' => $book['book_name'],
							'videos' => $pvideos['videos']
						];

						$els3 = sanitize_title_with_dashes( $els[3] );

						$cache[ $els3 ] = [
							'name' => str_replace('-',' ',$els[3]),
							'permissions' => "course_{$exam}_{$title}_{$els3}",
							'type' => 'playlist',
							'color' => '#0aa',//#2d9e6b
							'collection' => $tsections
						];

						if (!($title === 'the-official-act-prep-guide' && $els3 === 'test-5')) {
							$caps["course_{$exam}_{$title}_{$els3}"] = true;
						} else {
							$test5Patch = true;
						}
					}
					return $cache;
				})()
			];
		}

		$data['collections']['practice'] = [
			'name' => 'Practice Tests',
			'description' => esc_html( $course['practice']['description'] ?? ''),
			'type' => 'collection',
			'collection' => $psubsec,
			'downloads' => $presc
		];
		
		$data['size'] = ( mb_strlen( json_encode( $data ), '8bit' )/1000 ) . 'KB';
		
		update_post_meta( $post_id, 'sttv_course_data', $data );

		$role = add_role(str_replace(' ','_',strtolower($post->post_title)),$post->post_title) ?? get_role(str_replace(' ','_',strtolower($post->post_title)));

		$role_trial = add_role(str_replace(' ','_',strtolower($post->post_title.' Trial')),$post->post_title.' Trial') ?? get_role(str_replace(' ','_',strtolower($post->post_title.' Trial')));

		$role->add_cap("course_{$exam}_full_access",true);
		$role_trial->add_cap("course_{$exam}_trial_access",true);

		foreach( $caps as $cap => $grant ) {
			$role->add_cap($cap,$grant);
			$role_trial->add_cap($cap,$grant);
		}

		if ($test5Patch) {
			$test5role = add_role('act_test_5_patch', 'ACT Test 5 Patch');
			if ( $test5role !== NULL ) {
				$test5role->add_cap( 'course_act_the-official-act-prep-guide_test-5' );
			}
		}

	}
}
