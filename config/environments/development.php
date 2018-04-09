<?php
/** Development */
define('SAVEQUERIES', true);
define('WP_DEBUG', true);
define('SCRIPT_DEBUG', true);

/** Stripe Keys */
define('STRIPE_PK', env('STRIPE_PK_TEST'));
define('STRIPE_SK', env('STRIPE_SK_TEST'));
define('STRIPE_WHSEC', env('STRIPE_WHSEC_TEST'));
