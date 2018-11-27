<?php

function get_mandrill_template( $temp = 'default', $args ) {
    $content = [
        'default' => [],
        'course_welcome' => ''
    ];
    return $content[$temp];
}