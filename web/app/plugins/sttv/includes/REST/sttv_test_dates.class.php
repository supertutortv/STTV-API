<?php
if ( ! defined( 'ABSPATH' ) ) exit;
/**
 * SupertutorTV test dates API.
 *
 * REST endpoints for adding, displaying, and updating product reviews.
 *
 * @class 		STTV_Test_Dates
 * @version		1.4
 * @package		STTV
 * @category	Class
 * @author		Supertutor Media, inc.
 */

class STTV_Test_Dates extends WP_REST_Controller {
	
	private $allowed_tests = [
		'act',
		'sat',
		'satsubject',
		'satii',
		'psat',
		'gre',
		'isee',
	];

	private $tests_table = STTV_PREFIX.'_tests_data';
	
	public function __construct() {
		global $wpdb;
		if($wpdb->get_var("SHOW TABLES LIKE '$this->tests_table'") != $this->tests_table) {
			$this->create_tests_table($wpdb);
		}

		add_action( 'rest_api_init', [ $this, 'sttv_test_dates_api' ] );
		
		register_shutdown_function( [ $this, '__destruct' ] );
	}
	
	public function __destruct() {
        return true;
    }
	
	public function sttv_test_dates_api() {
		register_rest_route( STTV_REST_NAMESPACE, '/test_dates', [
				[
					'methods' => WP_REST_Server::ALLMETHODS,
					'callback' => [ $this, 'init' ],
					'permission_callback' => [ $this, 'check_auth' ],
					'args' => [
						'test' => [
							'validate_callback' => [ $this, 'is_allowed_test' ],
							'default' => 'all'
						],
						'range' => [
							'type' => 'string',
							'sanitize_callback' => [ $this, 'date_range_sanitizer']
						],
						'order_by' => [
							'type' => 'string',
							'enum' => [
								'test',
								'test_date',
								'reg_date',
								'change_date',
								'late_reg'
							]
						],
						'sort' => [
							'type' => 'string',
							'enum' => [
								'ASC',
								'asc',
								'DESC',
								'desc'
							]
						]
					]
				]
			]
		);
	}

	public function init( WP_REST_Request $request ) {
		$params = $request->get_params();
		switch ( $request->get_method() ) {
			case 'GET' :
				$test = $this->get_test_dates( $params );
				break;
			case 'POST' :
				unset($params['auth']);
				$test = $params;
				break;
			case 'PUT' :
				$test = 'PATCH method';
				break;
			case 'DELETE' :
				$test = [
					$request->get_param('test')
				];
				break;
			default:
				return $this->method_not_supported( $request->get_method() );
		}
		return $test;
	}
	
	private function get_test_dates( $vals ) {
		global $wpdb;
		$query = "SELECT * FROM $this->tests_table";
		
		if ( 'all' !== $vals['test']) {
			if (!strpos($query,'WHERE')){
				$query .= ' WHERE';
			}
			$query .= ' test=\''.$vals['test'].'\'';
		}

		if ( isset($vals['range']) && isset($vals['range'][1]) ){
			$query .= ' AND (test_date BETWEEN '.$vals['range'][0].' AND '.$vals['range'][1].')';
		} elseif ( isset($vals['range']) && count($vals['range']) === 1) {
			$query .= ' AND test_date='.$vals['range'][0];
		}

		$order = (isset($vals['order_by'])) ? $vals['order_by'] : 'test';

		$sort = (isset($vals['sort'])) ? strtoupper($vals['sort']) : 'ASC';

		$query .= ' ORDER BY '.$order.' '.$sort;

		return $wpdb->get_results( $query );
		return $query;
	}

	private function update_test_dates( WP_REST_Request $request ) {
		return $request->get_param('auth');
	}

	private function delete_test_dates( WP_REST_Request $request ) {

	}

	private function method_not_supported( $method ) {
		$data = [
			'code'    => 'method_not_allowed',
			'message' => $method.' method not supported',
			'data'    => [ 
				'status' => 405
			]
		];
		return new WP_REST_Response( $data, 405 );
	}

	public function date_range_sanitizer( $val, $request ){
		$dates = array_map( 'strtotime', explode( '|', $val ) );
		return $request->set_param( 'range', $dates );
	}

	public function check_auth( WP_REST_Request $request ) {
		if ( 'GET' === $request->get_method() ) {
			return true;
		}
		return true;
		return wp_verify_nonce( $request->get_param('auth'), STTV_REST_AUTH ) && current_user_can('edit_test_dates_api');
	}

	public function is_allowed_test( $val ) {
		return ( is_null($val) || $val === 'all' ) ?: in_array($val, $this->allowed_tests);
	}

	private function create_tests_table($wpdb){
		$charset_collate = $wpdb->get_charset_collate();
		
			$sql = "CREATE TABLE $this->tests_table (
				 id int(10) NOT NULL AUTO_INCREMENT,
				 test tinytext,
				 test_date int(10) UNSIGNED,
				 reg_date int(10) UNSIGNED,
				 change_date int(10) UNSIGNED,
				 late_reg text,
				 reg_link varchar(255),
				 description text,
				 UNIQUE KEY id (id)
			) $charset_collate;";
			require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
			dbDelta( $sql );
	}
}
new STTV_Test_Dates;

/**
 * SupertutorTV test dates API admin area.
 *
 * Defines the Wordpress admin area and front-end editor for the STTV Test Dates API.
 *
 * @class 		STTV_Test_Dates_Admin
 * @version		1.1.0
 * @package		STTV
 * @category	Class
 * @author		Supertutor Media, inc.
 */

 class STTV_Test_Dates_Admin {

	private $test_dates_cap = 'edit_test_dates_api';

	public function __construct() {
		add_action( 'admin_menu' , array($this,'test_dates_menu_page') );
		add_action( 'admin_menu' , array($this,'define_test_dates_capabilities') );
	}

	public function define_test_dates_capabilities() {
		// add new role specific to authorized API authors
		add_role(
			'test_dates_editor',
			'Test Dates Editor',
			[
				'read'         => true,
				$this->test_dates_cap   => true
			]
		);
		
		// add to admin role
		$admin = get_role('administrator');
		if (!in_array($this->test_dates_cap,$admin->capabilities)) {
			$admin->add_cap($this->test_dates_cap, true);
		}
	}

	public function test_dates_menu_page() {
		add_submenu_page(
			'edit.php?post_type=courses',
			'Test Dates',
			'Test Dates',
			'manage_options',
			STTV_PREFIX.'_test_dates', 
			array($this, 'test_dates_admin')
		);
	}

	public function test_dates_admin() {
		print 'I did naht hit herrr, it\'s nahttrueit\'sbullshit I did nahhhhhhht. Oh hai Mark.';
	}

 }
 new STTV_Test_Dates_Admin;