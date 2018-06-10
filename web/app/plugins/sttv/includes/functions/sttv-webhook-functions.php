<?php

defined( 'ABSPATH' ) || exit;

function trail_expiration_checker() {
    return 'Hello world!';
}

// charge.succeeded
function charge_succeeded( $data ) {

}

// invoice.created
function invoice_created( $data ) {
    return $data;
}