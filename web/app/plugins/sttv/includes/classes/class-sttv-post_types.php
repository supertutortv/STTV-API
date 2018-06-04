<?php

namespace STTV;

defined( 'ABSPATH' ) || exit;

class Post_Types {

    public static function init() {
        add_action( 'init', [ __CLASS__, 'register_post_types' ], 5 );
        add_action( 'add_meta_boxes', [ __CLASS__, 'add_meta_boxes' ] );
    }

    public static function register_post_types() {

        $supports = [ 'title', 'editor', 'excerpt', 'thumbnail', 'custom-fields', 'comments', 'author' ];
		
		register_post_type(
            'courses',
            [
                'labels'				=>	[
                    'name'	=>	'Courses'
                ],
                'description'			=>	'SupertutorTV courses',
                'menu_position'			=>	56,
                'menu_icon'				=> 'dashicons-welcome-learn-more',
                'public'				=>	true,
                'hierarchical'			=>	true,
                'exclude_from_search'	=>	true,
                'show_in_nav_menus'		=>	false,
                'show_in_rest'			=>	true,
                'rewrite'				=>	[
                    'with_front'	=>	true,
                    'pages'			=>	false
                ],
                'delete_with_user'		=>	false,
                'can_export'			=>	true,
                'supports'				=>	$supports
            ]
        );

		register_post_type(
			'feedback',
			[
				'labels'				=>	[
					'name'	=>	'Feedback'
				],
				'description'			=>	'SupertutorTV course feedback',
				'menu_position'			=>	57,
				'menu_icon'				=> 'dashicons-megaphone',
				'public'				=>	false,
				'public_queryable'      =>  false,
				'show_ui'               =>  true,
				'show_in_menu'          =>  true,
				'hierarchical'			=>	false,
				'exclude_from_search'	=>	true,
				'show_in_nav_menus'		=>	false,
				'show_in_rest'			=>	true,
				'delete_with_user'		=>	false,
				'can_export'			=>	true,
				'supports'				=>	$supports
			]
		);
    }

    public static function add_meta_boxes() {
        add_meta_box(
             'course_info', // $id
             'Course Information', // $title
             [ __CLASS__ , 'sttv_display_course_meta' ], // $callback
             'courses', // $post_type
             'normal', // $context
             'low' // $priority
        );
    }
    
    public static function sttv_display_course_meta() {
        global $post, $wp_rewrite;
        $fields = get_fields( $post->ID ); 
        $meta = get_post_meta( $post->ID, 'sttv_course_data', true ); ?>
        <pre><?php print_r( $meta ); ?></pre>
    <?php }

}
Post_Types::init();