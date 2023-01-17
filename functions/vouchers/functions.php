<?php
	
	if ( ! defined('ABSPATH') ) exit;
	
	############
	# VOUCHERS #
	############
	
	function ob2c_is_plausible_voucher_code( $code ) {
		if ( strlen( $code ) === 12 and strpos( $code, '-' ) === false ) {
			return true;
		} else {
			return false;
		}
	}
	
	function ob2c_is_valid_voucher_code( $code, $ignore_ip_limit = false ) {
		// Vermijd dat we ook autocoupons checken en zo geldige gebruikers blacklisten!
		if ( ob2c_is_plausible_voucher_code( $code ) ) {
			$tries = intval( get_site_transient( 'number_of_failed_attempts_ip_'.$_SERVER['REMOTE_ADDR'] ) );
			if ( ! $ignore_ip_limit and $tries > 10 ) {
				write_log( "Too many coupon attempts by ".$_SERVER['REMOTE_ADDR'].", code lookup temporarily blocked" );
				return WC_COUPON::E_WC_COUPON_NOT_EXIST;
			}
			
			global $wpdb;
			$coupon = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->base_prefix}universal_coupons WHERE `code` = %s", $code ) );
			
			if ( NULL !== $coupon ) {
				return $coupon;
			} else {
				set_site_transient( 'number_of_failed_attempts_ip_'.$_SERVER['REMOTE_ADDR'], $tries + 1, HOUR_IN_SECONDS );
			}
		}
	
		return WC_COUPON::E_WC_COUPON_NOT_EXIST;
	}
	
	// Creëer vouchers on-the-fly op basis van centrale MySQL-tabel
	// Dit wordt bij elke wijziging aan het winkelmandje opnieuw doorlopen!
	add_filter( 'woocommerce_get_shop_coupon_data', 'ob2c_load_digital_voucher_on_the_fly', 10, 3 );
	
	function ob2c_load_digital_voucher_on_the_fly( $bool, $code, $wc_coupon_class ) {
		$db_coupon = ob2c_is_valid_voucher_code( $code );
	
		if ( $db_coupon === WC_COUPON::E_WC_COUPON_NOT_EXIST ) {
			return false;
		} else {
			$data = array(
				'amount' => $db_coupon->value,
				'date_expires' => $db_coupon->expires,
				'discount_type' => 'fixed_cart',
				'description' => sprintf( 'Cadeaubon %s t.w.v. %d euro', $db_coupon->issuer, $db_coupon->value ),
				// Eventueel beperken tot OFT-producten?
				// 'product_ids' => array(),
				// Alle papieren geschenkencheques uitsluiten? WORDT PAS AUTOMATISCH TOEGEPAST WANNEER AAN DE VOORWAARDEN VOLDAAN WORDT ...
				// 'excluded_product_ids' => get_oxfam_cheques_ids_array(),
				'usage_limit' => 1,
			);
			if ( ! empty( $db_coupon->order ) ) {
				// De code bestaat maar kan niet meer gebruikt worden!
				// Door deze waarde in te stellen zal meteen een foutmelding getriggerd worden
				$data['usage_count'] = 1;
			}
			return $data;
		}
	
		return $bool;
	}
	
	// Tweak foutmeldingen
	add_filter( 'woocommerce_coupon_error', 'ob2c_coupon_error_message', 10, 3 );
	
	function ob2c_coupon_error_message( $message, $error_code, $coupon ) {
		// $coupon kan blijkbaar ook null zijn, fatal error opvangen
		if ( $coupon instanceof WC_Coupon and $coupon->get_virtual() ) {
			if ( $error_code == WC_COUPON::E_WC_COUPON_USAGE_LIMIT_REACHED ) {
				return sprintf( __( 'De cadeaubon met code %s werd al ingeruild!', 'oxfam-webshop' ), strtoupper( $coupon->get_code() ) );
			}
		}
	
		if ( $error_code == WC_COUPON::E_WC_COUPON_NOT_EXIST ) {
			if ( intval( get_site_transient( 'number_of_failed_attempts_ip_'.$_SERVER['REMOTE_ADDR'] ) ) > 10 ) {
				return __( 'Je ondernam te veel onsuccesvolle pogingen na elkaar. Probeer het over een uur opnieuw.', 'oxfam-webshop' );
			} else {
				return sprintf( __( 'De code %s kennen we helaas niet! Opgelet: papieren geschenkencheques kunnen niet via de webshop ingeruild worden.', 'oxfam-webshop' ), strtoupper( $coupon->get_code() ) );
			}
		}
	
		return $message;
	}
	
	// Toon duidelijke omschrijving i.p.v. kortingscode
	add_filter( 'woocommerce_cart_totals_coupon_label', 'ob2c_modify_digital_voucher_label', 10, 2 );
	
	function ob2c_modify_digital_voucher_label( $label, $coupon ) {
		if ( $coupon->get_virtual() ) {
			$label = $coupon->get_description().': '.strtoupper( $coupon->get_code() ).' <a class="dashicons dashicons-editor-help tooltip" title="Niet spreidbaar over meerdere aankopen. Eventuele restwaarde wordt niet terugbetaald. Niet toepasbaar op verzendkosten."></a>';
		}
	
		return $label;
	}
	
	// Check net vòòr we de betaling starten nog eens of de code wel geldig is
	add_action( 'woocommerce_before_pay_action', 'ob2c_revalidate_digital_voucher_before_payment', 10, 1 );
	
	function ob2c_revalidate_digital_voucher_before_payment( $order ) {
		foreach ( $order->get_coupons() as $coupon_item ) {
			// Negeer in deze stap de rate limiting per IP-adres
			$db_coupon = ob2c_is_valid_voucher_code( $coupon_item->get_code(), true );
			if ( is_object( $db_coupon ) ) {
				// Verhinder het betalen van een bestelling die een inmiddels reeds ingewisselde code bevat!
				$code = strtoupper( $coupon_item->get_code() );
				if ( ! empty( $db_coupon->order ) ) {
					$logger = wc_get_logger();
					$context = array( 'source' => 'Oxfam' );
					$logger->warning( 'Trying to re-use coupon '.$code.' in '.$order->get_order_number().', previously used in '.$db_coupon->order, $context );
					wc_add_notice( sprintf( __( 'Deze bestelling bevatte een digitale cadeaubon met code %1$s die reeds ingeruild werd in bestelling %2$s. We verwijderden deze cadeaubon en herberekenden het resterende te betalen bedrag.', 'oxfam-webshop' ), $code, $db_coupon->order ), 'error' );
					// Wis de voucher en sla het order op, zodat een nieuw betaaltotaal ontstaat
					$order->remove_coupon( $coupon_item->get_code() );
					$order->save();
				}
			}
		}
	}
	
	// Maak de code na succesvolle betaling onbruikbaar in de centrale database
	add_action( 'woocommerce_payment_complete', 'ob2c_invalidate_digital_voucher', 10, 1 );
	
	function ob2c_invalidate_digital_voucher( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( $order !== false ) {
			foreach ( $order->get_coupons() as $coupon_item ) {
				// Wees in deze stap niet te kieskeurig met validatie: deze actie is eenmalig én cruciaal voor het ongeldig maken van de voucher!
				// Negeer in deze stap de rate limiting per IP-adres
				$db_coupon = ob2c_is_valid_voucher_code( $coupon_item->get_code(), true );
				if ( is_object( $db_coupon ) ) {
					// Nogmaals checken of de code al niet ingewisseld werd!
					$code = strtoupper( $coupon_item->get_code() );
					if ( ! empty( $db_coupon->order ) ) {
						$logger = wc_get_logger();
						$context = array( 'source' => 'Oxfam' );
						$logger->critical( 'Coupon '.$code.' was already used in '.$db_coupon->order.', should not be used in '.$order->get_order_number(), $context );
						send_automated_mail_to_helpdesk( 'Cadeaubon '.$code.' werd reeds gebruikt in '.$db_coupon->order, '<p>Bekijk <u>zo snel mogelijk</u> de bestelling <a href="'.$order->get_edit_order_url().'">in de back-end</a>. Hier is iets niet pluis!</p>' );
					} else {
						// Ongeldig maken in de centrale database
						global $wpdb;
						$rows_updated = $wpdb->update(
							$wpdb->base_prefix.'universal_coupons',
							array( 'order' => $order->get_order_number(), 'used' => date_i18n('Y-m-d H:i:s'), 'blog_id' => get_current_blog_id() ),
							array( 'code' => $code )
						);
	
						if ( $rows_updated === 1 ) {
							// Converteer korting naar pseudo betaalmethode voor correcte omzetrapporten en verwerking van BTW
							$fee = new WC_Order_Item_Fee();
							$coupon_data_array = $coupon_item->get_meta('coupon_data');
							$fee->set_name( $coupon_data_array['description'].': '.$code );
							$fee->set_amount(0);
							$fee->set_total(0);
							// Opgelet: op negatieve kosten wordt sowieso automatisch BTW toegevoegd, ondanks deze instelling
							// Zie https://github.com/woocommerce/woocommerce/issues/16528#issuecomment-354738929
							$fee->set_tax_status('none');
							$fee->update_meta_data( 'voucher_code', $code );
							$fee->update_meta_data( 'voucher_value', $db_coupon->value );
							// Bewaar het effectieve bedrag dat betaald werd via de voucher (kan minder zijn dan de totale waarde!) for future reference
							// Bij bestellingen die VOLLEDIG met vouchers betaald werden wordt toch de waarde van de volledige voucher doorgegeven?
							$fee->update_meta_data( 'voucher_amount', $coupon_item->get_discount() + $coupon_item->get_discount_tax() );
							$fee->save();
	
							if ( $order->add_item( $fee ) !== false ) {
								// Verwijder de kortingscode volledig van het order
								// Gebruik bewust niet de uppercase versie maar de originele waarde!
								$order->remove_coupon( $coupon_item->get_code() );
							}
							// Lokt dit een herberekening van alle kosten uit die het BTW-tarief op betalende verzending verkeerdelijk altijd op 21% zet?
							// Zorgt er ook voor dat kortingsbonnen die slechts per n-de item toegepast werden toch op elk item toegepast worden indien de bestelling VOLLEDIG met vouchers betaald werd?
							$order->save();
						} else {
							send_automated_mail_to_helpdesk( 'Cadeaubon '.$code.' kon niet als gebruikt gemarkeerd worden in de database', '<p>Bekijk <u>zo snel mogelijk</u> de bestelling <a href="'.$order->get_edit_order_url().'">in de back-end</a>. Hier is iets niet pluis!</p>' );
						}
					}
				}
			}
		}
	}
	
	// Vermeld het bedrag dat betaald werd via cadeaubonnen in de back-end (enige beschikbare actie in die buurt ...)
	add_action( 'woocommerce_admin_order_totals_after_total', 'ob2c_list_voucher_payments', 10, 1 );
	
	function ob2c_list_voucher_payments( $order_id ) {
		$order = wc_get_order( $order_id );
		$voucher_total = ob2c_get_total_voucher_amount( $order );
		if ( $voucher_total > 0 ) {
			?>
			<tr>
				<td>
					<span class="description"><?php echo 'waarvan '.wc_price( $voucher_total ).' via digitale cadeaubon'; ?></span>
				</td>
			</tr>
			<?php
		}
	}
	
	// Vermeld het bedrag dat betaald werd via cadeaubonnen in de front-end
	add_filter( 'woocommerce_get_order_item_totals', 'ob2c_add_voucher_subtotal', 10, 3 );
	
	function ob2c_add_voucher_subtotal( $total_rows, $order, $tax_display ) {
		$voucher_total = ob2c_get_total_voucher_amount( $order );
		if ( $voucher_total > 0 ) {
			$total_rows['vouchers'] = array( 'label' => __( 'waarvan betaald via digitale cadeaubon:', 'oxfam-webshop' ), 'value' => wc_price( $voucher_total ) );
		}
	
		return $total_rows;
	}
	
	// Verwijder vouchers uit onafgewerkte bestellingen die uiteindelijk geannuleerd worden (oogt netter) NOG NIET GETEST
	// add_action( 'woocommerce_order_status_pending_to_cancelled', 'ob2c_remove_vouchers_on_cancelled_orders', 1, 2 );
	
	function ob2c_remove_vouchers_on_cancelled_orders( $order_id, $order ) {
		foreach ( $order->get_coupons() as $coupon_item ) {
			// Negeer in deze stap de rate limiting per IP-adres
			$db_coupon = ob2c_is_valid_voucher_code( $coupon_item->get_code(), true );
			if ( is_object( $db_coupon ) ) {
				send_automated_mail_to_helpdesk( 'Cadeaubon '.strtoupper( $coupon_item->get_code() ).' werd verwijderd uit onafgewerkte bestelling <a href="'.$order->get_edit_order_url().'">'.$order->get_order_number().'</a>.</p>' );
				$order->remove_coupon( $coupon_item->get_code() );
			}
		}
	}
	
	function ob2c_bulk_create_digital_vouchers( $issuer = 'Cera', $expires = '2024-03-01', $value = 30, $number = 1000 ) {
		global $wpdb;
		$created_codes = array();
	
		if ( current_user_can('update_core') ) {
			for ( $i = 0; $i < $number; $i++ ) {
				$data = array(
					'code' => ob2c_generate_new_voucher_code(),
					'issuer' => $issuer,
					'expires' => $expires,
					'value' => $value,
				);
	
				if ( $wpdb->insert( $wpdb->base_prefix.'universal_coupons', $data ) === 1 ) {
					$created_codes[] = $data['code'];
					file_put_contents( ABSPATH . '/../oxfam-digital-vouchers-'.$value.'-EUR-valid-'.$expires.'.csv', $data['code']."\n", FILE_APPEND );
				} else {
					echo "Error inserting new code row<br/>";
				}
			}
		} else {
			echo 'No permission to create vouchers, please log in<br/>';
		}
	
		echo implode( '<br/>', $created_codes );
	}
	
	function ob2c_generate_new_voucher_code() {
		// O weglaten om verwarring met 0 te vermijden
		$characters = '0123456789ABCDEFGHIJKLMNPQRSTUVWXYZ';
		$characters_length = strlen( $characters );
		$random_string = '';
		for ( $i = 0; $i < 12; $i++ ) {
			$random_string .= $characters[ rand( 0, $characters_length - 1 ) ];
		}
	
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->base_prefix}universal_coupons WHERE code = '%s'", $random_string ) );
		if ( null !== $row ) {
			// De code bestond al, begin opnieuw
			echo "Coupon code ".$random_string." already exists, retry ...<br/>";
			return ob2c_generate_new_voucher_code();
		} else {
			return $random_string;
		}
	}