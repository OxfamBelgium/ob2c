<?php
	if ( ! defined('ABSPATH') ) exit;
?>

<div class="wrap oxfam-admin-styling">
	<h1>Ingeruilde digicheques</h1>

	<p>Hieronder vind je een overzicht van alle vouchers (uitgegeven door Gezinsbond, Cera, CM, ...) die de voorbije 4 maanden ingeruild werden in deze webshop.<br/>
	De weergave is gegroepeerd per kredietnota, en vervolgens per bestelling. De reële crediteringsdatum kan enkele dagen afwijken van de geafficheerde datum, dit hangt af van de drukte op onze Klantendienst!</p>
	
	<?php
		if ( in_array( get_current_blog_id(), array( 14, 48, 51, 67, 76 ) ) and date_i18n('Y-m-d') < '2023-01-15' ) {
			if ( get_current_blog_id() === 51 ) {
				$aantal_bonnen = '2 bonnen';
			} else {
				$aantal_bonnen = '1 bon';
			}
			
			echo '<p><b>Door een fout in de exportlogica werden digicheques uit laattijdig afgeronde bestellingen (door bv. een stockbreuk bij OFTL) achteraf niet meer gecrediteerd. We hebben de export gecorrigeerd zodat dit in de toekomst niet meer kan gebeuren. Jullie behoren tot de 5 webshops waar '.$aantal_bonnen.' dit voorjaar niet correct gecrediteerd werd. Deze digicheques duiken inmiddels wél op in onderstaand overzicht en zullen rond 30 december 2022 gecrediteerd worden. Onze excuses!</b></p>';
			
			$start_date = date( 'Y-m-d', strtotime('first day of this month -12 months') );
		} else {
			$start_date = date( 'Y-m-d', strtotime('first day of this month -4 months') );
		}
	?>
	
	<p>&nbsp;</p>

	<div id="oxfam-vouchers">
		<?php
			global $wpdb;
			$end_date = date( 'Y-m-d', strtotime('last day of this month') );
			$query = "SELECT * FROM {$wpdb->base_prefix}universal_coupons WHERE `blog_id` = %d AND DATE(`used`) BETWEEN '%s' AND '%s' ORDER BY `used` ASC";
			$rows = $wpdb->get_results( $wpdb->prepare( $query, get_current_blog_id(), $start_date, $end_date ) );
			
			$credit_dates = array_unique( array_column( $rows, 'credited' ) );
			rsort( $credit_dates );
			if ( in_array( '0000-00-00', $credit_dates ) ) {
				// Blok met nog niet gecrediteerde vouchers bovenaan plaatsen
				array_unshift( $credit_dates, $last = array_pop( $credit_dates ) );
			}
			
			foreach ( $credit_dates as $credit_date ) {
				$credit_date_formatted = date_i18n( 'j F Y', strtotime( $credit_date ) );
				
				// Tellers op 0 zetten
				$credit_refs = array(
					// Bonnen voor 2026
					'08935' => array( 'issuer' => 'Gezinsbond', 'value' => 50, 'count' => 0 ),
					'08936' => array( 'issuer' => 'Gezinsbond', 'value' => 25, 'count' => 0 ),
				);
				
				if ( $credit_date === '0000-00-00' ) {
					echo '<h2>Nog te verwerken crediteringen</h2>';
					echo '<p>Deze codes zijn nog niet verwerkt op het Nationaal Secretariaat. Als de bestelling al afgerond is, hoef je zelf niets meer te doen.<br/>Hou er rekening mee dat we een wachtperiode inlassen van min. 1 maand na de besteldatum vòòr we overgaan tot creditering, om ruimte te laten voor retours en correcties.</p>';
				} else {
					echo '<h2>Kredietnota van '.$credit_date_formatted.'</h2>';
				}
				
				// Externe variabelen zijn standaard niet beschikbaar binnen callback, gebruik 'use'!
				$all_codes = array_filter( $rows, function($row) use ($credit_date) {
					return ( $row->credited == $credit_date );
				});
				
				$all_order_numbers = array_unique( array_column( $all_codes, 'order' ) );
				foreach ( $all_order_numbers as $order_number ) {
					$all_codes_for_order_number = array_filter( $all_codes, function($row) use ($order_number) {
						return ( $row->order == $order_number );
					});
					
					$args = array(
						'type' => 'shop_order',
						'order_number' => $order_number,
						'limit' => -1,
					);
					$orders = wc_get_orders( $args );
					
					echo '<div id="'.$order_number.'" class="row">';
					
					if ( $order_number === 'OFFLINE' or count( $orders ) === 1 ) {
						$warnings = array();
						$messages = array();
						$shop_to_credit = false;
						
						if ( $order_number !== 'OFFLINE' ) {
							$order = reset( $orders );
							
							if ( $order->get_status() !== 'completed' ) {
								$warnings[] = 'is niet afgerond, kan nog niet gecrediteerd worden';
							} else {
								if ( is_regional_webshop() ) {
									$shop_to_credit = $order->get_meta('claimed_by');
									
									if ( strlen( $shop_to_credit ) < 1 ) {
										$warnings[] = 'is niet geclaimd door winkel, kan nog niet gecrediteerd worden';
									}
								}
							}
						} else {
							$order = false;
						}
						
						echo '<div class="column first-column">';
							if ( $order ) {
								echo '<a href="'.$order->get_edit_order_url().'">'.$order_number.'</a> ('.$order->get_date_created()->date_i18n('d/m/Y').')';
								
								if ( $order ) {
									$refunds = $order->get_refunds();
									if ( count( $refunds ) > 0 ) {
										$refund_amount = 0.0;
										foreach ( $refunds as $refund ) {
											$refund_amount += $refund->get_amount();
										}
										if ( $refund_amount > ( $order->get_total() - ob2c_get_total_voucher_amount( $order ) ) ) {
											$text = 'bevat een terugbetaling t.w.v. '.wc_price( $refund_amount ).' die groter is dan het restbedrag dat niet met vouchers betaald werd!';
											if ( $credit_date === '0000-00-00' ) {
												$warnings[] = $text;
											} else {
												$messages[] = $text;
											}
										}
									}
								}
							} else {
								echo $order_number;
							}
						echo '</div>';
						
						echo '<div class="column second-column">';
							echo '<ul>';
							
							foreach ( $all_codes_for_order_number as $row ) {
								echo '<li>'.$row->code.' ('.$row->issuer.') t.w.v. '.wc_price( $row->value );
								
								if ( count( $warnings ) === 0 ) {
									echo ': ';
									
									if ( $row->credited > date('Y-m-d') ) {
										echo 'ingepland voor creditering';
									} else {
										if ( $row->credited === '0000-00-00' ) {
											echo 'creditering nog in te plannen';
										} else {
											echo 'gecrediteerd';
										}
									}
									
									$filtered_refs = array_filter( $credit_refs, function($ref) use ($row) {
										// Eventueel $row->expires == $ref['expires'] toevoegen als er bonnen met zelfde waarde maar andere vervaldatum bij komen
										return ( $row->value == $ref['value'] );
									});
									$credit_ref = key( $filtered_refs );
									
									$credit_refs[ $credit_ref ]['count']++;
									echo ' (art.nr. '.$credit_ref.')';
									
									if ( $shop_to_credit ) {
										echo ' aan OWW '.ucfirst( $shop_to_credit );
									}
								}
								
								echo '</li>';
							}
						
							if ( count( $warnings ) > 0 ) {
								echo '<li class="warning">'.implode( ', ', $warnings ).'</li>';
							}
							if ( count( $messages ) > 0 ) {
								echo '<li class="message">'.implode( ', ', $messages ).'</li>';
							}
							
							echo '</ul>';
						echo '</div>';
					}
					
					echo '</div>';
				}
				
				if ( $credit_date !== '0000-00-00' ) {
					$sum = 0;
					$output = array();
					foreach ( $credit_refs as $credit_ref => $credit_ref_info ) {
						if ( $credit_ref_info['count'] > 0 ) {
							$sum += $credit_ref_info['count'] * $credit_ref_info['value'];
							$output[] = $credit_ref_info['count'].'x '.$credit_ref.' à '.wc_price( $credit_ref_info['value'] );
						}
					}
					
					if ( $credit_date > date('Y-m-d') ) {
						$prefix = 'Te crediteren';
					} else {
						$prefix = 'Gecrediteerd';
					}
					echo '<h4>'.$prefix.': '.implode( ' + ', $output ).' = '.wc_price( $sum ).'</h4>';
				}
				echo '<p>&nbsp;</p>';
			}
		?>
	</div>
</div>