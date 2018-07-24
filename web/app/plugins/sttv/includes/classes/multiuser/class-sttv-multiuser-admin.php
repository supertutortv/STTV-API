<?php

namespace STTV\Multiuser;

defined( 'ABSPATH' ) || exit;

class Admin {

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'mukey_admin_page' ] );
    }

    public function mukey_admin_page() {
        add_menu_page(
            'Multi User Keys',     // page title
            'Multi User Keys',     // menu title
            'manage_options',   // capability
            'mu-keys',     // menu slug
            [ $this, 'mukey_render_admin_page' ], // callback function
            'dashicons-admin-network', // icon
            56 // position
        );
    }

    public function mukey_render_admin_page() {
        global $title;

        $keys = Keys::getAll();
        $keyss = '';
        foreach ($keys as $val) {
            $keyss .= '<span>'.json_encode($val).'</span><br>';
        }

        $courses = get_posts(['numberposts' => -1,'post_type' => 'courses']);
        $cselect = '<option value="" disabled selected>Select course</option>';
        foreach ( $courses as $course ) {
            $cselect .= "<option value='{$course->ID}'>{$course->post_title}</option>";
        }

        $users = get_users( [ 'role__in' => [ 'teacher', 'administrator' ] ] );
        $uselect = '<option value="" disabled selected>Select master</option>';
        foreach ( $users as $user ) {
            $uselect .= "<option value='{$user->ID}'>{$user->user_email}</option>";
        }
        $resturl = rest_url().'multiuser/keys';
        $html = <<<HTML
        <style type="text/css">
            header h1 {
                font-size: 42px;
            }
            .fullwidth {
                width: 100%;
                padding: 1em;
            }
            #keygen > * {
                margin-right: 1em
            }
        </style>
        <div class="wrap">
            <header class="mukey-header fullwidth"><h1>$title</h1></header>
            <div id="keygen" class="fullwidth">
                <input name="qty" type="text" placeholder="Qty" />
                <select name="master_user">$uselect</select>
                <select name="course_id">$cselect</select>
                <button class="button button-primary" type="submit">Generate keys</button>
            </div>
            <div id="generated-keys" class="fullwidth">
                $keyss
            </div>
        </div>
        <script>
        (function($){
            $('#keygen > .button').on('click', function(e){
                e.preventDefault();
                var data = {
                    qty : $('input[name=qty]','#keygen').val(),
                    user : $('select[name=master_user]','#keygen').val(),
                    course : $('select[name=course_id]','#keygen').val(),
                    email : $('select[name=master_user] option:selected','#keygen').text()
                }
                $.ajax({
                    url : $resturl,
                    method : 'POST',
                    data : data,
                    success : function(d) {
                        console.log(d)
                        //window.location.reload(false)
                    },
                    error : function(x) {
                        console.log(x)
                    }
                })
            })
        })(jQuery)
        </script>
HTML;
        print $html;
    }
}