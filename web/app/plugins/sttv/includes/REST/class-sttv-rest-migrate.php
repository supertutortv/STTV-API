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
        $body = unserialize($request->get_body());
        $returned = [];
        
        foreach ($body as $user) {
            if ($user instanceof WP_User) {
                unset($user->data->ID);
                $uid = wp_insert_user($user);
                if (is_wp_error($uid)) continue;

                $uu = new WP_User($uid);
                $uu->add_role('the_best_act_prep_course_ever');
                $umeta = [
                    'user' => [
                        'subscription' => '',
                        'history' => [],
                        'downloads' => [],
                        'type' => 'standard',
                        'trialing' => false,
                        'settings' => [
                            'autoplay' => [
                                'msl' => false,
                                'playlist' => false
                            ],
                            'dark_mode' => false
                        ],
                        'userdata' => [
                            'login_timestamps' => []
                        ]
                    ],
                    'courses' => ['the-best-act-prep-course-ever'=>[]]
                ];
            
                update_user_meta( $user->ID, 'sttv_user_data', $umeta );

                $returned[$uid] = [
                    'user_can_act' => user_can($uid,'the_best_act_prep_course_ever'),
                    'id' => $uid,
                    'meta' => $umeta
                ];
            }
        }
        return sttv_rest_response(
            'migration',
            'Migration successful',
            200,
            $returned
        );
    }

    public function verifyMigrate( WP_REST_Request $request ) {
        return $request->get_header('STTVWHSEC') === hash_hmac( 'sha256', $request->get_body(), STTV_WHSEC );
    }
}