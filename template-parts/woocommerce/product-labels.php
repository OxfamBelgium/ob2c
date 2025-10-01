<?php
	global $product;
	$labels = array();
	
	// Tag automatisch alle producten die deelnemen aan de koffie-actie (maart 2025)
	// Het product hoeft dus niet in promo te staan!
	// if ( ! is_b2b_customer() and wp_date('Y-m-d') >= '2025-03-01' and wp_date('Y-m-d') <= '2025-03-31' ) {
	// 	$terms = array( 'bonen', 'gemalen', 'pads', 'instant' );
	// 	foreach ( $terms as $term ) {
	// 		$coffee_term = get_term_by( 'slug', $term, 'product_cat' );
	// 		if ( $coffee_term !== false ) {
	// 			if ( in_array( $coffee_term->term_id, $product->get_category_ids() ) ) {
	// 				$labels['promotion'] = 'Gratis reep chocolade';
	// 				break;
	// 			}
	// 		}
	// 	}
	// }
	
	if ( ! is_b2b_customer() and $product->is_on_sale() ) {
		// Neem algemeen label als default
		$labels['promotion'] = 'Promo';
		
		// Zoek vervolgens de details van de actie op
		// Handmatig beheerde lijst, want simpeler dan afleiden uit de kortingsregels!
		
		$wijnfestival_products = array( 20211, 20225, 20262, 20067, 20068, 20074, 20413, 20415 );
		if ( in_array( $product->get_sku(), $wijnfestival_products ) ) {
			$labels['promotion'] = '-15% per 2 flessen';
		}
		
		$fifty_percent_off_second_products = get_site_option( 'oxfam_shop_promotion_products_fifty_percent_off_second', array() );
		if ( in_array( $product->get_sku(), $fifty_percent_off_second_products ) ) {
			$labels['promotion'] = 'Promo 2de -50%';
		}
		
		$one_plus_one_products = get_site_option( 'oxfam_shop_promotion_products_one_plus_one', array() );
		if ( in_array( $product->get_sku(), $one_plus_one_products ) ) {
			$labels['promotion'] = 'Promo 1+1 gratis';
		}
		
		$two_plus_two_products = array();
		if ( in_array( $product->get_sku(), $two_plus_two_products ) ) {
			$labels['promotion'] = 'Promo 2+2 gratis';
		}
		
		$three_plus_two_products = array();
		if ( in_array( $product->get_sku(), $three_plus_two_products ) ) {
			$labels['promotion'] = 'Promo 3+2 gratis';
		}
		
		$ten_percent_off_products = array( 22023, 22024, 26400, 26401 );
		if ( in_array( $product->get_sku(), $ten_percent_off_products ) ) {
			$labels['promotion'] = 'Promo -10%';
		}
		
		$twentyfive_percent_off_products = array();
		if ( in_array( $product->get_sku(), $twentyfive_percent_off_products ) ) {
			$labels['promotion'] = 'Promo -25%';
		}
		
		$fifty_percent_off_products = array();
		if ( in_array( $product->get_sku(), $fifty_percent_off_products ) ) {
			$labels['promotion'] = 'Promo -50%';
		}
		
		$two_plus_one_products = get_site_option( 'oxfam_shop_promotion_products_two_plus_one', array() );
		if ( in_array( $product->get_sku(), $two_plus_one_products ) ) {
			$labels['promotion'] = 'Promo 2+1 gratis';
		}
		
		$three_plus_one_products = array();
		if ( in_array( $product->get_sku(), $three_plus_one_products ) ) {
			$labels['promotion'] = 'Promo 3+1 gratis';
		}
		
		$four_plus_two_products = array();
		if ( in_array( $product->get_sku(), $four_plus_two_products ) ) {
			$labels['promotion'] = 'Promo 4+2 gratis';
		}
		
		$five_plus_one_products = array();
		if ( in_array( $product->get_sku(), $five_plus_one_products ) ) {
			$labels['promotion'] = 'Promo 5+1 gratis';
		}
	}
	
	if ( $product->get_date_created() !== NULL and $product->get_date_created()->date_i18n('Y-m-d') > date_i18n( 'Y-m-d', strtotime('-3 months') ) ) {
		$labels['newbee'] = 'Nieuw';
	}
	
	if ( stripos( $product->get_attribute('preferences'), 'biologisch' ) !== false ) {
		$labels['organic'] = 'Bioproduct';
	}
	
	if ( ! is_main_site() and ! does_risky_delivery() ) {
		if ( $product->get_shipping_class() === 'breekbaar' ) {
			$labels['pickup-only'] = 'Afhaling';
		}
	}
	
	if ( count( $labels ) > 0 ) {
		echo '<ul class="info-labels">';
		foreach ( $labels as $class => $label ) {
			echo '<li class="info-label '.$class.'">'.$label.'</li>';
		}
		echo '</ul>';
	}