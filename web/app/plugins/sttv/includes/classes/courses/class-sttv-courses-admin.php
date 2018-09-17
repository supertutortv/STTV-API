<?php

namespace STTV\Courses;

defined( 'ABSPATH' ) || exit;

class Admin {
    public function __construct() {
		add_action( 'save_post_courses', [ $this, 'sttv_build_course' ], 10, 2 );
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
		
		$data = [
			'id' => $post_id,
			'name' => $post->post_title,
			'slug' => $post->post_name,
			'created' => strtotime( $post->post_date ),
			'modified' => strtotime( $post->post_modified ),
			'intro' => $intros['videos'][$test.'-course-intro']['id'],
			'test' => strtoupper( $test ),
			'capabilities' => [
				'course_platform_access',
				"course_{$test}_access",
				"course_{$test}_trialing",
				'course_post_feedback',
				'course_post_reviews'
			],
			'sections' => [],
			'practice' => []
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
						mkdir( $root_path, 0777, true );
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
				$calb = json_decode( file_get_contents( $cache_dir.$sec['section_info']['section_name'].':'.$sub['subsection_name'].'.cache' ), true );

				if ( empty( $color ) ) $color = $calb['embedColor'];

				$subsec[sanitize_title_with_dashes( $sub['subsection_name'] )] = [
					'data' => [
						'name' => str_replace( ':', ' ', $calb['albumName'] ),
						'type' => 'videos',
						'in_trial' => (bool) $sub['in_trial'],
					],
					'collection' => $calb['videos']
				];
			}

			$data['sections'][$aslug] = [
				'data' => [
					'name' => $sec['section_info']['section_name'],
					'abbrev' => $sec['section_info']['section_code'],
					'type' => 'collection',
					'description' => esc_html( $sec['section_info']['description'] ),
					'intro' => $intros['videos'][$test.'-'.strtolower($sec['section_info']['section_code'])]['id'],
					'color' => '#'.$color
				],
				'collection' => $subsec,
				'files' => $resources
			];

			$data['capabilities'][] = "course_{$test}_{$aslug}";
		}
			
		// PRACTICE
		$presc = $psubsec = [];

		if ( $course['practice']['uploads'] ) {
			$root_path = STTV_RESOURCE_DIR . $test .'/practice/';
			if ( ! is_dir( $root_path ) ) {
				mkdir( $root_path, 0777, true );
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
			$data['capabilities'][] = "course_{$test}_{$title}";
	
			// Main Practice Object
			$psubsec[$title] = [
				'data' => [
					'name' => $book['book_name'],
					'in_trial' => (bool) $book['in_trial'],
					'type' => 'collection'
				],
				'collection' => (function() use ( $cache_dir, $book ){
					$tests = glob( $cache_dir . 'Practice:' . str_replace( ' ', '-', $book['book_name'] ) . "*.cache" );
					$cache = [];
					foreach ( $tests as $test ) {
						$els = explode( ':', $test );
						if ( strpos( $els[3], '.cache' ) ) {
							array_splice( $els, 3, 0, 'Test 1' );
						}
						$pvideos = json_decode( file_get_contents( $test ), true );
						$tsections[sanitize_title_with_dashes( str_replace( '.cache', '', $els[4] ) )] = [
							'data' => [
								'name' => str_replace( '.cache', '', $els[4] ),
								'type' => 'videos',
								'color' => '#'.$pvideos['embedColor']
							],
							'collection' => $pvideos['videos']
						];
						$cache[sanitize_title_with_dashes( $els[3] )] = [
							'data' => [
								'name' => $els[3],
								'type' => 'collection'
							],
							'collection' => $tsections
						];
					}
					return $cache;
				})()
			];
		}

		$data['practice'] = [
			'data' => [
				'name' => 'Practice Tests',
				'description' => esc_html( $course['practice']['description'] ?? ''),
				'type' => 'collection'
			],
			'collection' => $psubsec,
			'files' => $presc
		];
		
		$data['size'] = ( mb_strlen( json_encode( $data ), '8bit' )/1000 ) . 'KB';
		
		update_post_meta( $post_id, 'sttv_course_data', $data );
	}
}