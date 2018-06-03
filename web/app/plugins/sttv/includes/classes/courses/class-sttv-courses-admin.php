<?php

namespace STTV\Courses;

defined( 'ABSPATH' ) || exit;

class Admin {
    public function __construct() {
		add_action( 'init', [ $this, 'sttv_course_endpoints' ], 10, 0 );
        add_filter( 'query_vars', [ $this, 'sttv_course_query_vars' ], 10, 1 );
        add_action( 'save_post_courses', [ $this, 'sttv_build_course' ], 10, 2 );
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

		$course = get_fields( $post_id );
		$test = strtolower( $course['course_meta']['course_abbrev'] );
		
		$data = [
			'id' => $post_id,
			'name' => $post->post_title,
			'slug' => $post->post_name,
			'link' => get_post_permalink( $post_id ),
			'created' => strtotime( $post->post_date ),
			'modified' => strtotime( $post->post_modified ),
			'intro' => 0,
			'test' => strtoupper( $test ),
			'pricing' => [
				'price' => $course['course_meta']['course_price']['price'],
				'taxable' => $course['course_meta']['course_price']['taxable'],
				'taxable_amt' => $course['course_meta']['course_price']['taxable_amt']
			],
			'capabilities' => [
				'trial' => [
					"course_{$test}_trial"
				],
				'full' => [
					"course_{$test}_full",
					'course_post_feedback',
					'course_post_reviews'
				]
				],
			'sections'=>[],
			'practice'=>[]
		];
		
		foreach( $course['sections'] as $ind => $sec) {
			$aslug = sanitize_title_with_dashes( $sec['section_info']['section_name'] );
			$resources = $videos = $subsec = [];
			$color = '';

			if ( $sec['uploads'] ) {
				$root_path = STTV_RESOURCE_DIR . $test .'/'. $aslug .'/';

				foreach ( $sec['uploads'] as $file ) {
					$chunk = stristr( $file['file']['url'], '/uploads');
					$fcopy = copy( WP_CONTENT_DIR . $chunk, $root_path . $file['file']['filename'] );
					if ( $fcopy ){
						$resources[sanitize_title_with_dashes( $file['file']['title'] )] = [
							'title' => $file['file']['title'],
							'file' => $root_path . $file['file']['filename'],
							'hash' => md5_file( $root_path . $file['file']['filename'] ),
							'updated' => strtotime( $file['file']['modified'] )
						];
					}
				}
			}

			foreach ( $sec['subsections'] as $sub ) {
				$calb = json_decode( file_get_contents( STTV_CACHE_DIR . $test .'/'. $course['course_meta']['course_abbrev'].'|'.$sec['section_info']['section_name'].'|'.$sub['subsection_name'].'.cache' ), true );

				if ( empty( $color ) ) {
					$color = $calb['embedColor'];
				}

				$subsec[sanitize_title_with_dashes( $sub['subsection_name'] )] = [
					'id' => $calb['albumID'],
					'title' => str_replace( '|', ' ', $calb['albumName'] ),
					'videos' => $calb['videos']
				];
			}

			$data['sections'][$aslug] = [
				'name' => $sec['section_info']['section_name'],
				'abbrev' => $sec['section_info']['section_code'],
				'description' => $sec['section_info']['description'],
				'intro' => '',
				'color' => '#'.$color,
				'resources' => $resources,
				'videos' => $videos,
				'subsec' => $subsec
			];

			$data['capabilities']['full'][] = "course_{$test}_{$aslug}";
		}
			
			/* $rp = STTV_RESOURCE_DIR.strtolower($data['test']).'/practice/';
			$resc = [];
			$f = scandir($rp);
			foreach ($f as $file) {
				if (is_file($rp.$file)){
					$resc[$file] = md5_file($rp.$file);
				}
			}

			$data['practice'] = [
				'description' => $_POST['courses']['practice']['description'],
				'resources' => $resc,
				'tests' => []
			];

			foreach ($_POST['courses']['practice']['tests'] as $prac) :
		
				$title = sanitize_title_with_dashes($prac['title']);
		
				$sections = [];
				foreach ($prac['sections'] as $v) {
					$calb = $this->get_cached_album($v['id']);
					if (empty($color)) {
						$color = $calb['embedColor'];
					}
					
					$sections[sanitize_title_with_dashes($v['title'])] = [
						'id'=>$v['id'],
						'album-name'=>$calb['albumName'],
						'title'=>$v['title'],
						'intro'=>$v['intro_vid'],
						'videos'=>$calb[$v['id']]
					];
				}

				$data['practice']['tests'][$title] = [
					'name'=>$prac['title'],
					'cap'=>"course_{$test}_practice_{$title}",
					'sections'=>$sections
				];

				$caps[]=$data['practice'][$title]['cap'];
		
			endforeach; */
		
		$data['size'] = ( mb_strlen( json_encode( $data ), '8bit' )/1000 ) . 'KB';
		
		update_post_meta( $post_id, 'sttv_course_data', $data );
		
			/* $admin = get_role( 'administrator' );
			foreach ( $data as $c ) {
				$admin->add_cap( $c );
			} */
	}
}