<?php
	if ( ! defined('ABSPATH') ) exit;

	use PhpOffice\PhpSpreadsheet\Spreadsheet;
	use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
	require_once WP_PLUGIN_DIR.'/phpspreadsheet/autoload.php';
?>

<div class="wrap">
	<h1>Ingeruilde digitale cadeaubonnen</h1>

	<p>Hieronder vind je een overzicht van de digitale cadeaubonnen die in de loop van <u>vorige maand ingeruild</u> werden én ondertussen <u>nog niet gecrediteerd</u> zijn.</p>

	<p>Open op het einde van de maand (ten vroegste op de 20ste) deze pagina, en controleer de eventuele waarschuwingen. Terugbetalingen die iets groter zijn dan het restbedrag dat de klant zelf betaalde, bedekken we met de mantel der liefde. Alleen als we zien dat het een erg groot bedrag was en/of zeer vaak gebeurt, contacteren we de winkel in kwestie even. (Zoals vermeld in de bevestigingsmail naar de webshopmailbox moet bij grote terugbetalingen de volledige bestelling geannuleerd worden en de digicheque gereactiveerd worden in de database. Dit bleek tot nu toe erg zeldzaam te zijn.)</p>
	
	<p>Bestellingen van vorige maand die nog niet afgerond zijn, kunnen indien nodig open blijven staan. (Meestal gaat het om een vergetelheid van de winkel, maar soms moet er effectief gewacht worden op voorraad.) De digicheque zal nog niet opgenomen worden in de Excel-lijst voor creditering, maar volgende maand automatisch opnieuw opduiken. Van zodra het order afgerond is, zal het in de eerstvolgende crediteringsronde meegenomen worden.</p>
	
	<p>Als alles in orde is, download je de Excel-file. Per terugbetalingsreferentie zie je een werkblad staan met de juiste aantallen per winkel. Zet deze gegevens over naar de Access-file voor creditering op de eerste dag van de volgende maand. <b>Vergeet de creditering tot slot niet te bevestigen, zodat de Excel-file gearchiveerd wordt én alles correct geregistreerd wordt in de webshopdatabase (geen dubbele crediteringen).</b></p>
	
	<p>Er zit dus steeds een veiligheidsmarge van minstens één maand tussen het inruilen en het crediteren van een cadeaubon, zodat de bestelling rustig afgerond kan worden en eventuele problemen reeds afgehandeld zijn. In geval van nood kan een developer het datumbereik aanpassen en/of handmatige correcties doorvoeren in de database. Ook indien er nieuwe bonnen in omloop gebracht worden dient een developer de juiste Odisy-referenties toe te voegen aan het bronbestand van deze pagina (zie <i>/functions/vouchers/</i>).</p>

	<?php
		$start_date = date_i18n( 'Y-m-d', strtotime('first day of previous month') );
		$end_date = date_i18n( 'Y-m-d', strtotime('last day of previous month') );
		echo '<p><b>Startdatum</b>: '.$start_date.'<br/><b>Einddatum</b>: '.$end_date.'</p>';
		$voucher_ids = array();
		
		// Haal standaard data van voorbije maand op
		$distribution = get_credit_report_used_vouchers( $start_date, $end_date );
		
		// Haal specifieke data van bepaalde periode op
		// $distribution = get_credit_report_used_vouchers( '2021-10-25', '2021-11-30', array( '08918' => array( 'issuer' => false, 'value' => 'faircaps21' ) ) );
		
		if ( count( $distribution ) > 0 ) {
			// Toon resultaat op scherm
			// var_dump_pre( $distribution );
			
			// Schrijf resultaat weg naar Excel
			export_to_excel( $distribution );
		} else {
			echo '<p><b><span style="color: red">Er valt deze maand niets (meer) te crediteren!</span></b></p>';
		}
		
		output_latest_exports( $start_date, $end_date );
		
		add_action( 'admin_footer', 'close_voucher_export' );
	?>

	<div class="output"></div>

	<?php
		function get_credit_report_used_vouchers( $start_date = '2021-05-01', $end_date = '2021-05-31', $credit_refs = false ) {
			$distribution = array();
			
			if ( $credit_refs === false ) {
				// Gelijkaardige array komt ook voor get-local-report.php, beter opslaan in optie?
				$credit_refs = array(
					// Terugbetalingsreferenties definitief afgesloten voor creditering
					// '08917' => array( 'issuer' => 'Cera', 'value' => 30, 'expires' => '2023-01-01' ),
					// '08924' => array( 'issuer' => 'CM', 'value' => 10, 'expires' => '2023-01-01' ),
					// '08937' => array( 'issuer' => 'Cera', 'value' => 30, 'expires' => '2024-03-01' ),
					// '08953' => array( 'issuer' => 'Gezinsbond', 'value' => 50, 'expires' => '2025-01-01' ),
					// '08954' => array( 'issuer' => 'Gezinsbond', 'value' => 25, 'expires' => '2025-01-01' ),
					// '08955' => array( 'issuer' => 'Cera', 'value' => 30, 'expires' => '2025-03-01' ),
					'08899' => array( 'issuer' => 'Gezinsbond', 'value' => 50, 'expires' => '2026-01-01' ),
					'08900' => array( 'issuer' => 'Gezinsbond', 'value' => 25, 'expires' => '2026-01-01' ),
					'08935' => array( 'issuer' => 'Gezinsbond', 'value' => 50, 'expires' => '2027-01-01' ),
					'08936' => array( 'issuer' => 'Gezinsbond', 'value' => 25, 'expires' => '2027-01-01' ),
					// Nieuwe acties hier aanvullen met de parameters issuer / value / expires, zoals ze aangemaakt zijn in de MySQL-tabel
					// Op die manier kunnen de bonnen in het rapport aan de juiste crediteringscode gelinkt worden
					// Odisy-artikelnummer te ontvangen van Aike Detelder
				);
			}
			
			foreach ( $credit_refs as $credit_ref => $credit_params ) {
				$rows = get_number_of_times_voucher_was_used( $credit_refs[ $credit_ref ]['issuer'], $credit_refs[ $credit_ref ]['value'], $start_date, $end_date, $credit_refs[ $credit_ref ]['expires'] );
				echo '<p><b>Totaalbedrag te crediteren onder referentie '.$credit_ref.': '.wc_price( array_sum( $rows ) * $credit_refs[ $credit_ref ]['value'] ).'</b></p>';
				
				if ( count( $rows ) > 0 ) {
					$distribution[ $credit_ref ] = $rows;
				}
			}
			
			return $distribution;
		}
		
		function get_number_of_times_voucher_was_used( $issuer, $value, $start_date, $end_date, $expires = false ) {
			global $wpdb, $voucher_ids;
			$repartition = array();
			$warnings = array();
			
			if ( $issuer === false ) {
				
				// Licht andere logica voor reguliere kortingsbonnen die niet in universele tabel zitten
				$sites = get_sites( array( 'site__not_in' => get_site_option('oxfam_blocked_sites'), 'public' => 1 ) );
				
				foreach ( $sites as $site ) {
					switch_to_blog( $site->blog_id );
					foreach ( get_number_of_times_coupon_was_used( $value, $start_date, $end_date, true ) as $order ) {
						if ( $order->get_status() !== 'completed' ) {
							$warnings[ $order->get_order_number() ] = 'Bestelling <a href="'.$order->get_edit_order_url().'" target="_blank">'.$order->get_order_number().'</a> is nog niet afgerond, toch opgenomen in export';
						}
						
						if ( is_regional_webshop() ) {
							$blog_path = $order->get_meta('claimed_by');
						} else {
							$current_blog = get_site();
							// Wis de bewoording 'regio' bij pseudo-regiowebshops, om verwarring bij het ingeven in Access te vermijden
							$blog_path = str_replace( array( '/regio', '/' ), '', $current_blog->path );
						}
						
						if ( strlen( $blog_path ) < 1 ) {
							$warnings[ $order->get_order_number() ] = 'Bestelling <a href="'.$order->get_edit_order_url().'" target="_blank">'.$order->get_order_number().'</a> is niet toegekend aan een winkel, niet opgenomen in export';
							continue;
						}
						
						if ( ! array_key_exists( $blog_path, $repartition ) ) {
							$repartition[ $blog_path ] = 1;
						} else {
							$repartition[ $blog_path ] += 1;
						}
						
						// Door gebrek aan $voucher_ids werkt de 'Bevestig creditering'-knop niet voor deze orders, dus voeg eventueel meteen toe
						// $order->add_order_note( 'Kortingsbon '.strtoupper( $value ).' werd op 1 december 2021 door het NS gecrediteerd aan OWW '.ucfirst( $blog_path ).'.', 0, false );
						// write_log( "Crediteringsnota toegevoegd aan ".$order->get_order_number() );
					}
					restore_current_blog();
				}
				
			} else {
				
				// Vereis een lege 'credited'-datum zodat we verhinderen dat vouchers twee keer in een opgeslagen export kunnen opduiken
				// Géén startdatum doorgeven, zodat ook bonnen in laattijdig afgewerkte bestellingen nog correct gecrediteerd kunnen worden in een latere maand
				$query = "SELECT * FROM {$wpdb->base_prefix}universal_coupons WHERE `issuer` = '%s' AND value = %d AND DATE(`used`) BETWEEN '2001-01-01' AND CONCAT('%s', ' 23:59:59') AND DATE(`credited`) < '2001-01-01' AND `expires` = '%s'";
				$rows = $wpdb->get_results( $wpdb->prepare( $query, $issuer, $value, $end_date, $expires ) );
				
				foreach ( $rows as $key => $row ) {
					switch_to_blog( $row->blog_id );
					
					$args = array(
						'type' => 'shop_order',
						'order_number' => $row->order,
						'limit' => -1,
					);
					$orders = wc_get_orders( $args );
					
					if ( $row->order !== 'OFFLINE' and count( $orders ) === 0 ) {
						$warnings[ $row->order ] = 'Bestelling '.$row->order.' niet gevonden, voucher '.$row->code.' niet opgenomen in export';
					} elseif ( count( $orders ) > 1 ) {
						$warnings[ $row->order ] = 'Meerdere bestellingen gevonden voor '.$row->order.', voucher '.$row->code.' niet opgenomen in export';
					} else {
						$current_blog = get_site();
						
						// Soms worden codes per ongeluk toch fysiek geaccepteerd in een winkel
						// In dat geval maken we de code handmatig ongeldig in de MySQL-tabel en gebruiken we 'OFFLINE' als ordernummer
						if ( $row->order === 'OFFLINE' ) {
							$voucher_ids[] = $row->id;
							if ( intval( $row->blog_id ) > 0 ) {
								// Wis de bewoording 'regio' bij pseudo-regiowebshops, om verwarring bij het ingeven in Access te vermijden
								$blog_path = str_replace( array( '/regio', '/' ), '', $current_blog->path );
							} else {
								// Indien de winkel zelf geen webshop heeft kunnen we ze niet linken aan de juiste winkelnaam o.b.v. webshop-ID!
								// In dat geval zal de code onder 'UNKNOWN' opduiken in de Excel en moet de juiste winkelna(a)m(en) handmatig ingevuld worden na het exporteren
								$blog_path = 'UNKNOWN';
							}
						} else {
							$order = reset( $orders );
							if ( $order->get_status() !== 'completed' ) {
								$warnings[ $row->order ] = 'Bestelling <a href="'.$order->get_edit_order_url().'" target="_blank">'.$row->order.'</a> is nog niet afgerond, voucher '.$row->code.' niet opgenomen in export';
								restore_current_blog();
								continue;
							}
							
							if ( is_regional_webshop() ) {
								$blog_path = $order->get_meta('claimed_by');
							} else {
								// Wis de bewoording 'regio' bij pseudo-regiowebshops, om verwarring bij het ingeven in Access te vermijden
								$blog_path = str_replace( array( '/regio', '/' ), '', $current_blog->path );
							}
							
							if ( strlen( $blog_path ) < 1 ) {
								$warnings[ $row->order ] = 'Bestelling <a href="'.$order->get_edit_order_url().'" target="_blank">'.$row->order.'</a> is niet toegekend aan een winkel, voucher '.$row->code.' niet opgenomen in export';
								restore_current_blog();
								continue;
							}
							
							$voucher_ids[] = $row->id;
							$refunds = $order->get_refunds();
							if ( count( $refunds ) > 0 ) {
								$refund_amount = 0.0;
								foreach ( $refunds as $refund ) {
									$refund_amount += $refund->get_amount();
								}
								$warning = 'Bestelling <a href="'.$order->get_edit_order_url().'" target="_blank">'.$row->order.'</a> bevat een terugbetaling t.w.v. '.wc_price( $refund_amount );
								if ( $refund_amount > ( $order->get_total() - ob2c_get_total_voucher_amount( $order ) ) ) {
									$warning .= ' die groter is dan het restbedrag dat niet met vouchers betaald werd, <span style="color: red">dit mag in principe niet</span>';
								} else {
									$warning .= ' die kleiner is dan het restbedrag dat niet met vouchers betaald werd, <span style="color: green">geen probleem</span>';
								}
								$warnings[ $row->order ] = $warning;
							}
						}
						
						if ( ! array_key_exists( $blog_path, $repartition ) ) {
							$repartition[ $blog_path ] = 1;
						} else {
							$repartition[ $blog_path ] += 1;
						}
					}
					
					restore_current_blog();
				}
				
			}
			
			// Sorteer alfabetisch op naam van de winkel
			ksort( $repartition );
			
			if ( count( $warnings ) > 0 ) {
				// Sorteer op ordernummer
				ksort( $warnings );
				echo '<p>Waarschuwingen:</p><ol><li>'.implode( '</li><li>', $warnings ).'</li></ol>';
			}
			
			return $repartition;
		}
		
		function export_to_excel( $distribution ) {
			$reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
			$spreadsheet = $reader->load( get_stylesheet_directory().'/functions/vouchers/template-credit-export.xlsx' );
			$empty_sheet = clone $spreadsheet->getSheet(0);
			foreach ( $distribution as $credit_ref => $number_of_credits_per_shop ) {
				$i = 2;
				
				try {
					$spreadsheet->setActiveSheetIndexByName( $credit_ref );
				} catch (Exception $e) {
					// Maak het werkblad aan indien de referentie nog niet bestaat in de template
					write_log("Excel-werkblad '".$credit_ref."' niet gevonden, probeer aan te maken ...");
					$empty_sheet->setTitle( $credit_ref );
					// @toCheck: Fatale error omdat we werkblad iedere keer opnieuw proberen toevoegen?
					// Workbook already contains a worksheet named 'X'. Rename this worksheet first.
					$spreadsheet->addSheet( $empty_sheet );
					$spreadsheet->setActiveSheetIndexByName( $credit_ref );
				}
				
				foreach ( $number_of_credits_per_shop as $shop => $numbers_of_credits ) {
					$spreadsheet->getActiveSheet()->setCellValue( 'A'.$i, $shop )->setCellValue( 'B'.$i, $numbers_of_credits );
					$i++;
				}
			}
			$writer = new Xlsx( $spreadsheet );
			$writer->save( WP_CONTENT_DIR . '/exports/latest.xlsx' );
		}
			
		function output_latest_exports( $start_date, $end_date ) {
			global $voucher_ids;
			$files = get_latest_exports('xlsx');
			
			foreach ( $files as $file ) {
				$id = '';
				$title = ucwords( str_replace( '-', ' ', $file['name'] ) );
				$extras = '';
				
				if ( $file['name'] === 'latest.xlsx' ) {
					$id = 'latest';
					$title = 'Download openstaande export';
					if ( intval( date_i18n('j') ) >= 20 ) {
						// Afsluiten ten vroegste toestaan vanaf de 20ste van de maand
						$extras = ' <button id="'.$id.'" data-voucher-ids="'.implode( ',', $voucher_ids ).'" data-start-date="'.str_replace( '-', '', $start_date ).'" data-end-date="'.str_replace( '-', '', $end_date ).'" class="button confirm-export" disabled>Bevestig creditering</button>';
					} else {
						// Ook downloadknop niet tonen vòòr 20ste van de maand, om onafgesloten crediteringen te vermijden
						continue;
					}
				}
				
				// Om downloadlink te leggen naar niet-publieke map hebben we een download manager nodig ...
				echo '<br/><a href="'.content_url( '/exports/'.$file['name'] ).'" download><button id="'.$id.'" class="button download-excel">'.$title.'</button></a>'.$extras.'<br/>';
			}
		}
		
		function get_latest_exports( $extension = false, $max = false ) {
			$exports = array();
			$local_path = WP_CONTENT_DIR.'/exports/';
			
			if ( $handle = opendir( $local_path ) ) {
				// Loop door alle files in de map
				while ( false !== ( $file = readdir( $handle ) ) ) {
					$filepath = $local_path . $file;
					// Check of we enkel de opgegeven extensie moeten beschouwen
					if ( ! $extension or ends_with( $file, '.'.$extension ) ) {
						// Zet naam, timestamp, datum en pad van de upload in de array
						$exports[] = array(
							'name' => basename( $filepath ),
							'timestamp' => filemtime( $filepath ),
							'date' => get_date_from_gmt( date( 'Y-m-d H:i:s', filemtime( $filepath ) ), 'd/m/Y H:i:s' ),
							'path' => $filepath,
						);
					}
				}
				closedir( $handle );
			}
			
			// Orden in dalende chronologisch volgorde
			usort( $exports, 'sort_by_time' );
			
			if ( $max ) {
				// Geef enkel de eerste X weer
				return array_slice( $exports, 0, $max );
			} else {
				return $exports;
			}
		}
		
		function close_voucher_export() {
			?>
			<script type="text/javascript">
				jQuery(document).ready(function() {
					jQuery(".confirm-export").prop( "disabled", false );
					
					jQuery(".confirm-export").on( "click", function() {
						var button = jQuery(this);
						var go = confirm("Ben je zeker dat deze lijst wil afsluiten? Alle vouchers uit de Excel worden als uitbetaald gemarkeerd in de database en zullen niet meer opduiken in volgende exports! Bij de bestellingen in kwestie wordt een nota toegevoegd dat de cadeaubon op <?php echo date_i18n( 'd/m/Y', strtotime( '+1 weekday', strtotime('last day of this month') ) ); ?> gecrediteerd zal worden.");
						if ( go == true ) {
							button.prop( "disabled", true );
							button.text("Laden ...");
							jQuery("#wpcontent").css( "background-color", "gold" );
							closeCurrentList(button);
						}
					});
					
					var tries = 0;
					var max = 5;
					
					function closeCurrentList(button) {
						jQuery.ajax({
							type: 'POST',
							url: ajaxurl,
							data: {
								'action': 'oxfam_close_voucher_export_action',
								'path': '<?php echo WP_CONTENT_DIR . '/exports/latest.xlsx'; ?>',
								'start_date': button.data('start-date'),
								'end_date': button.data('end-date'),
								'voucher_ids': button.data('voucher-ids'),
							},
							dataType: 'html',
							success: function(newPath) {
								tries = 0;
								jQuery("#wpcontent").css( "background-color", "lightgreen" );
								button.text("Creditering succesvol afgesloten!");
								jQuery( ".download-excel#"+button.attr('id') ).html("Afgesloten creditering");
								jQuery( ".download-excel#"+button.attr('id') ).parent("a").attr( "href", newPath );
							},
							error: function(jqXHR, statusText, errorThrown) {
								tries++;
								var message = "Asynchroon laden van PHP-file mislukt ... (poging ###CURRENT### van ###MAXIMUM###: ###ERROR###)";
								message = message.replace( '###CURRENT###', tries );
								message = message.replace( '###MAXIMUM###', max );
								message = message.replace( '###ERROR###', errorThrown );
								jQuery(".output").prepend("<p>"+message+"</p>");
								if ( tries < max ) {
									closeCurrentList();
								} else {
									tries = 0;
									jQuery("#wpcontent").css( "background-color", "red" );
									button.text("Afsluiten van creditering mislukt!");
								}
							},
						});
					}
				});
			</script>
			<?php
		}
	?>
</div>