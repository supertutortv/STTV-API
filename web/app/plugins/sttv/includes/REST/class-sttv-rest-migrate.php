<?php

namespace STTV\REST;

defined( 'ABSPATH' ) || exit;

use WP_Error;
use WP_User;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * SupertutorTV Migrate controller class.
 *
 * Properties, methods, routes, and endpoints for processing and managing the user migration process on all SupertutorTV web applications.
 *
 * @class 		Migrate
 * @version		2.0.0
 * @package		STTV
 * @category	Class
 * @author		Supertutor Media, inc.
 */
class Migrate extends \WP_REST_Controller {

    public function __construct() {}

    public function register_routes() {
        $routes = [
            '/users' => [
                [
                    'methods' => 'POST',
                    'callback' => [ $this, 'users' ],
                    'permission_callback' => [ $this, 'verifyMigrate' ]
                ]
            ]
        ];

        foreach ( $routes as $route => $endpoint ) {
            register_rest_route( 'migrate', $route, $endpoint );
        }
    }

    public function users( WP_REST_Request $request ) {
        return sttv_rest_response(
            'migration',
            'Migration successful',
            200,
            $request->get_headers()
        );
    }

    public function verifyMigrate( WP_REST_Request $request ) {
        return true;
    }
}