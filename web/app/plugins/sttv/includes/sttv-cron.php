<?php

namespace STTV;

if ( ! defined( 'ABSPATH' ) ) {exit;}

class Cron {

    public function __construct()
    {
        $methods = get_class_methods($this);

        foreach ($methods as $method) {
            if ($method != '__construct')
            {
                echo $this->{$method}();
            }
        }
    }
}