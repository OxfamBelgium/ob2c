<?php

	if ( ! defined('ABSPATH') ) exit;

	// Toon custom Oxfam-berichten met actuele info bovenaan adminpagina's
	// Moet een latere prioriteit hebben dan hide_non_oxfam_notices!
	add_action( 'admin_head', 'show_oxfam_notices', 20000 );

	function show_oxfam_notices() {
		add_action( 'admin_notices', 'oxfam_admin_notices_dashboard' );
		add_action( 'admin_notices', 'oxfam_admin_notices_reports' );
		// Uitgeschakeld
		// add_action( 'network_admin_notices', 'oxfam_network_admin_notices' );
	}

	function oxfam_admin_notices_dashboard() {
		global $pagenow, $post_type;
		$screen = get_current_screen();
		
		if ( 'index.php' === $pagenow and 'dashboard' === $screen->base ) {
			if ( in_array( get_current_blog_id(), get_site_option('oxfam_blocked_sites') ) ) {
				echo '<div class="notice notice-error">';
					echo '<p>Deze webshop is momenteel reeds bereikbaar (voor o.a. Mollie-controle) maar verschijnt nog niet in de winkelzoeker!</p>';
				echo '</div>';
			}
			if ( get_option('mollie-payments-for-woocommerce_test_mode_enabled') === 'yes' ) {
				echo '<div class="notice notice-success">';
					echo '<p>De betalingen op deze site staan momenteel in testmodus! Voel je vrij om naar hartelust te experimenteren met bestellingen.</p>';
				echo '</div>';
			}
			
			if ( get_current_site()->domain === 'shop.oxfamwereldwinkels.be' ) {
				if ( '' !== ( $success = get_site_option( 'oxfam_shop_dashboard_notice_success', '' ) ) ) {
					echo '<div class="notice notice-success">';
						echo '<p>' . stripslashes( $success ) . '</p>';
					echo '</div>';
				}
				
				// Er werden twee geschenkverpakkingen toegevoegd: een geschenkmand (servicekost: 3,95 euro, enkel afhaling) en een geschenkdoos (servicekost: 2,50 euro, ook thuislevering). Door minstens één product op voorraad te zetten activeer je de module. Onder het winkelmandje verschijnt dan een opvallende knop om een geschenkverpakking toe te voegen. <a href="https://github.com/OxfamBelgium/ob2c/wiki/9.-Lokaal-assortiment#geschenkverpakkingen" target="_blank">Raadpleeg de handleiding voor info over de werking en hoe je zelf geschenkverpakkingen kunt aanmaken met andere prijzen/voorwaarden.</a> Opmerking: indien je thuislevering van breekbare goederen inschakelde onder <a href="admin.php?page=oxfam-options">Winkelgegevens</a> kan de geschenkmand ook thuisgeleverd worden.
				if ( '' !== ( $info = get_site_option( 'oxfam_shop_dashboard_notice_info', '' ) ) ) {
					echo '<div class="notice notice-info">';
						echo '<p>' . stripslashes( $info ) . '</p>';
					echo '</div>';
				}
				
				if ( '' !== ( $warning = get_site_option( 'oxfam_shop_dashboard_notice_warning', '' ) ) ) {
					echo '<div class="notice notice-warning">';
						echo '<p>' . stripslashes( $warning ) . '</p>';
					echo '</div>';
				}
				
				$new_skus = get_site_option( 'oxfam_shop_dashboard_notice_new_products', array() );
				if ( count( $new_skus ) > 0 ) {
					echo '<div class="notice notice-success">';
						echo '<p>Deze nieuwigheden werden onlangs toegevoegd aan de database:</p><ul style="margin-left: 2em; column-count: 2;">';
							foreach ( $new_skus as $sku ) {
								$product_id = wc_get_product_id_by_sku( $sku );
								if ( $product_id ) {
									$product = wc_get_product( $product_id );
									echo '<li><a href="'.$product->get_permalink().'" target="_blank">'.$product->get_title().'</a> ('.$product->get_meta('_shopplus_code').')</li>';
								}
							}
						echo '</ul><p>';
						if ( current_user_can('manage_network_users') ) {
							echo 'Je herkent deze producten aan de blauwe achtergrond onder \'<a href="admin.php?page=oxfam-products-list">Voorraadbeheer</a>\'. ';
						}
						echo 'Pas wanneer een beheerder ze in voorraad plaatst, worden deze producten bestelbaar voor klanten.</p>';
					echo '</div>';
				}
				
				$replaced_skus = get_site_option( 'oxfam_shop_dashboard_notice_replaced_products', array() );
				if ( count( $replaced_skus ) > 0 ) {
					echo '<div class="notice notice-success">';
						echo '<p>Volgende referenties vervangen een bestaand product (met ongewijzigde verpakking):</p><ul style="margin-left: 2em; column-count: 2;">';
							foreach ( $replaced_skus as $old_new ) {
								$parts = explode( '-', $old_new );
								if ( count( $parts ) !== 2 ) {
									continue;
								}
								$old_sku = $parts[0];
								$new_sku = $parts[1];
								$product_id = wc_get_product_id_by_sku( $new_sku );
								if ( $product_id ) {
									$product = wc_get_product( $product_id );
									echo '<li><a href="'.$product->get_permalink().'" target="_blank">'.$product->get_sku().' '.$product->get_title().'</a> ('.$product->get_meta('_shopplus_code').')';
									
									$old_product_id = wc_get_product_id_by_sku( $old_sku );
									$old_product = wc_get_product( $old_product_id );
									if ( $old_product ) {
										echo ', vervangt '.$old_product->get_sku().' '.$old_product->get_title();
									}
									
									echo '</li>';
								}
							}
						echo '</ul><p>De voorraadstatus van het bestaande product werd overgenomen en het oude ompaknummer werd verborgen.</p>';
					echo '</div>';
				}
				
				$deleted_skus = get_site_option( 'oxfam_shop_dashboard_notice_deleted_products', array() );
				if ( count( $deleted_skus ) > 0 ) {
					echo '<div class="notice notice-warning">';
						echo '<p>Volgende uitgefaseerde referenties werden uit de database verwijderd omdat hun uiterste houdbaarheid inmiddels gepasseerd is (of omdat het product niet langer relevant is):</p><ul style="margin-left: 2em; column-count: 2;">';
							global $wpdb;
							foreach ( $deleted_skus as $sku ) {
								// Standaardfunctie wc_get_product_id_by_sku() filtert producten die in de prullenbak zitten weg!
								// Gebruik gelijkaardige query, afgekeken van https://github.com/woocommerce/woocommerce/blob/trunk/plugins/woocommerce/includes/data-stores/class-wc-product-data-store-cpt.php#L1010
								$product_id = $wpdb->get_var(
									$wpdb->prepare(
										"SELECT posts.ID
										FROM {$wpdb->posts} as posts
										INNER JOIN {$wpdb->wc_product_meta_lookup} AS lookup ON posts.ID = lookup.product_id
										WHERE posts.post_type IN ( 'product', 'product_variation' )
										AND lookup.sku = %s
										LIMIT 1",
										$sku
									)
								);
								
								if ( $product_id ) {
									$product = wc_get_product( $product_id );
									echo '<li>'.$product->get_title().' ('.$product->get_meta('_shopplus_code').')</li>';
								}
							}
						echo '</ul><p>Als je dat nog niet deed: denk eraan om het vervangende product op voorraad te nemen, indien beschikbaar (bv. geschenkencheques => nieuwe artikelnummers met langere geldigheidsdatum).</p>';
					echo '</div>';
				}
				
				// Het is momenteel niet werkbaar om de volledige productcatalogus van Magasins du Monde (+/- 2.500 voorradige producten) in het webshopnetwerk te pompen: dit stelt hogere eisen aan de productdata, de zoekfunctie, het voorraadbeheer, onze server, ... Bovendien is het voor de consument weinig zinvol om alle non-food te presenteren in onze nationale catalogus, gezien de beperkte lokale beschikbaarheid van de oudere craftsproducten.
				// echo '<p>Verder werden de prijzen van alle craftsproducten in de nationale database (eindelijk) gelijk getrokken met de adviesprijzen van MDM in ShopPlus (incl. de meest recente wijzigingen van 1 oktober). Daarnaast maakten we een resem extra referenties beschikbaar die de voorbije maanden verschenen:</p>';
				// echo '<ul>';
				// 	echo '<li>Kalenders en agenda\'s voor 2023:<ul style="margin-left: 2em; column-count: 2;">';
				// 		$skus = array( 87570, 87571, 87420, 87421, 87422, 87423, 87424, 87425, 87426, 87427, 87428, 87429, 87430, 87431 );
				// 		foreach ( $skus as $sku ) {
				// 			$product_id = wc_get_product_id_by_sku( $sku );
				// 			if ( $product_id ) {
				// 				$product = wc_get_product( $product_id );
				// 				echo '<li><a href="'.$product->get_permalink().'" target="_blank">'.$product->get_title().'</a> ('.$product->get_meta('_shopplus_code').')</li>';
				// 			}
				// 		}
				// 	echo '</ul></li>';
				//
				// 	echo '<li>Nieuwe verzorgingsproducten:<ul style="margin-left: 2em; column-count: 2;">';
				// 		$skus = array( 45247, 45258, 45262, 45267, 45390, 65200, 65202, 65204, 65205, 65207, 65208, 65209, 65215, 65228, 65229, 65269, 65270, 65273, 65274, 87359, 87360, 87361 );
				// 		foreach ( $skus as $sku ) {
				// 			$product_id = wc_get_product_id_by_sku( $sku );
				// 			if ( $product_id ) {
				// 				$product = wc_get_product( $product_id );
				// 				echo '<li><a href="'.$product->get_permalink().'" target="_blank">'.$product->get_title().'</a> ('.$product->get_meta('_shopplus_code').')</li>';
				// 			}
				// 		}
				// 	echo '</ul></li>';
				//
				// 	echo '<li>Nieuwe onderhoudsproducten:<ul style="margin-left: 2em; column-count: 2;">';
				// 		$skus = array( 87309, 87312, 87351, 80282, 80283, 80306, 80313, 80314, 80315 );
				// 		foreach ( $skus as $sku ) {
				// 			$product_id = wc_get_product_id_by_sku( $sku );
				// 			if ( $product_id ) {
				// 				$product = wc_get_product( $product_id );
				// 				echo '<li><a href="'.$product->get_permalink().'" target="_blank">'.$product->get_title().'</a> ('.$product->get_meta('_shopplus_code').')</li>';
				// 			}
				// 		}
				// 	echo '</ul></li>';
				//
				// 	echo '<li>Nieuwe Dopper-drinkflessen:<ul style="margin-left: 2em; column-count: 2;">';
				// 		$skus = array( 12374, 12375, 12376, 12377, 12378, 12379, 12380, 12381 );
				// 		foreach ( $skus as $sku ) {
				// 			$product_id = wc_get_product_id_by_sku( $sku );
				// 			if ( $product_id ) {
				// 				$product = wc_get_product( $product_id );
				// 				echo '<li><a href="'.$product->get_permalink().'" target="_blank">'.$product->get_title().'</a> ('.$product->get_meta('_shopplus_code').')</li>';
				// 			}
				// 		}
				// 	echo '</ul><p>Omdat sommige van deze producten al langer dan 3 maanden bestelbaar zijn, krijgen ze niet allemaal een blauwe achtergrond in de voorraadlijst!</p></li>';
				// echo '</ul>';
				
				if ( does_home_delivery() ) {
					// Boodschappen voor winkels die thuislevering doen
				}
				
				if ( does_sendcloud_delivery() ) {
					// Boodschappen voor winkels die verzenden met SendCloud
				}
			}
		}
	}
	
	function oxfam_admin_notices_reports() {
		global $pagenow, $post_type;
		$screen = get_current_screen();
		
		if ( 'woocommerce_page_wc-reports' === $screen->base and ( empty( $_GET['tab'] ) or $_GET['tab'] === 'orders' ) ) {
			global $wpdb;
			$credit_date_timestamp = strtotime( '+1 weekday', strtotime('last day of this month') );
			$credit_month_timestamp = strtotime( '-1 month', strtotime('first day of this month') );
			$query = "SELECT * FROM {$wpdb->base_prefix}universal_coupons WHERE blog_id = ".get_current_blog_id()." AND DATE(credited) = '".date_i18n( 'Y-m-d', $credit_date_timestamp )."';";
			$results = $wpdb->get_results( $query );

			$sum = array_reduce( $results, function( $carry, $row ) {
				return $carry + $row->value;
			}, 0 );

			if ( $sum > 0 ) {
				echo '<div class="notice notice-success"><p>';
					if ( $credit_date_timestamp >= strtotime('today') ) {
						$tense = 'zal Oxfam Fair Trade '.wc_price( $sum ).' crediteren';
					} else {
						$tense = 'heeft Oxfam Fair Trade '.wc_price( $sum ).' gecrediteerd';
					}
					echo 'Op '.date_i18n( 'd/m/Y', $credit_date_timestamp ).' '.$tense.' voor de digitale cadeaubonnen die in de loop van de maand '.date_i18n( 'F Y', $credit_month_timestamp ).' gebruikt werden als betaalmiddel in jullie webshop.';
					if ( is_regional_webshop() ) {
						echo ' Elke cadeaubon wordt automatisch gecrediteerd aan het klantnummer van de winkel die de bestelling behandelde.';
					}
				echo '</p></div>';
			}
		}
	}

	function oxfam_network_admin_notices() {
		global $pagenow;
		$screen = get_current_screen();
		
		if ( 'admin.php' === $pagenow and 'toplevel_page_woonet-woocommerce-network' === $screen->base ) {
			$orders = array();
			$total = 0;
			$sites = get_sites( array( 'site__not_in' => get_site_option('oxfam_blocked_sites'), 'public' => 1 ) );
			
			foreach ( $sites as $site ) {
				switch_to_blog( $site->blog_id );
				$orders_with_coupon = get_number_of_times_coupon_was_used( 'koffiechoc22', '2022-01-01', '2022-02-28', true );
				
				foreach ( $orders_with_coupon as $wc_order ) {
					// Om dit correct te tellen, zouden we alle gratis repen in het order moeten opsnorren ...
					// $total += count( $orders_with_coupon );
					$output = '<a href="'.$wc_order->get_edit_order_url().'" target="_blank">'.$wc_order->get_order_number().'</a>: '.wc_price( $wc_order->get_total() ).' &mdash; '.$wc_order->get_billing_email();
					$orders[ $wc_order->get_order_number() ] = $output;
				}
				
				restore_current_blog();
			}
			
			ksort( $orders );
			
			echo '<div class="notice notice-success">';
				echo '<p>Tot nu toe werd de kortingsbon KOFFIECHOC22 al een of meerdere keren toegekend in '.count( $orders ).' verschillende bestellingen!</p>';
				echo '<ul style="margin-left: 2em; column-count: 2;">';
				
				foreach( $orders as $string ) {
					echo '<li>'.$string.'</li>';
				}
				
				echo '</ul>';
			echo '</div>';
		}
	}