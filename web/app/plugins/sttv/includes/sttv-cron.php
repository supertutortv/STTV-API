<?php

class Cron {

    public function __construct( $method ) {
        $this->$method();
    }

    public function __call( $method, $args ) {
        echo "Method $method does not exist.";
    }

    public function stuff() {
        echo 'Hello World!';
    }

}

$cron = new Cron( $argv[1] );