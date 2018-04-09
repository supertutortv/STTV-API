<?php
/** Staging */
ini_set('display_errors', 0);
define('WP_DEBUG_DISPLAY', false);
define('SCRIPT_DEBUG', false);
/** Disable all file modifications including updates and update notifications */
define('DISALLOW_FILE_MODS', true);

/** Stripe Keys */
define('STRIPE_PK', env('STRIPE_PK_LIVE'));
define('STRIPE_SK', env('STRIPE_SK_LIVE'));
define('STRIPE_WHSEC', env('STRIPE_WHSEC_LIVE'));
