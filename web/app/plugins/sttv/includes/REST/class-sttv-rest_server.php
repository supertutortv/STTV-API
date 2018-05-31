<?php
if ( ! defined( 'ABSPATH' ) ) exit;
/**
 * SupertutorTV REST Server class.
 *
 * Extends the WP_REST_Server class to simply override the serve_request method to not return JSON.
 *
 * @class 		STTV_REST_Server
 * @version		1.4.0
 * @package		STTV
 * @category	Class
 * @author		Supertutor Media, inc.
 */
class STTV_REST_Server extends WP_REST_Server {
    public function serve_request( $path = null ) {
        $content_type = isset( $_GET['_jsonp'] ) ? 'application/javascript' : 'application/json';
        $this->send_header( 'Content-Type', $content_type . '; charset=' . get_option( 'blog_charset' ) );
        $this->send_header( 'X-Robots-Tag', 'noindex' );
 
        $api_root = get_rest_url();
        if ( ! empty( $api_root ) ) {
            $this->send_header( 'Link', '<' . esc_url_raw( $api_root ) . '>; rel="https://api.w.org/"' );
        }
 
        /*
         * Mitigate possible JSONP Flash attacks.
         *
         * https://miki.it/blog/2014/7/8/abusing-jsonp-with-rosetta-flash/
         */
        $this->send_header( 'X-Content-Type-Options', 'nosniff' );
        $this->send_header( 'Access-Control-Expose-Headers', 'X-WP-Total, X-WP-TotalPages' );
        $this->send_header( 'Access-Control-Allow-Headers', 'Authorization, Content-Type' );
 
        /**
         * Send nocache headers on authenticated requests.
         *
         * @since 4.4.0
         *
         * @param bool $rest_send_nocache_headers Whether to send no-cache headers.
         */
        $send_no_cache_headers = apply_filters( 'rest_send_nocache_headers', is_user_logged_in() );
        if ( $send_no_cache_headers ) {
            foreach ( wp_get_nocache_headers() as $header => $header_value ) {
                if ( empty( $header_value ) ) {
                    $this->remove_header( $header );
                } else {
                    $this->send_header( $header, $header_value );
                }
            }
        }
 
        /**
         * Filters whether the REST API is enabled.
         *
         * @since 4.4.0
         * @deprecated 4.7.0 Use the rest_authentication_errors filter to restrict access to the API
         *
         * @param bool $rest_enabled Whether the REST API is enabled. Default true.
         */
        apply_filters_deprecated( 'rest_enabled', array( true ), '4.7.0', 'rest_authentication_errors',
            __( 'The REST API can no longer be completely disabled, the rest_authentication_errors filter can be used to restrict access to the API, instead.' )
        );
 
        /**
         * Filters whether jsonp is enabled.
         *
         * @since 4.4.0
         *
         * @param bool $jsonp_enabled Whether jsonp is enabled. Default true.
         */
        $jsonp_enabled = apply_filters( 'rest_jsonp_enabled', true );
 
        $jsonp_callback = null;
 
        if ( isset( $_GET['_jsonp'] ) ) {
            if ( ! $jsonp_enabled ) {
                echo $this->json_error( 'rest_callback_disabled', __( 'JSONP support is disabled on this site.' ), 400 );
                return false;
            }
 
            $jsonp_callback = $_GET['_jsonp'];
            if ( ! wp_check_jsonp_callback( $jsonp_callback ) ) {
                echo $this->json_error( 'rest_callback_invalid', __( 'Invalid JSONP callback function.' ), 400 );
                return false;
            }
        }
 
        if ( empty( $path ) ) {
            if ( isset( $_SERVER['PATH_INFO'] ) ) {
                $path = $_SERVER['PATH_INFO'];
            } else {
                $path = '/';
            }
        }
 
        $request = new WP_REST_Request( $_SERVER['REQUEST_METHOD'], $path );
 
        $request->set_query_params( wp_unslash( $_GET ) );
        $request->set_body_params( wp_unslash( $_POST ) );
        $request->set_file_params( $_FILES );
        $request->set_headers( $this->get_headers( wp_unslash( $_SERVER ) ) );
        $request->set_body( $this->get_raw_data() );
 
        /*
         * HTTP method override for clients that can't use PUT/PATCH/DELETE. First, we check
         * $_GET['_method']. If that is not set, we check for the HTTP_X_HTTP_METHOD_OVERRIDE
         * header.
         */
        if ( isset( $_GET['_method'] ) ) {
            $request->set_method( $_GET['_method'] );
        } elseif ( isset( $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] ) ) {
            $request->set_method( $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] );
        }
 
        $result = $this->check_authentication();
 
        if ( ! is_wp_error( $result ) ) {
            $result = $this->dispatch( $request );
        }
 
        // Normalize to either WP_Error or WP_REST_Response...
        $result = rest_ensure_response( $result );
 
        // ...then convert WP_Error across.
        if ( is_wp_error( $result ) ) {
            $result = $this->error_to_response( $result );
        }
 
        /**
         * Filters the API response.
         *
         * Allows modification of the response before returning.
         *
         * @since 4.4.0
         * @since 4.5.0 Applied to embedded responses.
         *
         * @param WP_HTTP_Response $result  Result to send to the client. Usually a WP_REST_Response.
         * @param WP_REST_Server   $this    Server instance.
         * @param WP_REST_Request  $request Request used to generate the response.
         */
        $result = apply_filters( 'rest_post_dispatch', rest_ensure_response( $result ), $this, $request );
 
        // Wrap the response in an envelope if asked for.
        if ( isset( $_GET['_envelope'] ) ) {
            $result = $this->envelope_response( $result, isset( $_GET['_embed'] ) );
        }
 
        // Send extra data from response objects.
        $headers = $result->get_headers();
        $this->send_headers( $headers );
 
        $code = $result->get_status();
        $this->set_status( $code );
 
        /**
         * Filters whether the request has already been served.
         *
         * Allow sending the request manually - by returning true, the API result
         * will not be sent to the client.
         *
         * @since 4.4.0
         *
         * @param bool             $served  Whether the request has already been served.
         *                                           Default false.
         * @param WP_HTTP_Response $result  Result to send to the client. Usually a WP_REST_Response.
         * @param WP_REST_Request  $request Request used to generate the response.
         * @param WP_REST_Server   $this    Server instance.
         */
        $served = apply_filters( 'rest_pre_serve_request', false, $result, $request, $this );
 
        if ( ! $served ) {
            if ( 'HEAD' === $request->get_method() ) {
                return null;
            }
 
            // Embed links inside the request.
            $result = $this->response_to_data( $result, isset( $_GET['_embed'] ) );
 
            /**
             * Filters the API response.
             *
             * Allows modification of the response data after inserting
             * embedded data (if any) and before echoing the response data.
             *
             * @since 4.8.1
             *
             * @param array            $result  Response data to send to the client.
             * @param WP_REST_Server   $this    Server instance.
             * @param WP_REST_Request  $request Request used to generate the response.
             */
            $result = apply_filters( 'rest_pre_echo_response', $result, $this, $request );
 
            //$result = wp_json_encode( $result );
 
            $json_error_message = $this->get_json_last_error();
            if ( $json_error_message ) {
                $json_error_obj = new WP_Error( 'rest_encode_error', $json_error_message, array( 'status' => 500 ) );
                $result = $this->error_to_response( $json_error_obj );
                $result = wp_json_encode( $result->data[0] );
            }
 
            if ( $jsonp_callback ) {
                // Prepend '/**/' to mitigate possible JSONP Flash attacks.
                // https://miki.it/blog/2014/7/8/abusing-jsonp-with-rosetta-flash/
                echo '/**/' . $jsonp_callback . '(' . $result . ')';
            } else {
                echo $result;
            }
        }
        return null;
    }
}