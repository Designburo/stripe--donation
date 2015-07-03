<?php

function pippin_stripe_process_payment() {
	if ( isset( $_POST['action'] ) && $_POST['action'] == 'stripe' && wp_verify_nonce( $_POST['stripe_nonce'], 'stripe-nonce' ) ) {

		global $stripe_options;

		// load the stripe libraries
		if ( !class_exists( 'Stripe' ) )
			require_once STRIPE_BASE_DIR . '/lib/Stripe.php';

		$amount = base64_decode( $_POST['amount'] ) * 100;

		// retrieve the token generated by stripe.js
		$token = $_POST['stripeToken'];

		// check if we are using test mode
		if ( isset( $stripe_options['test_mode'] ) && $stripe_options['test_mode'] ) {
			$secret_key = trim( $stripe_options['test_secret_key'] );
		} else {
			$secret_key = trim( $stripe_options['live_secret_key'] );
		}

		Stripe::setApiKey( $secret_key );

		$using_discount = false;

		// check for a discount code and make sure it is valid if present
		if ( isset( $_POST['discount'] ) && strlen( trim( $_POST['discount'] ) ) > 0 ) {

			$using_discount = true;

			// we have a discount code, now check that it is valid

			try {

				$coupon = Stripe_Coupon::retrieve( trim( $_POST['discount'] ) );
				// if we got here, the coupon is valid

			} catch ( Exception $e ) {

				// an exception was caught, so the code is invalid
				wp_die( __( 'The coupon code you entered is invalid. Please click back and enter a valid code, or leave it blank for no discount.', 'pippin' ), 'Error' );

			}

		}

		if ( isset( $_POST['recurring'] ) && $_POST['recurring'] == 'yes' ) { // process a recurring payment

			$plan_id = strip_tags( trim( $_POST['plan_id'] ) );

			try {

				if ( is_user_logged_in() )
					$customer_id = get_user_meta( get_current_user_id(), '_stripe_customer_id', true );
				else
					$customer_id = false;

				if ( $customer_id ) {

					// retrieve our customer from Stripe
					$cu = Stripe_Customer::retrieve( $customer_id );

					// update the customer's card info (in case it has changed )
					$cu->card = $token;

					// update a customer's subscription
					$cu->updateSubscription( array(
							'plan' => $plan_id
						)
					);

					// save everything
					$cu->save();

				} else {

					// create a brand new customer
					$customer = Stripe_Customer::create( array(
							'card' => $token,
							'plan' => $plan_id,
							'email' => isset( $_POST['email'] ) ? strip_tags( trim( $_POST['email'] ) ) : null,
							'coupon' => $using_discount ? trim( $_POST['discount'] ) : null
						)
					);

					if ( is_user_logged_in () ) {
						// store the new customer ID in the meta table
						update_user_meta( get_current_user_id(), '_stripe_customer_id', $customer->id );
					}

					$customer_id = $customer->id;
				}

				if ( isset( $stripe_options['one_time_fee'] ) ) {

					$amount = $stripe_options['fee_amount'] * 100;

					$invoice_item = Stripe_InvoiceItem::create( array(
							'customer'    => $customer_id, // the customer to apply the fee to
							'amount'      => $amount, // amount in cents
							'currency'    => 'usd',
							'description' => 'One-time setup fee' // our fee description
						) );

					$invoice = Stripe_Invoice::create( array(
							'customer'    => $customer_id, // the customer to apply the fee to
						) );

					$invoice->pay();

				}

				// redirect on successful recurring payment setup
				$redirect = add_query_arg( 'payment', 'paid', $_POST['redirect'] );

			} catch ( Exception $e ) {
				// redirect on failure
				wp_die( $e, 'Error' );
				$redirect = add_query_arg( 'payment', 'failed', $_POST['redirect'] );
			}

		} else { // process a one-time payment

			// attempt to charge the customer's card
			try {

				if ( $using_discount !== false ) {
					// calculate the discounted price
					$amount = $amount - ( $amount * ( $coupon->percent_off / 100 ) );
				}

				if ( is_user_logged_in() )
					$customer_id = get_user_meta( get_current_user_id(), '_stripe_customer_id', true );
				else
					$customer_id = false;

				if ( !$customer_id ) {

					// create a new customer if our current user doesn't have one
					$customer = Stripe_Customer::create( array(
							'card' => $token,
							'email' => strip_tags( trim( $_POST['email'] ) )
						)
					);

					$customer_id = $customer->id;

					if ( is_user_logged_in () ) {
						update_user_meta( get_current_user_id(), '_stripe_customer_id', $customer_id );
					}
				}
				if ( $customer_id ) {

					$charge = Stripe_Charge::create( array(
							'amount' => $amount, // amount in cents
							'currency' => 'usd',
							'customer' => $customer_id
						)
					);

				} else {
					// the customer wasn't found or created, throw an error
					throw new Exception( __( 'A customer could not be created, or no customer was found.', 'pippin' ) );
				}

				// redirect on successful payment
				$redirect = add_query_arg( 'payment', 'paid', $_POST['redirect'] );

			} catch ( Exception $e ) {
				wp_die( $e );
				// redirect on failed payment
				$redirect = add_query_arg( 'payment', 'failed', $_POST['redirect'] );
			}
		}

		// redirect back to our previous page with the added query variable
		wp_redirect( $redirect ); exit;
	}
}
add_action( 'init', 'pippin_stripe_process_payment' );