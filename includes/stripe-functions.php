<?php

function pippin_get_stripe_plans() {

	global $stripe_options;

	// load the stripe libraries
	if( !class_exists( 'Stripe' ) )
		require_once(STRIPE_BASE_DIR . '/lib/Stripe.php');
	
	// check if we are using test mode
	if(isset($stripe_options['test_mode']) && $stripe_options['test_mode']) {
		$secret_key = $stripe_options['test_secret_key'];
	} else {
		$secret_key = $stripe_options['live_secret_key'];
	}

	Stripe::setApiKey($secret_key);
	// retrieve all plans from stripe
	$plans_data = Stripe_Plan::all();
	// setup a blank array
	$plans = array();
	if($plans_data) {
		foreach($plans_data['data'] as $plan) {
			// store the plan ID as the array key and the plan name as the value
			$plans[$plan['id']] = $plan['name'];
		}
	}
	return $plans;
}