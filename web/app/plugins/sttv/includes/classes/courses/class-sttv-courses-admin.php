<?php

namespace STTV\Courses;

defined( 'ABSPATH' ) || exit;

class Admin {
    public function __construct() {
		add_action( 'save_post_courses', [ $this, 'sttv_build_course' ], 10, 2 );
		add_action( 'user_register', [ $this, 'admin_course_meta' ] );
		add_action('edit_user_profile_update', [ $this, 'correct_user_perms' ]);
	}

	public function correct_user_perms( $id ) {
		$ids = [428,445,1093,1108,1121,1127,1147,1166];
		if ( in_array($id,$ids)) {
			$user = get_userdata($id);
			$user->remove_cap('course_trialing');
			$umeta = get_user_meta( $user_id, 'sttv_user_data', true);
			$umeta['user']['trialing'] = false;
			if ( $id == 1147 ) $umeta['courses']['the-best-sat-prep-course-ever'] = [];
			update_user_meta( $user_id, 'sttv_user_data', $umeta);
		}
	}
	
	public function admin_course_meta( $user_id ) {
		if ( current_user_can('manage_options') ) 
			return update_user_meta( $user_id, 'sttv_user_data', [
				'user' => [
					'history' => [],
					'downloads' => [],
					'type' => 'admin',
					'trialing' => false,
					'settings' => [
						'autoplay' => false,
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

		$test = strtolower( $course['course_meta']['course_abbrev'] );
		$cache_dir = STTV_CACHE_DIR . $test .'/'. $course['course_meta']['course_abbrev'].':';
		$intros = json_decode( file_get_contents( $cache_dir . 'Intro-Videos.cache' ), true );

		$caps = [
			'course_platform_access' => true,
			"course_{$test}_access" => true,
			"course_{$test}_trialing" => true,
			"course_{$test}_feedback" => true,
			"course_{$test}_reviews" => true
		];

		$intr_thumb = explode('|',$course['course_meta']['intro_vid']);
		
		$data = [
			'id' => $post_id,
			'name' => $post->post_title,
			'slug' => $post->post_name,
			'created' => strtotime( $post->post_date ),
			'modified' => strtotime( $post->post_modified ),
			'test' => strtoupper( $test ),
			'type' => 'collection',
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
				$root_path = STTV_RESOURCE_DIR . $test .'/'. $aslug .'/';

				foreach ( $sec['uploads'] as $file ) {
					$chunk = stristr( $file['file']['url'], '/uploads');
					if ( ! is_dir( $root_path ) ) {
						mkdir( $root_path, 0755, true );
					}
					$fcopy = copy( WP_CONTENT_DIR . $chunk, $root_path . $file['file']['filename'] );
					if ( $fcopy ){
						$resources[] = [
							'name' => $file['file']['title'],
							'file' => $test .'/'. $aslug .'/' . $file['file']['filename'],
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
				$calb = json_decode( file_get_contents( $cache_dir.$newtitle.':'.$sub['subsection_name'].'.cache' ), true );

				if ( empty( $color ) ) $color = $calb['embedColor'];

				$subsec[sanitize_title_with_dashes( $sub['subsection_name'] )] = [
					'name' => $sub['subsection_name'],
					'type' => 'videos',
					'in_trial' => (bool) $sub['in_trial'],
					'videos' => $calb['videos']
				];
			}

			$data['collections'][$aslug] = [
				'name' => $sec['section_info']['section_name'],
				'abbrev' => $sec['section_info']['section_code'],
				'type' => 'playlist',
				'description' => $sec['section_info']['description'],
				'intro' => $intros['videos'][$test.'-'.strtolower($sec['section_info']['section_code'])]['id'],
				'color' => '#'.$color,
				'collection' => $subsec,
				'downloads' => $resources
			];

			$caps["course_{$test}_{$aslug}"] = true;
		}
			
		// PRACTICE
		$presc = $psubsec = [];

		if ( $course['practice']['uploads'] ) {
			$root_path = STTV_RESOURCE_DIR . $test .'/practice/';
			if ( ! is_dir( $root_path ) ) {
				mkdir( $root_path, 0755, true );
			}

			foreach ( $course['practice']['uploads'] as $file ) {
				$chunk = stristr( $file['file']['url'], '/uploads');
				$fcopy = copy( WP_CONTENT_DIR . $chunk, $root_path . $file['file']['filename'] );
				if ( $fcopy ){
					$presc[] = [
						'name' => $file['file']['title'],
						'file' => $test .'/'. $aslug .'/' . $file['file']['filename'],
						'size' => round($file['file']['filesize'] / 1024) . ' KB',
						'thumb' => str_replace( '.pdf', '-pdf', $file['file']['url'] ) . '.jpg',
						'hash' => md5_file( $root_path . $file['file']['filename'] ),
						'updated' => strtotime( $file['file']['modified'] ),
						'in_trial' => (bool) $file['in_trial']
					];
				}
			}
		}

		foreach ( $course['practice']['book'] as $book ) {
	
			$title = sanitize_title_with_dashes( $book['book_name'] );

			if ( strpos( $book['book_name'], 'Free' ) !== false ) {
				$data['capabilities'][] = "course_{$test}_{$title}";
			}
			$caps["course_{$test}_{$title}"] = true;
	
			// Main Practice Object
			$psubsec[$title] = [
				'name' => $book['book_name'],
				'in_trial' => (bool) $book['in_trial'],
				'type' => 'collection',
				'tests' => (function() use ( $cache_dir, $book ){
					$tests = glob( $cache_dir . 'Practice:' . str_replace( ' ', '-', $book['book_name'] ) . "*.cache" );
					$cache = [];
					foreach ( $tests as $test ) {
						$els = explode( ':', $test );
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

						$cache[sanitize_title_with_dashes( $els[3] )] = [
							'name' => str_replace('-',' ',$els[3]),
							'type' => 'playlist',
							'color' => '#0aa',//#2d9e6b
							'collection' => $tsections
						];
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

		foreach( $caps as $cap => $grant ) {
			$role->add_cap($cap,$grant);
		}
	}
}
