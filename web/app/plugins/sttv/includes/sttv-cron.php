<?php

class Cron {

    public function __construct( $method ) {
        $this->$method();
    }

    public function __call( $method, $args ) {
        echo "Method '$method' does not exist.";
    }

    public function vcache() {
        echo 'Hello World!';
    }

}

$cron = new Cron( $argv[1] );