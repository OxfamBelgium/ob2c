<?php
	
	if ( ! defined('ABSPATH') ) exit;
	
	// Registreer aantal gratis capsules over alle webshops heen
	// add_filter( 'woocommerce_coupon_get_usage_count', 'get_sitewide_coupon_usage', 10, 2 );
	// add_action( 'woocommerce_increase_coupon_usage_count', 'increase_coupon_usage_count_sitewide', 10, 3 );
	// add_action( 'woocommerce_decrease_coupon_usage_count', 'decrease_coupon_usage_count_sitewide', 10, 3 );
	
	function get_sitewide_coupon_usage( $usage_count, $coupon ) {
		if ( $coupon->get_code() === 'faircaps21' ) {
			return get_site_option( 'free_capsules_given_2021', 0 );
		} else {
			return $usage_count;
		}
	}
	
	function increase_coupon_usage_count_sitewide( $coupon, $new_count, $used_by ) {
		if ( $coupon->get_code() === 'faircaps21' ) {
			// Gebruik bewust niét $new_count want dat bevat enkel het gebruik in deze site!
			update_site_option( 'free_capsules_given_2021', get_site_option( 'free_capsules_given_2021', 0 ) + 1 );
			write_log( "Aantal keer '".$coupon->get_code()."' gebruikt (na toename): ".get_site_option( 'free_capsules_given_2021', 0 ) );
		}
	}
	
	function decrease_coupon_usage_count_sitewide( $coupon, $new_count, $used_by ) {
		if ( $coupon->get_code() === 'faircaps21' ) {
			// Wordt correct doorlopen bij het annuleren / volledig terugbetalen van een order
			// Gebruik bewust niét $new_count want dat bevat enkel het gebruik in deze site!
			update_site_option( 'free_capsules_given_2021', get_site_option( 'free_capsules_given_2021', 0 ) - 1 );
			write_log( "Aantal keer '".$coupon->get_code()."' gebruikt (na afname): ".get_site_option( 'free_capsules_given_2021', 0 ) );
		}
	}
	
	function get_number_of_times_coupon_was_used( $coupon_code, $start_date = '2022-10-01', $end_date = '2022-10-31', $return_orders = false ) {
		global $wpdb;
		$total_count = 0;
		$orders = array();
		
		$query = "SELECT p.ID AS order_id FROM {$wpdb->prefix}posts AS p INNER JOIN {$wpdb->prefix}woocommerce_order_items AS woi ON p.ID = woi.order_id WHERE p.post_type = 'shop_order' AND p.post_status IN ('" . implode( "','", array( 'wc-processing', 'wc-claimed', 'wc-completed' ) ) . "') AND woi.order_item_type = 'coupon' AND woi.order_item_name = '" . $coupon_code . "' AND DATE(p.post_date) BETWEEN '" . $start_date . "' AND '" . $end_date . "';";
		$rows = $wpdb->get_results( $query );
		
		foreach ( $rows as $key => $row ) {
			$order = wc_get_order( $row->order_id );
			if ( $order !== false ) {
				$orders[] = $order;
				foreach ( $order->get_coupons() as $coupon ) {
					if ( $coupon->get_code() == $coupon_code ) {
						$total_count += $coupon->get_quantity();
					}
				}
			}
		}
		
		if ( $return_orders ) {
			return $orders;
		} else {
			return $total_count;
		}
	}
	
	// Vreemd genoeg blijft .wjecf-fragment-checkout-select-free-product op de afrekenpagina leeg, dus redirect naar het winkelmandje indien de code daar toegevoegd werd
	// add_action( 'woocommerce_applied_coupon', 'redirect_to_cart_to_choose_version', 10, 1 );
	
	function redirect_to_cart_to_choose_version( $code ) {
		if ( $code === 'koffiechoc22' ) {
			if ( ! is_cart() ) {
				// wp_safe_redirect() lokt enkel een redirect in de pop-up uit, gebruik JavaScript om de volledige pagina te refreshen!
				?>
				<script type="text/javascript">
					window.location.href = '<?php echo wc_get_cart_url() ?>#wjecf-select-free-products';
				</script>
				<?php
			}
		}
	}
	
	// Pas winkelmandkorting n.a.v. Week van de Fair Trade 2021 toe op het uiteindelijk te betalen bedrag i.p.v. het subtotaal
	// Deze filter wordt enkel doorlopen bij autocoupons!
	// add_filter( 'wjecf_coupon_can_be_applied', 'apply_coupon_on_total_not_subtotal', 1000, 2 );
	
	function apply_coupon_on_total_not_subtotal( $can_be_applied, $coupon ) {
		if ( $coupon->get_code() === '202110-wvdft' and date('Y-m-d') < $coupon->get_date_expires()->date_i18n('Y-m-d') ) {
			$melkchocolade = wc_get_product( wc_get_product_id_by_sku('24300') );
			if ( $melkchocolade !== false and $melkchocolade->get_stock_status() !== 'instock' ) {
				// Pas de korting niet toe als het gratis product niet op voorraad is
				return false;
			}
			
			// Vergelijk met het subtotaal NA kortingen m.u.v. digitale vouchers (inclusief BTW, exclusief verzendkosten)
			// Of toch gewoon 'ignore_discounts' inschakelen op alle levermethodes?
			$totals = WC()->cart->get_totals();
			if ( current_user_can('update_core') ) {
				write_log( "Basisbedrag voor toekennen gratis tablet chocolade: ". ( $totals['cart_contents_total'] + $totals['cart_contents_tax'] + ob2c_get_total_voucher_amount() - ob2c_get_total_empties_amount() ) );
			}
			// Eventueel $coupon->get_meta('_wjecf_min_matching_product_subtotal') gebruiken indien beperkt tot bepaalde producten
			if ( ( $totals['cart_contents_total'] + $totals['cart_contents_tax'] + ob2c_get_total_voucher_amount() - ob2c_get_total_empties_amount() ) >= floatval( $coupon->get_minimum_amount() ) ) {
				// Pas op met expliciet op true zetten: dit zal iedere keer een foutmelding genereren boven het winkelmandje als de coupon om een andere reden (bv. usage count) ongeldig is!
				// Deze logica was duidelijk niet sluitend voor Cera-bestellingen > 30 euro ...
				if ( $can_be_applied ) {
					return true;
				}
			}
			
			return false;
		}
		
		return $can_be_applied;
	}
	
	// Schakel kortingsbon met een gratis product uit als de webshop geen voorraad heeft
	add_action( 'wjecf_assert_coupon_is_valid', 'check_if_free_products_are_on_stock', 1000, 2 );
	
	function check_if_free_products_are_on_stock( $coupon, $wc_discounts  ) {
		if ( in_array( $coupon->get_code(), array( '202509-chips' ) ) and date_i18n('Y-m-d') < $coupon->get_date_expires()->date_i18n('Y-m-d') ) {
			$yucachips = wc_get_product( wc_get_product_id_by_sku( '25409' ) );
			if ( $yucachips !== false and $yucachips->get_stock_status() !== 'instock' ) {
				throw new Exception( __( 'Deze webshop heeft helaas geen yucachips op voorraad. Gelieve een ander afhaalpunt te kiezen.', 'oxfam-webshop' ), 79106 );
			}
		}
	}
	
	// Probeer wijnduo's enkel toe te passen op gelijke paren (dus niet 3+1, 5+1, 5+3, ...)
	// add_filter( 'woocommerce_coupon_get_apply_quantity', 'limit_coupon_to_equal_pairs', 100, 4 );
	
	function limit_coupon_to_equal_pairs( $apply_quantity, $item, $coupon, $wc_discounts ) {
		if ( is_admin() ) {
			return $apply_quantity;
		}
		
		// Schuimwijnen hebben dezelfde prijs, dus zij mogen wel gemixt worden
		if ( stristr( $coupon->get_code(), 'wijnduo' ) !== false and stristr( $coupon->get_code(), 'schuimwijn' ) === false ) {
			$this_quantity = 0;
			$other_quantity = 0;
			$old_apply_quantity = $apply_quantity;
			
			// Check of beide vereiste producten in gelijke hoeveelheid aanwezig zijn
			foreach ( WC()->cart->get_cart() as $cart_item_key => $values ) {
				$product_in_cart = $values['data'];
				
				// Cart item maakt deel uit van de promotie
				if ( in_array( $product_in_cart->get_id(), $coupon->get_product_ids() ) ) {
					if ( $product_in_cart->get_id() === $item->product->get_id() ) {
						$this_quantity = intval( $values['quantity'] );
						$this_sku = intval( $product_in_cart->get_sku() );
					} else {
						$other_quantity = intval( $values['quantity'] );
						$other_sku = intval( $product_in_cart->get_sku() );
					}
					
					if ( $other_quantity !== 0 and $this_quantity !== 0 ) {
						// We passen de korting VOLLEDIG toe op het kleinste artikelnummer van het duo
						if ( $this_sku < $other_sku ) {
							// Niet meer delen door twee!
							$apply_quantity = min( $this_quantity, $other_quantity );
						} else {
							$apply_quantity = 0;
						}
						
						// We hebben beide producten gevonden en kunnen afsluiten
						// write_log( "APPLY QUANTITY FOR ".$coupon->get_code()." ON SKU ".$item->product->get_sku().": ".$old_apply_quantity." => ".$apply_quantity );
						break;
					}
				}
			}
		}
		
		return $apply_quantity;
	}