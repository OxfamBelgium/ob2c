<?php
	
	if ( ! defined('ABSPATH') ) exit;
	
	#############
	# VILVOORDE #
	#############
	
	add_action( 'init', 'delay_actions_and_filters_till_load_completed_34' );
	
	function delay_actions_and_filters_till_load_completed_34() {
		if ( get_current_blog_id() === 34 ) {
			// Schakel afrekenen uit van 21/06/2025 t.e.m. 08/07/2025
			add_filter( 'woocommerce_available_payment_gateways', 'vilvoorde_disable_all_payment_methods', 10, 1 );
			add_filter( 'woocommerce_no_available_payment_methods_message', 'vilvoorde_print_explanation_if_disabled', 1000, 1 );
			add_filter( 'woocommerce_order_button_html', 'vilvoorde_disable_checkout_button', 10, 1 );
		}
	}
	
	function vilvoorde_disable_all_payment_methods( $methods ) {
		if ( date_i18n('Y-m-d') >= '2025-06-21' and date_i18n('Y-m-d') <= '2025-07-08' ) {
			return array();
		}
		return $methods;
	}
	
	function vilvoorde_print_explanation_if_disabled( $text ) {
		return get_option('oxfam_sitewide_banner_top');
	}
	
	function vilvoorde_disable_checkout_button( $html ) {
		if ( date_i18n('Y-m-d') >= '2025-06-21' and date_i18n('Y-m-d') <= '2025-07-08' ) {
			$original_button = __( 'Place order', 'woocommerce' );
			return str_replace( '<input type="submit"', '<input type="submit" disabled="disabled"', str_replace( $original_button, 'Bestellen tijdelijk onmogelijk', $html ) );
		}
		return $html;
	}