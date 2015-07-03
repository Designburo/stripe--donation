<?php
/*
Plugin Name: WordPress Stripe Integration
Plugin URI: http://pippinsplugins.com/
Description: A plugin to illustrate how to integrate Stripe and WordPress
Author: Pippin Williamson
Author URI: http://pippinsplugins.com
COntributors: mordauk
Version: 1.0
*/

/**********************************
* constants and globals
**********************************/

if(!defined('STRIPE_BASE_URL')) {
	define('STRIPE_BASE_URL', plugin_dir_url(__FILE__));
}
if(!defined('STRIPE_BASE_DIR')) {
	define('STRIPE_BASE_DIR', dirname(__FILE__));
}

$stripe_options = get_option('stripe_settings');

/*******************************************
* plugin text domain for translations
*******************************************/

load_plugin_textdomain( 'pippin_stripe', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );

/**********************************
* includes
**********************************/

if(is_admin()) {
	// load admin includes
	include(STRIPE_BASE_DIR . '/includes/settings.php');
} else {
	// load front-end includes
	include(STRIPE_BASE_DIR . '/includes/scripts.php');
	include(STRIPE_BASE_DIR . '/includes/shortcodes.php');
	include(STRIPE_BASE_DIR . '/includes/process-payment.php');
	include(STRIPE_BASE_DIR . '/includes/stripe-functions.php');
}


