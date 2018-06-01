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
    
        add_meta_box(
             'course_product_page', // $id
             'Course Product Page', // $title
             [ __CLASS__ , 'sttv_display_course_product_page' ], // $callback
             'courses', // $post_type
             'side', // $context
             'low' // $priority
        );

        add_meta_box(
            'course_introvid_album', // $id
            'Course Introvideo Album', // $title
            [ __CLASS__ , 'sttv_display_course_introvid_album' ], // $callback
            'courses', // $post_type
            'side', // $context
            'low' // $priority
       );
    }

    public static function sttv_display_course_product_page() { ?>
		<select name="product_page_dropdown">
            <option value=""><?php echo esc_attr( __( 'Select page' ) ); ?></option> 
		<?php global $post;
		    $cpp = get_post_meta( $post->ID, 'course_product_page', true );
		    $pages = get_pages(); 
            foreach ( $pages as $page ) :
                $selected = ($page->ID == $cpp) ? 'selected' : '';
                $option = '<option value="' . $page->ID . '" '.$selected.'>';
                $option .= $page->post_title;
                $option .= '</option>';
                echo $option;
            endforeach;
		?>
		</select>
	<?php 
		wp_nonce_field( 'save_course_product_page_nonce', 'course_product_page_nonce' );										   
	}
	
	public static function sttv_display_course_introvid_album() { 
		global $post;
		?>
		<input type="text" name="course_introvid_album" value="<?php echo get_post_meta( $post->ID, 'course_introvid_album', true ); ?>"/>
    <?php }
    
    public static function sttv_display_course_meta() {
        global $post;
        $fields = get_fields( $post->ID ); ?>
        <pre><?php var_dump( $fields ); ?></pre>
    <?php }

}
Post_Types::init();