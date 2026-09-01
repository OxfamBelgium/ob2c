<?php
	if ( ! defined('ABSPATH') ) exit;
?>

<div class="wrap">
	<h1>Stel de voorraad van je lokale webshop in</h1>

	<nav class="nav-tab-wrapper">
		<?php
			// Om het submenu de tabselectie netjes te laten volgen, kijken we beter naar het laatste deel van de parameter 'oxfam-product-list-...' i.p.v. de nieuwe parameter 'assortment'
			$tabs = array( 'general' => 'Alle producten', 'chocolade' => 'Chocolade', 'koffie' => 'Koffie', 'wijn' => 'Wijn', 'andere-dranken' => 'Andere dranken', 'ontbijt' => 'Ontbijt', 'snacks' => 'Snacks', 'wereldkeuken' => 'Wereldkeuken', 'crafts' => 'Assortiment MDM', 'local' => 'Lokaal assortiment' );
			
			$parts = explode( 'oxfam-products-list-', $_REQUEST['page'] );
			if ( count( $parts ) === 2 and array_key_exists( $parts[1], $tabs ) ) {
				$assortment = $parts[1];
			} else {
				$assortment = 'general';
			}
			
			foreach ( $tabs as $key => $title ) {
				$active = '';
				if ( $assortment === $key ) {
					$active = 'nav-tab-active';
				}
				
				if ( $key === 'general' ) {
					$suffix = '';
				} else {
					$suffix = '-'.$key;
				}
				echo '<a href="'.admin_url( 'admin.php?page=oxfam-products-list'.$suffix ).'" class="nav-tab '.$active.'">'.$title.'</a>';
			}
		?>
	</nav>

	<p>Vink een product aan om het op de homepage te plaatsen of selecteer de juiste voorraadstatus om het in of uit de online verkoop te halen. Je aanpassing wordt onmiddellijk opgeslagen! Met de knop onderaan de pagina kun je alle producten op dit tabblad in één keer in/uit voorraad halen. Een bevestigingsvenster behoedt je daarbij voor onbedoelde wijzigingen. <b>Tip: met Ctrl+F kun je snel zoeken naar een product.</b></p>

	<p>Recente producten met een publicatiedatum die in de voorbije 3 maanden ligt, hebben <span style="background-color: lightskyblue;">een blauwe achtergrond</span> en krijgen in de front-end het 'nieuw'-label. Ze verschijnen aanvankelijk als 'niet in assortiment' in jullie lokale webshop, zodat je alle tijd hebt om te beslissen of je het product zal inkopen en online wil aanbieden. Producten die voor langere tijd onbeschikbaar zijn op BestelWeb krijgen <span style="background-color: gold;">een gele achtergrond</span>, zodat het duidelijk is dat dit product misschien op zijn laatste benen loopt.</p>

	<?php if ( $assortment !== 'local' ) : ?>
		<p>Oude producten die definitief niet meer te bestellen zijn bij Oxfam Fair Trade worden pas na enkele maanden uit de moederdatabank verwijderd (en dus uit jullie webshop), zodat we er zeker kunnen van zijn dat er geen lokale voorraden meer bestaan. Dit zal ook aangekondigd worden op het dashboard.</p>
	<?php endif; ?>

	<div id="oxfam-products">
		<?php
			// Query alle gepubliceerde producten, orden op ompaknummer
			$args = array(
				'post_type'			=> 'product',
				'post_status'		=> array('publish'),
				'posts_per_page'	=> -1,
				'meta_key'			=> '_sku',
				'orderby'			=> 'meta_value',
				'order'				=> 'ASC',
			);
			$products = new WP_Query( $args );
			
			if ( $products->have_posts() ) {
				$i = 0;
				$instock_cnt = 0;
				$featured_cnt = 0;
				$stock_statuses = wc_get_product_stock_status_options();
				$empties = get_oxfam_empties_skus_array();
				$content = '<div style="display: table; width: 100%;">';
				
				while ( $products->have_posts() ) {
					$products->the_post();
					$product = wc_get_product( get_the_ID() );
					
					// Verhinder dat leeggoed ook opduikt
					if ( $product === false or in_array( $product->get_sku(), $empties ) ) {
						continue;
					}
					
					// Logica eventueel reeds toepassen in WP_Query voor performantie?
					if ( ! ob2c_product_matches_assortment( $product, $assortment ) ) {
						continue;
					}
					
					// Kleur de randen en tel de initiële waarde voor de tellers
					if ( $product->is_on_backorder() ) {
						$class = 'border color-orange';
					} elseif ( $product->is_in_stock() ) {
						$class = 'border color-green';
						$instock_cnt++;
					} else {
						$class = 'border color-red';
					}
					if ( $product->is_featured() ) {
						$featured_cnt++;
					}
					
					$content .= '<div id="'.get_the_ID().'" class="compact';
					
					// Voeg klasse toe indien recent gepubliceerd
					if ( get_the_date('U') > strtotime('-3 months') ) $content .= ' new';
					
					// Voeg klasse toe indien centraal niet langer op voorraad
					if ( $product->get_meta('_in_bestelweb') === 'nee' ) {
						$content .= ' old';
					}
					
					$content .= '">';
						$content .= '<div class="cell" style="padding: 0.25em; width: 3%; text-align: center;"><a href="'.get_permalink().'" target="_blank">'.$product->get_image( 'wc_order_status_icon', null, false ).'</a></div>';
						$content .= '<div class="cell '.$class.'" style="width: 40%; text-align: left;"><span class="title">'.$product->get_sku().': '.$product->get_title().' ('.$product->get_meta('_shopplus_code').')';
						$content .= '</span></div>';
						$content .= '<div class="cell"><select class="toggle" id="'.get_the_ID().'-stockstatus">';
							$content .= '<option value="instock" '.selected( $product->get_stock_status(), 'instock', false ).'>'.$stock_statuses['instock'].'</option>';
							$content .= '<option value="onbackorder" '.selected( $product->get_stock_status(), 'onbackorder', false ).'>'.$stock_statuses['onbackorder'].'</option>';
							$content .= '<option value="outofstock" '.selected( $product->get_stock_status(), 'outofstock', false ).'>'.$stock_statuses['outofstock'].'</option>';
						$content .= '</select></div>';
						$content .= '<div class="cell">';
						if ( $product->get_catalog_visibility() !== 'hidden' and ! has_term( 'Grootverbruik', 'product_cat', get_the_ID() ) ) {
							$content .= '<input class="toggle" type="checkbox" id="'.get_the_ID().'-featured" '.checked( $product->is_featured(), true, false ).'> <label for="'.get_the_ID().'-featured">In de kijker?</label>';
						} else {
							if ( $product->get_catalog_visibility() === 'hidden' ) {
								$content .= '<small>NIET ZICHTBAAR</small>';
							}
							if ( has_term( 'Grootverbruik', 'product_cat', get_the_ID() ) ) {
								$content .= '<small>ENKEL ZICHTBAAR VOOR B2B-KLANTEN</small>';
							}
						}
						$content .= '</div>';
					$content .= '<div class="cell output"></div>';
					$content .= '</div>';
					$i++;
				}
				$content .= '</div>';
				wp_reset_postdata();
				
				echo '<p style="text-align: right; width: 100%;"><br>Deze pagina toont <b>'.$i.' producten</b>, waarvan er momenteel <b><span class="instock-cnt">'.$instock_cnt.'</span> bestelbaar</b> zijn en <b><span class="featured-cnt">'.$featured_cnt.'</span> in de kijker</b> staan op de homepage.</p>';
				
				echo $content;
				
				echo '<p style="text-align: right; width: 100%;">Deze pagina toont <b>'.$i.' producten</b>, waarvan er momenteel <b><span class="instock-cnt">'.$instock_cnt.'</span> bestelbaar</b> zijn en <b><span class="featured-cnt">'.$featured_cnt.'</span> in de kijker</b> staan op de homepage.</p>';
			}
		?>
		<?php if ( $i < 500 ) : ?>
			<div style="display: table; width: 100%; border-top: 1px solid black; border-bottom: 1px solid black;">
				<div class="cell" style="width: 3%;"></div>
				<div class="cell" style="width: 40%; text-align: center;">
					<select class="global-toggle">';
						<option value="" selected>(bulkwijziging)</option>
						<option value="instock">Zet ALLE producten op deze pagina op voorraad</option>
						<option value="onbackorder">Zet ALLE producten op deze pagina tijdelijk uit voorraad</option>
						<option value="outofstock">Haal ALLE producten op deze pagina uit assortiment</option>
					</select>
				</div>
				<div class="cell" style="width: 40%; text-align: left;">
					Opgelet: deze bewerking kan, afhankelijk van het aantal producten, ettelijke seconden in beslag nemen! Sluit de pagina niet zolang de oranje boodschap zichtbaar is. Na afloop wordt de pagina opnieuw geladen.
				</div>
				<div class="cell output" style="width: 17%;"></div>
			</div>
		<?php endif; ?>
	</div>
</div>

<script type="text/javascript">
	jQuery(document).ready(function() {
		// Check wijzigingen op selects (= voorraad) en checkboxes (= in de kijker)
		jQuery("#oxfam-products").find(".toggle").on( 'change', function() {
			var parts = jQuery(this).attr('id').split("-");
			var id = parts[0];
			var meta = parts[1];
			if ( meta == 'featured' ) {
				ajaxCall(id, meta, jQuery(this).is(":checked"));
			}
			if ( meta == 'stockstatus' ) {
				// TO DO: Validatie toevoegen op geldigheid waarde (kan gemanipuleerd worden in HTML)
				var value = jQuery(this).find(":selected").val();
				ajaxCall( id, meta, value );
			}
		});
		
		// Reset teller
		var tries = 0;
		
		// Verwerk de individuele AJAX-call
		function ajaxCall(id, meta, value) {
			jQuery("#"+id).find(".output").html("Aan het opslaan ...");
			
			var input = {
				'action': 'oxfam_stock_action',
				'id': id,
				'meta': meta,
				'value': value,
			};
			
			jQuery.ajax({
				type: 'POST',
				url: ajaxurl,
				data: input,
				dataType: 'html',
				success: function(msg) {
					tries = 0;
					
					if ( msg != 'ERROR' ) {
						// Pas de gekleurde rand aan na een succesvolle voorraadwijziging
						if ( value == 'onbackorder' ) {
							jQuery("#"+id).find('.border').removeClass('color-red color-green').addClass('color-orange');
						} else if ( value == 'outofstock' ) {
							jQuery("#"+id).find('.border').removeClass('color-green color-orange').addClass('color-red');
						} else if ( value == 'instock' ) {
							jQuery("#"+id).find('.border').removeClass('color-red color-orange').addClass('color-green');
						}
						
						// Werk de tellers bij
						jQuery(".instock-cnt").html(jQuery("#oxfam-products").find(".border.color-green").length);
						jQuery(".onbackorder-cnt").html(jQuery("#oxfam-products").find(".border-color-orange").length);
						jQuery(".featured-cnt").html(jQuery("#oxfam-products").find("input[type=checkbox]:checked").length);
					} else {
						msg = 'Niets gedaan!';
					}
					
					jQuery("#"+id).find(".output").html(msg).delay(5000).animate({
						opacity: 0,
					}, 1000, function(){
						jQuery(this).html("&nbsp;").css('opacity', 1);
					});
				},
				error: function(jqXHR, statusText, errorThrown) {
					tries++;
					if ( tries < 10 ) {
						ajaxCall(id, meta, value);
					} else {
						// Val terug op de tegengestelde waarde
						if ( value == 'outofstock' || value == 'onbackorder' ) {
							jQuery(this).val('instock');
						} else if ( value == 'instock' ) {
							jQuery(this).val('outofstock');
						} else {
							jQuery(this).prop("checked", !jQuery(this).is(":checked") );
						}
						
						tries = 0;
						jQuery("#"+id).find(".output").html("Wijzigingen mislukt!").delay(15000).animate({
							opacity: 0,
						}, 1000, function(){
							jQuery(this).html("&nbsp;").css('opacity', 1);
						});
					}
				},
			});
		}
		
		jQuery("#oxfam-products").find(".global-toggle").on( 'change', function() {
			var go = confirm("Weet je zeker dat je dit wil doen?");
			if ( go == true ) {
				jQuery(this).parent().parent().find(".output").html("Bezig met verwerken, wacht op automatische refresh ...");
				
				var input = {
					'action': 'oxfam_bulk_stock_action',
					'status': jQuery(this).find(":selected").val(),
					'assortment': '<?php echo $assortment; ?>',
				};
				
				jQuery.ajax({
					type: 'POST',
					url: ajaxurl,
					data: input,
					dataType: 'html',
					success: function(msg) {
						console.log(msg);
						if ( msg.substr(0, 5) == 'ERROR' ) {
							alert("Er liep iets mis, probeer het later eens opnieuw! "+msg);
							jQuery(this).val('');
						} else {
							window.location.reload();
						}
					},
					error: function(jqXHR, statusText, errorThrown) {
						alert("Er liep iets mis, probeer het later eens opnieuw!");
						jQuery(this).val('');
					},
				});
			} else {
				alert("Begrepen, we wijzigen niets!");
				jQuery(this).val('');
			}
		});
	});
</script>