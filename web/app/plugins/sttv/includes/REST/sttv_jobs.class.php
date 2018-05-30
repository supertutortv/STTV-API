<?
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * SupertutorTV job board.
 *
 * All operations, including custom routes, template redirection, and REST endpoints for the SupertutorTV job board.
 *
 * @class 		STTV_Jobs
 * @version		1.3.0
 * @package		STTV
 * @category	Class
 * @author		Supertutor Media, inc.
 */

class STTV_Jobs {
    private $jobs_table = STTV_PREFIX.'_jobs_data';

    private $st_jobs_table = STTV_PREFIX.'_jobs_data';

    private $submissions_table = STTV_PREFIX.'_jobs_submissions';
    
    public function __construct() {
        
    }
    public function init() {
        add_filter( 'query_vars', [$this, 'sttv_jobs_qvar'] );
        add_filter( 'template_include', [$this,'jobs_page_template'], 0 );
        add_action( 'init', [$this, 'sttv_jobs_endpoint'] );
        $this->sttv_jobs_tables();
    }

    public function sttv_jobs_qvar($vars) {
        $vars[] = 'job-post';
        $vars[] = 'job-action';
        return $vars;
    }

    public function sttv_jobs_endpoint() {
        add_rewrite_rule('^jobs/(.*)/(.*)?$','index.php?pagename=jobs&job-post=$matches[1]&job-action=$matches[2]','top' );
        add_rewrite_rule('^jobs/(.*)?$','index.php?pagename=jobs&job-post=$matches[1]','top' );
    }

    public function jobs_page_template($template) {
        global $wp_query;
        $base = STTV_TEMPLATE_DIR;

        if (isset($wp_query->query['job-action'])) {
            if (($wp_query->query['job-action'] === 'edit' || $wp_query->query['job-action'] === 'submissions' ) && !current_user_can('edit_posts')){
                return wp_redirect(site_url().'/'.$wp_query->query['pagename'].'/'.$wp_query->query['job-post']);
            } elseif ($wp_query->query['job-action'] === 'edit') {
                return $base.'jobs/edit-single.php';
            } elseif ($wp_query->query['job-action'] === 'apply') {
                return $base.'jobs/apply.php';
            } elseif ($wp_query->query['job-action'] === 'submissions') {
                return $base.'jobs/submissions.php';
            }
        }

        if (isset($wp_query->query['job-post'])) {
            if ($wp_query->query['job-post'] === 'edit') {
                return $base.'jobs/edit-all.php';
            } else {
                return $base.'jobs/single.php';
            }
        }
    
        return $template;
    }

    public function get_job($val = '') {
        global $wpdb;
        $column = is_numeric($val) ? 'id' : 'name';
        $job = $wpdb->get_results( 
            $wpdb->prepare("SELECT * FROM $this->jobs_table WHERE $column=%s", $val) 
        );

        if (!$job) {
            return null;
        }
        return $this->set_job_object_types($job[0]);
    }

    public function get_jobs() {
        global $wpdb;
        $jobject = [];
        $res = $wpdb->get_results( 
            $wpdb->prepare("SELECT * FROM $this->jobs_table WHERE status=%s", 'active') 
        );
        foreach ($res as $r) {
            $jobject[] = $this->set_job_object_types($r);
        }
        return $jobject;
    }

    public function create_job($job = []){
        if (!isset($job['title'])){
            return new WP_Error( 'bad_request', '`title` is a required field.', [ 'status' => 400 ] );
        }
        $defaults = [
            'location' => 'Los Angeles, CA',
            'is_remote' => false,
            'status' => 'active',
            'category' => 'general',
            'job_type' => 'full-time'
        ];

        global $wpdb;
        $init = $wpdb->insert($this->jobs_table,$defaults);

        if (!$init || is_wp_error($init)) {
            return $init;
        }

        $job['title'] = ucwords($job['title']);
        $job['name'] = sanitize_title_with_dashes($job['title']).'-'.$wpdb->insert_id;
        $job['url'] = '/jobs/'.$job['name'];

        $vals = array_merge($defaults,$job);
        $vals['id'] = $wpdb->insert_id;
        $the_job = $this->update_job($vals);
        if (!$the_job){
            $this->delete_job($wpdb->insert_id);
        }

        return $this->get_job($wpdb->insert_id);
    }

    public function update_job($params = []) {
        global $wpdb;
        $where = ['id'=>$params['id']];
        unset($params['id']);
        return $wpdb->update($this->jobs_table,$params,$where);
    }

    public function delete_job($id = 0) {
        global $wpdb;
        return $wpdb->delete($this->jobs_table,['id'=>$id]);
    }

    public function set_job_object_types($job){
        $job->id = (int) $job->id;
        $job->description = (string) esc_html($job->description);
        $job->is_remote = (bool) $job->is_remote;
        $job->sub_count = (int) $job->sub_count;
        $job->date_posted = strtotime($job->date_posted);
        return (object) $job;
    }

    public function sttv_jobs_tables() {
        global $wpdb;
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		if($wpdb->get_var("SHOW TABLES LIKE '$this->jobs_table'") != $this->jobs_table) {
            $charset_collate = $wpdb->get_charset_collate();
            $sql = "CREATE TABLE $this->jobs_table (
                    id int(10) NOT NULL AUTO_INCREMENT,
                    name tinytext,
                    title tinytext,
                    description longtext,
                    url varchar(255),
                    location tinytext,
                    category tinytext,
                    is_remote boolean NOT NULL DEFAULT 0,
                    status tinytext,
                    job_type tinytext,
                    sub_count smallint DEFAULT 0,
                    date_posted TIMESTAMP,
                    UNIQUE KEY id (id)
            ) AUTO_INCREMENT=20508 $charset_collate;";
            dbDelta( $sql );
        }
        
        if($wpdb->get_var("SHOW TABLES LIKE '$this->submissions_table'") != $this->submissions_table) {
            $charset_collate = $wpdb->get_charset_collate();
            $sql = "CREATE TABLE $this->submissions_table (
                    id int(10) NOT NULL AUTO_INCREMENT,
                    job_id int(10),
                    email varchar(255),
                    user_data longtext,
                    date_applied TIMESTAMP,
                    UNIQUE KEY id (id)
            ) $charset_collate;";
            dbDelta( $sql );
		}
    }

 }
(new STTV_Jobs)->init();

class STTV_Jobs_REST extends WP_REST_Controller {
    private $jobs;

    public function __construct() {
        $this->jobs = new STTV_Jobs();
        add_action( 'rest_api_init', [$this, 'jobs_rest_init'] );
    }

    public function jobs_rest_init() {

        // get ALL jobs
        register_rest_route( STTV_REST_NAMESPACE, '/jobs',
            [
                [
                    'methods' => WP_REST_Server::READABLE,
                    'callback' => [$this,'rest_job_getter'],
                    'permission_callback' => '__return_true',
                    'args' => [
                        'id' => [
                            'validate_callback' => function($param,$request,$key){
                                return is_numeric($param) && intval($param) !== 0;
                            }
                        ]
                    ]
                ],
                [
                    'methods' => WP_REST_Server::EDITABLE,
                    'callback' => [$this,'rest_job_editor'],
                    'permission_callback' => [$this,'jobs_cud_permissions']
                ],
                [
                    'methods' => WP_REST_Server::DELETABLE,
                    'callback' => [$this,'rest_job_deleter'],
                    'permission_callback' => [$this,'jobs_cud_permissions'],
                    'args' => [
                        'id' => [
                            'validate_callback' => function($param,$request,$key){
                                return is_numeric($param) && intval($param) !== 0;
                            }
                        ]
                    ]
                ]
            ]
        );
        
    }

    public function rest_job_getter(WP_REST_Request $request) {
        //return $request->get_param('id');
        if (null !== $request->get_param( 'id' )) {
            $job = $this->jobs->get_job($request->get_param( 'id' ));
            return ($job == null) ? new WP_Error( 'job_not_found', 'Invalid job request', [ 'status' => 404 ] ) : $job;
        } else {
            return $this->jobs->get_jobs();
        }
    }

    public function rest_job_editor(WP_REST_Request $request) {
        switch ($request->get_method()) {
            case "GET":
                return;
            case "POST":
                return $this->jobs->create_job($request->get_params());
                //return $request->get_params();
            case "PUT":
            case "PATCH":
                return $this->jobs->update_job($request->get_params());
            case "DELETE":
                return "deleted";
        }
    }

    public function rest_job_deleter(WP_REST_Request $request) {
        return $this->jobs->delete_job($request->get_param( 'id' ));
    }

    public function jobs_cud_permissions() {
        return current_user_can('edit_posts');
        return true;
    }
 }
 new STTV_Jobs_REST;