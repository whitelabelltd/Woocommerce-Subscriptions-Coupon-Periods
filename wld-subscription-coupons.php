<?php
/**
 * Plugin Name: Woocommerce Subscriptions Coupon Periods
 * Description: Allows free renewal for certain amount of periods. Settings are in Woocommerce > Subscriptions Tab
 * Version: 1.1.0
 * Author: Whitelabel Digital
 * Author URI: https://whitelabel.ltd
 * Requires at least: 4.8
 * Tested up to: 4.9.4
 * WC requires at least: 3.3.0
 * WC tested up to: 3.3.4
 * Text Domain: wcs-recurring-renewal-discount
 * Domain Path: /languages
 */

/**
 * Translate Text
 */
function wcs_recurring_renewal_discount_init() {
	$plugin_rel_path = basename( dirname( __FILE__ ) ) . '/languages'; /* Relative to WP_PLUGIN_DIR */
	load_plugin_textdomain( 'wcs-recurring-renewal-discount', false, $plugin_rel_path );
}
add_action('plugins_loaded', 'wcs_recurring_renewal_discount_init');


/**
 * Updates the date the customer will be charged on the checkout page
 * @param $order_total_html
 * @param $cart
 *
 * @return mixed|string
 */
function dfc_wld_subs_coupon_checkout( $order_total_html, $cart ) {

	// Load User set coupon code
	$coupon_code = get_option('wld-subscription-coupons_code','');

	// Make sure our coupon is applied and that it is not a re-subscription but it does contain a subscription
	if (!empty($coupon_code) && in_array($coupon_code,WC()->cart->applied_coupons) && !wcs_cart_contains_resubscribe() && WC_Subscriptions_Cart::cart_contains_subscription()) {

		$order_total_html_old = '';

		if ( 0 !== $cart->next_payment_date ) {
			$first_renewal_date = date_i18n( wc_date_format(), wcs_date_to_time( get_date_from_gmt( $cart->next_payment_date ) ) );

			// translators: placeholder is a date
			$order_total_html_old  = '<div class="first-payment-date"><small>' . sprintf( __( 'First renewal: %s', 'woocommerce-subscriptions' ), $first_renewal_date ) .  '</small></div>';
		}
		// Remove the standard String
		$order_total_html = str_replace($order_total_html_old,'',$order_total_html);

		// Get our new date

		$sub_period = get_option('wld-subscription-coupons_periods',0);

		if (!empty($sub_period) && is_numeric($sub_period) && 0 !== $sub_period) {

			$first_renewal_date = date_i18n( wc_date_format(), strtotime("+{$sub_period} week", wcs_date_to_time( get_date_from_gmt( $cart->next_payment_date ) )));

			// Add our own one
			// translators: placeholder is a number of periods until the customer is charged
			$order_total_html  .= '<div class="first-payment-text"><small>' . sprintf( __( 'You will not be charged until: %s', 'wcs-recurring-renewal-discount' ), $first_renewal_date ) .  '</small></div>';

		}

	}

	return $order_total_html;
}
add_filter('wcs_cart_totals_order_total_html','dfc_wld_subs_coupon_checkout', 20, 2);

/**
 * Adds the settings to Woocommerce Subscription Page
 * @param $settings
 *
 * @return mixed
 */
function dfc_wld_subs_coupons_settings( $settings ) {

	$option_id_prefix = 'wld-subscription-coupons';
	$option_id = $option_id_prefix;

	$settings[] = array(
		/* translators: Options Title */
		'name'          => _x( 'Subscription Coupon Periods', 'options section heading', 'wcs-recurring-renewal-discount' ),
		'type'          => 'title',
		/* translators: Options Page Description */
		'desc'          => _x('<p>Upon a renewal order it will apply a 100% discount to the order for a certain amount of periods. This can be useful for Allowing the first few renewals to be free in conjunction with a coupon.</p><p>&nbsp;</p>How to Use<br><ol><li>Create a coupon (eg; Fixed product discount)</li><li>Set the options below</li><li>Now for the periods set, the customer will not be charged</li></ol><p><p>&nbsp;</p>Do not use the recurring the coupons as no card details will be captured on checkout.</p>','wcs-recurring-renewal-discount'),
		'id'            => $option_id,
	);

	$settings[] = array(
		/* translators: Options Label for Coupon Code */
		'name'          => __( 'Coupon Code', 'wcs-recurring-renewal-discount' ),
		/* translators: Options Description for Coupon Code */
		'desc'          => _x( 'the coupon code that will be used for this plugin', 'wcs-recurring-renewal-discount' ),
		'id'            => $option_id_prefix . '_code',
		'css'           => 'min-width:150px;',
		'default'       => '',
		'type'          => 'text',
		'desc_tip'      => true,
	);

	$settings[] = array(
		/* translators: Options Label for Line Item */
		'name'          => __( 'Line Item Name', 'wcs-recurring-renewal-discount' ),
		/* translators: Options Description for Line Item */
		'desc'          => _x( 'The text listed on the order the discount is applied with. Leave blank to use the coupon description', 'wcs-recurring-renewal-discount' ),
		'id'            => $option_id_prefix . '_line_item',
		'css'           => 'min-width:150px;',
		/* translators: Options Default for Line Item */
		'default'       => _x('Sign-up Promo Discount','wcs-recurring-renewal-discount' ),
		'type'          => 'text',
		'desc_tip'      => true,
	);

	$settings[] = array(
		/* translators: Options Label for Periods */
		'name'          => __( 'How Many Periods', 'wcs-recurring-renewal-discount' ),
		/* translators: Options Description for Periods */
		'desc'          => _x( 'how many periods should it apply the coupon?', 'wcs-recurring-renewal-discount' ),
		'id'            => $option_id_prefix . '_periods',
		'css'           => 'min-width:150px;',
		'default'       => 4,
		'type'          => 'text',
		'desc_tip'      => true,
	);

	$settings[] = array( 'type' => 'sectionend', 'id' => $option_id );

	return $settings;

}
add_filter('woocommerce_subscription_settings','dfc_wld_subs_coupons_settings',10,1);

/**
 * Adds the needed custom META data to the subscription so we can track it on renewal
 * Only does it when the coupon code is detected.
 * @todo Add support for multiple subscriptions in the cart
 * @param $subscription
 * @param $order
 * @param $recurring_cart
 */
function dfc_wld_new_sub_created( $subscription , $order , $recurring_cart ) {

	// Get the options such as the coupon code that will used on the subscription
	$coupon_code_static = get_option('wld-subscription-coupons_code','');
	$coupon_periods_static = get_option('wld-subscription-coupons_periods',0);

	// Some data validation for the Coupon Code
	if (empty($coupon_code_static)) {
		return;
	}

	// Check periods are a number value and not 0
	if (empty($coupon_periods_static) || !is_numeric($coupon_periods_static) || 0 == $coupon_periods_static) {
		return;
	}

	// Round periods to a whole number
	$coupon_periods_static = round(floor($coupon_periods_static),0);

	// Does the subscription order contain a coupon?
	if ( ! empty( $order->get_used_coupons() ) ) {

		// As it might have multiple, lets go and check each one
		foreach( $order->get_used_coupons() as $coupon) {

			// Does it match the coupon in the settings?
			if ($coupon_code_static == $coupon) {

				// Using our defined Coupon code, add this to the subscription
				update_post_meta( $subscription->get_id() , '_dfc_wld_subs_coupons_use', true );

				// Adjust periods by 1 as when the order is placed we use up a period already
				if (1 < $coupon_periods_static) {
					$coupon_periods_static = $coupon_periods_static - 1;
				}
				
				// Set the renew periods left
				update_post_meta( $subscription->get_id() , '_dfc_wld_subs_coupons_periods_left', $coupon_periods_static );

				// Add Order note to the subscription for the store manager / admin
				/* translators: Order Note that is added to Admin part of the subscription */
				$subscription->add_order_note( sprintf( __('Sign-up Promo Discount Applied. For the next %s periods the customer will NOT be charged.' , 'wcs-recurring-renewal-discount' ) , ($coupon_periods_static - 1 ) ) );

			}
		}

	}
}
add_action('woocommerce_checkout_subscription_created','dfc_wld_new_sub_created', 20 , 3);


/**
 * Checks for coupon usage on the parent subscription and applies it for a certain amount of periods
 * Once the periods have elapsed it will cease applying the discount
 * @param $renewal_order
 * @param $subscription
 *
 * @return mixed $renewal_order
 */
function dfc_subs_recurring_discount( $renewal_order , $subscription ) {

	// Does the subscription contain our meta data?
	$is_free_sub_for_first_few_periods = get_post_meta( $subscription->get_id() , '_dfc_wld_subs_coupons_use', true );

	if (!empty($is_free_sub_for_first_few_periods) && true == $is_free_sub_for_first_few_periods) {
		// Yep its a special free for the first few periods subscription, lets get how many times left?

		$how_many_free_periods_remain = get_post_meta( $subscription->get_id() , '_dfc_wld_subs_coupons_periods_left' , true );

		if (!empty($how_many_free_periods_remain) && 1 <= $how_many_free_periods_remain) {
			// We have some free periods left, lets set the product for free renewal

			$order_total = get_post_meta( $renewal_order->get_id(), '_order_total' , true );

			// No point of applying a discount if the order is already free
			if (!empty($order_total) && 0 < $order_total) {

				// Set Discount based on the Cart Total
				$amount = $renewal_order->get_total();

				// Get Line Item from Options
				$line_item_description = get_option('wld-subscription-coupons_line_item','');
				if ('' == $line_item_description) {
					/* translators: Default Text used for Line Item for the Discount */
					$line_item_description = __('Sign-up Promo Discount','wcs-recurring-renewal-discount');
				}

				// Create Fee Object
				$fee = (object) array(
					'name' => $line_item_description,
					'amount' => wc_format_decimal(0 - $amount),
					'taxable' => FALSE,
					'tax_class' => NULL,
					'tax_data' => array(),
					'tax' => 0,
				);

				// Create Fee Item Object
				$item = new WC_Order_Item_Fee();
				$item->set_props( array(
					'name'      => $fee->name,
					'tax_class' => $fee->tax_class,
					'total'     => 0 - $amount,
					'total_tax' => 0,
					'order_id'  => $renewal_order->get_id(),
				) );
				$item->save();

				// Add the Fee Item Object to the Renewal Order
				$renewal_order->add_item( $item );

				// Add Order note to the order for the store manager / admin
				/* translators: Note that is added to Admin part of the order */



				$renewal_order->add_order_note( sprintf( __('Sign-up Promo Discount Applied. %s renewal periods remain' , 'wcs-recurring-renewal-discount' ) , ( $how_many_free_periods_remain - 1 ) ) );

				/* translators: Subscription Note that is added to Admin part of the order after the discount is applied */
				$subscription->add_order_note( sprintf( __('Sign-up Promo Discount Applied. %s renewal periods remain' , 'wcs-recurring-renewal-discount' ) , ( $how_many_free_periods_remain - 1 ) ) );

				// Update Totals
				$renewal_order->save();
				$renewal_order->set_total( $renewal_order->get_total() - $amount );

			}

			// Update the amount of periods
			$how_many_free_periods_remain_new = $how_many_free_periods_remain - 1;

			// Check if this is the last period?
			if (0 == $how_many_free_periods_remain_new) {
				// Add Order note to the order for the store manager / admin
				/* translators: Subscription Note that is added to Admin part of the order after the discount period has finished */
				$subscription->add_order_note( __('Sign-up Promo Discount Finished. Customer will now be charged as per normal','wcs-recurring-renewal-discount') );
			}

			update_post_meta( $subscription->get_id() , '_dfc_wld_subs_coupons_periods_left', $how_many_free_periods_remain_new );

		} else {

			// Remove the coupon traces as the free periods have ended
			delete_post_meta( $subscription->get_id(), '_dfc_wld_subs_coupons_periods_left');
			delete_post_meta( $subscription->get_id(), '_dfc_wld_subs_coupons_use');



		}

	}


	return $renewal_order;
}
add_filter('wcs_renewal_order_created','dfc_subs_recurring_discount', 5, 2);



