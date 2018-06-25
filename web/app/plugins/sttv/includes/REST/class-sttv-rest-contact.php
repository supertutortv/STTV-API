<?php

namespace STTV\REST;

defined( 'ABSPATH' ) || exit;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * SupertutorTV Forms controller class.
 *
 * Properties, methods, routes, and endpoints for processing the forms on the SupertutorTV website.
 * This includes, for now, the login/logout process, contact form, and email list subscription form.
 *
 * @class 		Forms
 * @version		2.0.0
 * @package		STTV
 * @category	Class
 * @author		Supertutor Media, inc.
 */
class Contact extends \WP_REST_Controller {}