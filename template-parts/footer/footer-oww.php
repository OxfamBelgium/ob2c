<?php
	if ( ! is_main_site() ) {
		// Haal de huidige gekozen winkel op
		$current_store = false;
		if ( ! empty( $_COOKIE['latest_shop_id'] ) ) {
			$current_store = intval( $_COOKIE['latest_shop_id'] );
		}

		$shops = ob2c_get_pickup_locations();
		if ( $current_store === false or ! array_key_exists( $current_store, $shops ) ) {
			// De cookie slaat op een winkel uit een andere subsite (bv. door rechtstreeks switchen)
			// Stel de hoofdwinkel van de huidige subsite in als fallback
			$current_store = get_option('oxfam_shop_post_id');
		}

		$atts = array( 'id' => $current_store );
		// Check of de huidige geselecteerde winkel een lokale nieuwsbrief heeft
		$oww_store_data = get_external_wpsl_store( $atts['id'] );
		if ( $oww_store_data !== false ) {
			$mailchimp_url = $oww_store_data['mailchimp_url'];
		}
	}
?>

<?php if ( ! empty( $mailchimp_url ) ) : ?>
	<div id="newsletter">
		<div class="container">
			<div class="col-row md-display-flex">
				<div class="col-md-5 md-align-self-center">
					<h2>Abonneer je hier op de<br/> nieuwsbrief van <?= $oww_store_data['title']['rendered']; ?></h2>
				</div>
				<div class="col-md-7 md-align-self-center">
					<form id="local-newsletter" action="<?= $mailchimp_url; ?>" method="post" target="_blank">
						<input type="text" name="FNAME" placeholder="Voornaam" required>
						<input type="email" name="EMAIL" placeholder="E-mailadres" required>
						<input type="submit" value="Inschrijven">
					</form>
				</div>
			</div>
		</div>
	</div>
<?php else : ?>
	<div id="newsletter">
		<div class="container">
			<div class="col-row md-display-flex">
				<div class="col-md-5 md-align-self-center">
					<h2>Abonneer je hier<br/> op onze nieuwsbrief</h2>
				</div>
				<div class="col-md-7 md-align-self-center">
					<form id="regular-newsletter" class="mc4wp-form ajaxified-subscription" method="post" data-description="onze nieuwsbrief">
						<div class="mc4wp-form-fields">
							<input type="text" name="FNAME" placeholder="Voornaam" required>
							<input type="email" name="EMAIL" placeholder="E-mailadres" required>
							<input type="hidden" name="LIST_ID" value="5cce3040aa">
							<label style="display: none !important;">Laat dit veld leeg als je een mens bent: <input type="text" name="lekkere-honing" value="" tabindex="-1" autocomplete="off"></label>
							<input type="submit" value="Inschrijven">
						</div>
						<div class="mc4wp-response"></div>
					</form>
				</div>
			</div>
		</div>
	</div>
<?php endif; ?>

<script type="text/javascript">
	jQuery(document).ready( function() {
		jQuery('form.ajaxified-subscription').on( 'submit', function(event) {
			event.preventDefault();
			var form = jQuery(this);
			var output = form.find('.mc4wp-response');
			
			form.find("input[type='submit']").prop( 'disabled', true );
			output.html('Even geduld ...');
			
			var newsletter = 'no';
			if ( form.find("input[name='lekkere-honing']").val().length == 0 ) {
				newsletter = 'yes';
			}

			jQuery.ajax({
				type: 'POST',
				url: '/wp-content/themes/oxfam-webshop/functions/mailchimp/subscribe.php',
				data: {
					'fname': form.find("input[name='FNAME']").val(),
					'email': form.find("input[name='EMAIL']").val(),
					'list_id': form.find("input[name='LIST_ID']").val(),
					'source': 'sitebanner',
					'newsletter': newsletter
				},
				dataType: 'json',
			}).done( function(response) {
				/* Varieer de succesboodschap per MailChimp-lijst */
				var description = form.data('description');
				if ( response.status == 'subscribed' || response.status == 'resubscribed') {
					output.html('Hoera, je bent nu ingeschreven op '+description+'!');
				} else if ( response.status == 'updated' ) {
					output.html('Je was al ingeschreven op '+description+'. Bedankt voor je enthousiasme!');
				} else {
					output.html('Er liep iets verkeerd ('+response.error+').');
				}
			}).fail( function() {
				output.html('Er liep iets verkeerd. Probeer je het eens opnieuw?');
			}).always( function() {
				form.find("input[type='submit']").prop( 'disabled', false );
			});
		});
	});
</script>

<div id="footer">
	<div class="container">
		<div class="footer">
			<div class="col-row">
				<div class="col-md-10">
					<div class="col-row">
						<div class="col-md-3 col-sm-6">
							<div class="footer-menu">
								<?php
									// Dynamisch maken m.b.v. wp_remote_get() naar https://www.oxfamwereldwinkels.be/wp-json/menus/v1/menus/footer-menu-1
									// Activeer hiervoor eerst https://nl.wordpress.org/plugins/wp-rest-api-v2-menus/
									// <h3> => menu_title
									// <ul> => foreach items as item
									// <li> => item['title']
									// href => item['url']
									// target => item['target']
									// title => item['attr_title']
									// class => enkel de custom extra klasses kunnen overgenomen worden, plus 'menu-item-type-'.$item['type'] en 'menu-item-object-'.$item['object']
								?>
								<h3>Oxfam-Wereldwinkels</h3>
								<ul id="menu-footer-menu-1" class="menu">
									<li class="menu-item menu-item-type-post_type menu-item-object-page"><a href="https://oxfambelgie.be/wij-zijn-oxfam" target="_blank">Missie en aanpak</a></li>
									<li class="menu-item menu-item-type-post_type menu-item-object-page"><a href="https://oxfambelgie.be/doe-mee/doe-een-gift" target="_blank">Steun ons</a></li>
									<li class="menu-item menu-item-type-post_type menu-item-object-page"><a href="https://oxfambelgie.be/bescherming-tegen-misbruik-en-uitbuiting">Meld wangedrag</a></li>
									<li class="menu-item menu-item-type-post_type menu-item-object-page"><a href="https://oxfambelgie.be/vacatures" target="_blank">Vacatures</a></li>
									<li class="menu-item menu-item-type-post_type menu-item-object-page"><a href="https://oxfambelgie.be/netwerken-en-allianties-van-oxfam-belgie" target="_blank">Bevriende organisaties</a></li>
								</ul>
							</div>
						</div>
						<div class="col-md-3 col-sm-6">
							<div class="footer-menu">
								<h3>Meer over fair trade</h3>
								<ul id="menu-footer-menu-2" class="menu">
									<li class="menu-item menu-item-type-post_type menu-item-object-page"><a href="https://oxfambelgie.be/eerlijke-handel" target="_blank">Expertise</a></li>
									<li class="menu-item menu-item-type-post_type_archive menu-item-object-partner"><a href="https://www.oxfamfairtrade.be/nl/partners/">Producenten</a></li>
									<li class="menu-item menu-item-type-post_type menu-item-object-page"><a href="https://oxfambelgie.be/nieuws" target="_blank">Nieuws</a></li>
									<li class="menu-item menu-item-type-post_type_archive menu-item-object-recipe"><a href="https://www.oxfamfairtrade.be/nl/recepten/">Recepten</a></li>
									<li class="menu-item menu-item-type-post_type menu-item-object-page"><a href="https://oxfambelgie.be/oxfam-magazine" target="_blank">Magazine FAIR</a></li>
								</ul>
							</div>
						</div>
						<div class="col-md-3 col-sm-6">
							<div class="footer-menu">
								<h3>Ik ben</h3>
								<ul id="menu-footer-menu-3" class="menu">
									<li class="menu-item menu-item-type-taxonomy menu-item-object-category"><a href="https://oxfambelgie.be/pers" target="_blank">Pers</a></li>
									<li class="menu-item menu-item-type-post_type menu-item-object-page"><a href="https://oxfambelgie.be/doe-mee/oxfam-op-jouw-school" target="_blank">School</a></li>
									<li class="menu-item menu-item-type-post_type menu-item-object-page"><a href="https://oxfambelgie.be/eerlijke-handel" target="_blank">Beleidsmedewerker</a></li>
									<li class="menu-item menu-item-type-custom menu-item-object-custom"><a href="https://www.oxfamfairtrade.be/nl/" target="_blank">Bedrijf</a></li>
									<li class="menu-item menu-item-type-custom menu-item-object-custom"><a href="https://copain.oww.be" target="_blank">Vrijwilliger</a></li>
								</ul>
							</div>
						</div>
						<div class="col-md-3 col-sm-6">
							<div class="footer-menu">
								<h3>Blijf op de hoogte</h3>
								<ul id="menu-footer-menu-4" class="menu">
									<li class="menu-item menu-item-type-custom menu-item-object-custom"><a href="https://www.facebook.com/OxfamWereldwinkels" target="_blank">Facebook</a></li>
									<li class="menu-item menu-item-type-custom menu-item-object-custom"><a href="https://www.youtube.com/@OxfamwereldwinkelsBe" target="_blank">YouTube</a></li>
									<li class="menu-item menu-item-type-custom menu-item-object-custom"><a href="https://www.instagram.com/oxfam.BE/" target="_blank">Instagram</a></li>
									<li class="menu-item menu-item-type-custom menu-item-object-custom"><a href="https://twitter.com/OxfamBE" target="_blank">Twitter</a></li>
									<li class="menu-item menu-item-type-custom menu-item-object-custom"><a ref="https://www.linkedin.com/company/oxfam-fair-trade/" target="_blank">LinkedIn</a></li>
								</ul>
							</div>
						</div>
					</div>
				</div>
				<div class="col-md-2">
					<div class="footer-info">
						<h3>Contact</h3>
						<?php if ( is_main_site() ) : ?>
							<p>
								Ververijstraat 17<br/>
								B-9000 Gent<br/>
								BE 0415.365.777<br/>
								RPR GENT
							</p>
							<p><a href="tel:+32092188899">+32 (0)9/218.88.99</a></p>
							<a href="https://oxfambelgie.be/contact" class="btn">Contacteer ons</a>
						<?php else : ?>
							<p>
								<?= str_replace( 'Oxfam-Wereldwinkel ', 'OWW ', get_shop_name( $atts ) ); ?><br/>
								<?= get_shop_address( $atts ); ?><br/>
								<?= get_shop_vat_number( $atts ); ?>
							</p>
							<p><?= print_telephone( $atts ); ?></p>
							<a href="mailto:<?= get_webshop_email(); ?>" class="btn">Contacteer ons</a>
						<?php endif; ?>
					</div>
				</div>
			</div>
		</div>
		<div class="footer-logos">
			<div class="col-row">
				<div class="col-sm-2 hidden-xs">
					<div class="logo">
						<img src="<?= get_stylesheet_directory_uri(); ?>/images/logo-green.svg" alt="Logo Oxfam-Wereldwinkels">
					</div>
				</div>
				<div class="col-sm-10 col-xs-12">
					<div class="col-row md-display-flex">
						<div class="col-xs-3 md-align-self-center">
							<div class="logo">
								<a href="https://www.mo.be" target="_blank"><img width="111" height="62" src="https://<?= OXFAM_MAIN_SITE_DOMAIN; ?>/wp-content/uploads/2019/04/footer-logo-1.png" class="attachment-medium size-medium" alt=""></a>
							</div>
						</div>
						<div class="col-xs-3 md-align-self-center">
							<div class="logo">
								<a href="https://11.be" target="_blank"><img width="140" height="60" src="https://<?= OXFAM_MAIN_SITE_DOMAIN; ?>/wp-content/uploads/2019/04/footer-logo-2.png" class="attachment-medium size-medium" alt=""></a>
							</div>
						</div>
						<div class="col-xs-3 md-align-self-center">
							<div class="logo">
								<a href="https://www.vlaanderen.be" target="_blank"><img width="153" height="60" src="https://<?= OXFAM_MAIN_SITE_DOMAIN; ?>/wp-content/uploads/2019/04/footer-logo-3.png" class="attachment-medium size-medium" alt=""></a>
							</div>
						</div>
						<div class="col-xs-3 md-align-self-center">
							<div class="logo">
								<img width="252" height="60" src="https://<?= OXFAM_MAIN_SITE_DOMAIN; ?>/wp-content/uploads/2019/04/footer-logo-4.png" class="attachment-medium size-medium" alt="">
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

<div id="copyright">
	<div class="container">
		<div class="col-row md-display-flex">
			<div class="col-sm-8 md-align-self-center">
				<p>Oxfam-Wereldwinkels vzw &copy; 2019-<?= date_i18n('Y'); ?>. Alle rechten voorbehouden.<br/>
				Oxfam-Wereldwinkels/Fair Trade en Oxfam-Solidariteit bundelen de krachten onder de naam Oxfam België.<br/>
				<a href="https://oxfambelgie.be/privacy" target="_blank">Privacybeleid</a> / <a href="https://oxfambelgie.be/cookiebeleid" target="_blank">Cookiebeleid</a></p>
			</div>
			<div class="col-sm-4 md-align-self-center alcohol-warning">
				<a href="https://www.vlaanderen.be/regels-voor-verkoop-van-alcohol" target="_blank"><img width="250" src="<?= get_stylesheet_directory_uri() . '/images/geen-alcohol-minderjarigen.jpg'; ?>" class="attachment-medium size-medium" alt="Geen verkoop van alcohol aan minderjarigen"></a>
			</div>
		</div>
	</div>
</div>