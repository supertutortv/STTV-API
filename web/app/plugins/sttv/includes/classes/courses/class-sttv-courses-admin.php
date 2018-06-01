<?php

namespace STTV\Courses;

defined( 'ABSPATH' ) || exit;

class Admin {
    public function __construct() {
		add_action( 'init', [ $this, 'sttv_course_endpoints' ], 10, 0 );
        add_filter( 'query_vars', [ $this, 'sttv_course_query_vars' ], 10, 1 );
        add_action( 'save_post_courses' , [ $this, 'save_course_meta' ], 10, 2 );
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
    
    public function save_course_meta($post_id, $post) {
		// Stop WP from clearing custom fields on autosave
		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
			return;

		// Prevent quick edit from clearing custom fields
		if (defined('DOING_AJAX') && DOING_AJAX)
			return;

		//save course intro album
		if ($_POST['course_introvid_album']) :
			update_post_meta($post_id, 'course_introvid_album', sanitize_text_field($_POST['course_introvid_album']));
		endif;
			
		//save course product page
		if ($_POST['product_page_dropdown']) :
			update_post_meta($post_id, 'course_product_page', sanitize_text_field($_POST['product_page_dropdown']));
		endif;
		
		if ($_POST['courses']) :
			update_post_meta($post_id, 'course_raw_post_data', $_POST['courses']);
    
			$test = strtolower($_POST['courses']['test_abbrev']?:'act');
			$caps = [ //default caps for all courses
				'course_post_feedback',
				'course_post_reviews'
			];
		
			$data = [
				'id'=>$post_id,
				'name'=>$post->post_title,
				'slug'=>$post->post_name,
				'link'=>get_post_permalink($post_id),
				'sales_page'=> get_permalink(get_post_meta($post_id, 'course_product_page',true)),
				'cap'=>"course_{$test}_full",
				'created'=>strtotime($post->post_date),
				'modified'=>strtotime($post->post_modified),
				'intro'=>$_POST['courses']['intro_vid']?:0,
				'test'=>strtoupper($test),
				'dashboard'=>$_POST['courses']['has_dash']?:'null',
				//'description'=>$post->post_content,
				'tl_content'=>$_POST['courses']['tl_content'],
				'sections'=>[],
				'practice'=>[]
			];
			$caps[]=$data['cap'];
			update_post_meta($post_id,'course_primary_cap',$data['cap']);
		
			foreach($_POST['courses']['sections'] as $sec) :
				$i = 0;
				$sec['title'] = strtolower($sec['title']);
		
				$cap = "course_{$test}_{$sec['title']}";
				$caps[]=$cap;
		
				$albs = [];
		
				$color = '';
		
				foreach ($sec['videos'] as $sub) {
					$albs[sanitize_title_with_dashes($sub['title'])] = [];
					
					
						$calb = $this->get_cached_album($sub['id']);
						if (empty($color)) {
							$color = $calb['embedColor'];
						}
						$albs[sanitize_title_with_dashes($sub['title'])] = [
							'id' => $sub['id'],
							'title'=>$sub['title'],
							'videos' => $calb[$sub['id']]
						];
				}
				
				$root_path = STTV_RESOURCE_DIR.strtolower($data['test']).'/'.$sec['title'].'/';
				$resources = [];
				$files = scandir($root_path);
				foreach ($files as $file) {
					if (is_file($root_path.$file)){
						$resources[$file] = md5_file($root_path.$file);
					}
				}
		
				$data['sections'][$sec['title']] = [
					'name'=>ucfirst($sec['title']),
					'description'=>$sec['desc'],
					'intro'=>$sec['intro_vid'],
					'cap'=>$cap,
					'color'=>'#'.$color,
					'resources'=>$resources,
					'videos'=>new stdClass(),
					'subsec'=>$albs
				];
				$i++;
			endforeach;
			
			$rp = STTV_RESOURCE_DIR.strtolower($data['test']).'/practice/';
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
		
			endforeach;
		
			$data['size'] = (mb_strlen(json_encode($data), '8bit')/1000).'KB';
		
			$data['allcaps'] = $caps;
			
			update_post_meta($post_id, 'sttv_course_data', $data);
		
			$admin = get_role('administrator');
			foreach ($caps as $c){
				$admin->add_cap($c);
			}
			
		endif;
	}
}