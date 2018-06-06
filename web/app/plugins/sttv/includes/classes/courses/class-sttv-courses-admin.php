<?php

namespace STTV\Courses;

defined( 'ABSPATH' ) || exit;

class Admin {
    public function __construct() {
		add_action( 'init', [ $this, 'sttv_course_endpoints' ], 10, 0 );
        add_filter( 'query_vars', [ $this, 'sttv_course_query_vars' ], 10, 1 );
        add_action( 'save_post', [ $this, 'sttv_build_course' ], 999, 2 );
    }


    public function sttv_course_endpoints() {
		
		add_rewrite_rule('^courses/(.*)/(.*)/(.*)/(.*)/(.*)?$','index.php?post_type=courses&name=$matches[1]&section=$matches[2]&subsection=$matches[3]&video=$matches[4]&q=$matches[5]','top' );
		add_rewrite_rule('^courses/(.*)/(.*)/(.*)/(.*)?$','index.php?post_type=courses&name=$matches[1]&section=$matches[2]&subsection=$matches[3]&video=$matches[4]','top' );
		add_rewrite_rule('^courses/(.*)/(.*)/(.*)?$','index.php?post_type=courses&name=$matches[1]&section=$matches[2]&subsection=$matches[3]','top' );
		add_rewrite_rule('^courses/(.*)/(.*)?$','index.php?post_type=courses&name=$matches[1]&section=$matches[2]','top' );
		add_rewrite_rule('^courses/(.*)?$','index.php?post_type=courses&name=$matches[1]','top' );
		
		add_rewrite_tag( '%section%', '([a-zA-Z0-9]+[_-])*', 'section=' );
		add_rewrite_tag( '%subsection%', '([a-zA-Z0-9]+[_-])*', 'subsection=' );
		add_rewrite_tag( '%video%', '([a-zA-Z0-9]+[_-])*', 'video=' );
		add_rewrite_tag( '%question%', '([a-zA-Z0-9]+[_-])*', 'q=' );
		
	}
	
	public function sttv_course_query_vars( $vars ) {
		$vars[] = 'section';
		$vars[] = 'subsection';
		$vars[] = 'video';
		$vars[] = 'q';
		return $vars;
    }
    
    public function sttv_build_course( $post_id, $post ) {

		// Stop WP from clearing custom fields on autosave
		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
			return;

		// Prevent quick edit from clearing custom fields
		if (defined('DOING_AJAX') && DOING_AJAX)
			return;
		
		if ( $post->post_type == 'courses' ) {
			$course = get_fields( $post_id );
			$test = strtolower( $course['course_meta']['course_abbrev'] );
			$cache_dir = STTV_CACHE_DIR . $test .'/'. $course['course_meta']['course_abbrev'].':';
			$intros = json_decode( file_get_contents( $cache_dir . 'Intro Videos.cache' ), true );
			
			$data = [
				'id' => $post_id,
				'name' => $post->post_title,
				'slug' => $post->post_name,
				'link' => get_post_permalink( $post_id ),
				'created' => strtotime( $post->post_date ),
				'modified' => strtotime( $post->post_modified ),
				'intro' => (int) $intros['videos'][$test.'-course-intro']['ID'],
				'test' => strtoupper( $test ),
				'pricing' => [
					'price' => (int) $course['course_pricing']['price'],
					'taxable_amt' => (int) $course['course_pricing']['taxable_amt'],
					'trial_period' => (int) $course['course_pricing']['trial_period'],
					'type' => 'subscription',
					'renewals' => $course['course_pricing']['renewals']
				],
				'capabilities' => [
					'trial' => [
						'course_access_cap',
						"course_{$test}_trial"
					],
					'full' => [
						'course_access_cap',
						"course_{$test}_full",
						'course_post_feedback',
						'course_post_reviews'
					]
				],
				'sections'=>[],
				'practice'=>[]
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
								'title' => $file['file']['title'],
								'file' => '/' . $test .'/'. $aslug .'/' . $file['file']['filename'],
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

					if ( empty( $color ) ) {
						$color = $calb['embedColor'];
					}

					$subsec[sanitize_title_with_dashes( $sub['subsection_name'] )] = [
						'id' => $calb['albumID'],
						'type' => 'collection',
						'title' => str_replace( ':', ' ', $calb['albumName'] ),
						'in_trial' => (bool) $sub['in_trial'],
						'videos' => $calb['videos']
					];
				}

				$data['sections'][$aslug] = [
					'name' => $sec['section_info']['section_name'],
					'abbrev' => $sec['section_info']['section_code'],
					'type' => 'collection',
					'description' => esc_html( $sec['section_info']['description'] ),
					'intro' => (int) $intros['videos'][$test.'-'.strtolower($sec['section_info']['section_code'])]['ID'],
					'color' => '#'.$color,
					'resources' => [
						'type' => 'file',
						'files' => $resources
					],
					'subsec' => $subsec
				];

				$data['capabilities']['full'][] = "course_{$test}_{$aslug}";
			}
				
			// PRACTICE
			$data['practice'] = [
				'description' => esc_html( $course['practice']['description'] ?? ''),
				'type' => 'collection',
				'resources' => [
					'type' => 'file'
				],
				'books' => []
			];

			if ( $course['practice']['uploads'] ) {
				$root_path = STTV_RESOURCE_DIR . $test .'/practice/';
				if ( ! is_dir( $root_path ) ) {
					mkdir( $root_path, 0777, true );
				}

				foreach ( $course['practice']['uploads'] as $file ) {
					$chunk = stristr( $file['file']['url'], '/uploads');
					$fcopy = copy( WP_CONTENT_DIR . $chunk, $root_path . $file['file']['filename'] );
					if ( $fcopy ){
						$data['practice']['resources']['files'] = [
							'title' => $file['file']['title'],
							'file' => '/' . $test .'/'. $aslug .'/' . $file['file']['filename'],
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
					$data['capabilities']['trial'][] = "course_{$test}_{$title}";
				}
				$data['capabilities']['full'][] = "course_{$test}_{$title}";
		
				// Main Practice Object
				$data['practice']['books'][$title] = [
					'name' => $book['book_name'],
					'in_trial' => (bool) $book['in_trial'],
					'type' => 'collection',
					'tests' => (function() use ( $cache_dir, $book ){
						$tests = glob( $cache_dir . 'Practice:' . $book['book_name'] . "*.cache" );
						$cache = [];
						foreach ( $tests as $test ) {
							$els = explode( ':', $test );
							if ( strpos( $els[3], '.cache' ) ) {
								array_splice( $els, 3, 0, 'Test 1' );
							}
							$pvideos = json_decode( file_get_contents( $test ), true );
							$tsections[sanitize_title_with_dashes( str_replace( '.cache', '', $els[4] ) )] = [
								'name' => str_replace( '.cache', '', $els[4] ),
								'type' => 'collection',
								'color' => '#'.$pvideos['embedColor'],
								'videos' => $pvideos['videos']
							];
							$cache[sanitize_title_with_dashes( $els[3] )] = [
								'name' => $els[3],
								'type' => 'collection',
								'sections' => $tsections
							];
						}
						return $cache;
					})()
				];
			}
			
			$data['size'] = ( mb_strlen( json_encode( $data ), '8bit' )/1000 ) . 'KB';
			
			update_post_meta( $post_id, 'sttv_course_data', $data );
		}
	}
}