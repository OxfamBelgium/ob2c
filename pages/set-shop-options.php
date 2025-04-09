<?php
	if ( ! defined('ABSPATH') ) exit;
	
	require_once WP_CONTENT_DIR.'/plugins/mollie-reseller-api/autoloader.php';
	
	function do_mollie_reseller_api_logic( $partner_id, $temporary = false ) {
		Mollie_Autoloader::register();
		$mollie = new Mollie_Reseller( MOLLIE_PARTNER, MOLLIE_PROFILE, MOLLIE_APIKEY );
		
		// Check of we niet op de hoofdaccount zitten, want anders fatale API-error
		if ( $partner_id != 2485891 and $partner_id > 2000000 ) {
			$href = 'https://my.mollie.com/dashboard/';
			
			// Verhinder doorklikken naar echte account op demosites
			if ( get_current_site()->domain === 'shop.oxfamwereldwinkels.be' ) {
				try {
					$login_link = $mollie->getLoginLink( $partner_id );
					$href = $login_link->redirect_url;
				} catch (Exception $e) {
					echo "<tr>";
						echo "<th class='left' style='color: red;'>Reseller API Deprecated</th>";
						echo "<td class='right'>Helaas kunnen we hier geen automatische inloglink naar jullie Mollie-account meer tonen. Gelieve handmatig in te loggen via <a href='https://my.mollie.com/dashboard/login' target='_blank'>mollie.com</a> Foutmelding: ".$e->getMessage()."</td>";
					echo "</tr>";
					return;
				}
			}
			
			echo "<tr>";
				echo "<th class='left'><a href='".$href."' target='_blank'>Log volautomatisch in op je Mollie-betaalaccount &raquo;</a></th>";
				echo "<td class='right'>Opgelet: deze link is slechts 60 seconden geldig! Herlaad desnoods even deze pagina.";
				if ( $temporary ) {
					echo "<br/><span style='color: red;'>Je ziet als admin momenteel de nieuwe Mollie-account van Antwerpen i.p.v. Deurne</span>";
				}
				echo "</td>";
			echo "</tr>";
			
			$methods = $mollie->availablePaymentMethodsByPartnerId( $partner_id );
			if ( $methods->resultcode == '10' ) {
				$lacking = array();
				// Er bestaat geen aparte service voor Apple Pay, wel voor 'voucher'
				$must_be = array( 'mistercash', 'kbc', 'belfius', 'creditcard', 'ideal' );
				foreach ( $must_be as $service ) {
					if ( $methods->services->{$service} == 'false' ) {
						$lacking[] = $service;
					}
				}
				
				if ( count( $lacking ) > 0 ) {
					echo "<tr>";
						echo "<th class='left' style='color: red;'>Activeer volgende verplichte betaalmethodes:</th>";
						echo "<td class='right'>";
							foreach ( $lacking as $service ) {
								echo strtoupper( $service )."&nbsp;&nbsp;";
							}
						echo "</td>";
					echo "</tr>";
				}
			}
			
			$profiles = $mollie->profilesByPartnerId( $partner_id );
			if ( $profiles->resultcode == '10' ) {
				// if ( get_webshop_name() != trim_and_uppercase( $profiles->items->profile->name ) ) {
				// 	$name_warning = "<br/><small style='color: red;'>Opgelet, bij Mollie staat een andere bedrijfsnaam geregistreerd!</small>";
				// }
				
				// Fix voor winkels met twee nummers (bv. Mariakerke) TE VERWIJDEREN?
				$phones = explode( ' of ', get_oxfam_shop_data('telephone') );
				$warning = "<br/><small style='color: red;'>Opgelet, bij Mollie staat een ander contactnummer geregistreerd!</small>";
				if ( $phones[0] != format_phone_number( '0'.substr( $profiles->items->profile->phone, 2 ), '.' ) ) {
					if ( count($phones) === 2 ) {
						if ( $phones[1] != format_phone_number( '0'.substr( $profiles->items->profile->phone, 2 ), '.' ) ) {
							$phone_warning = $warning;
						}
					} else {
						$phone_warning = $warning;
					}
				}
				
				if ( get_webshop_email() != $profiles->items->profile->email ) {
					$mail_warning = "<br/><small style='color: red;'>Opgelet, bij Mollie staat een ander contactadres geregistreerd!</small>";
				}
			}
			
			$accounts = $mollie->bankAccountsByPartnerId( $partner_id );
			if ( $accounts->resultcode == '10' ) {
				// Er kunnen meerdere rekeningnummers in de account zitten!
				foreach ( $accounts->items->bankaccount as $bankaccount ) {
					if ( $bankaccount->selected == 'true' and get_oxfam_shop_data('account') !== format_account( $bankaccount->iban_number ) ) {
						$account_warning = "<br/><small style='color: red;'>Opgelet, Mollie gebruikt een ander rekeningnummer voor de uitbetalingen!</small>";
					}
					if ( get_oxfam_shop_data('account') === format_account( $bankaccount->iban_number ) and $bankaccount->verified == 'false' ) {
						$account_warning = "<br/><small style='color: red;'>Opgelet, dit rekeningnummer is bij Mollie (nog) niet geverifieerd!</small>";
					}
				}
			}
		}
	}
?>

<div class="wrap">
	<h1>Handige gegevens voor je lokale webshop</h1>
	
	<form method="post" action="options.php">
		<table class="form-table" id="oxfam-options">
			<tr valign="top">
				<th class='left'><a href='https://outlook.office.com/oxfamwereldwinkels.be?login_hint=<?= get_webshop_email(); ?>' target='_blank'>Log in op je Office 365-mailaccount &raquo;</a></th>
				<td class='right'>Merk op dat het wachtwoord van deze mailbox volledig los staat van de webshop.</td>
			</tr>
			
			<?php
				settings_errors();
				// Strikter onderscheid maken tussen opties die nationaal of lokaal beheerd worden (want anders toch bereikbaar door HTML te manipuleren)
				if ( current_user_can('create_sites') ) {
					settings_fields('oxfam-options-global');
				} else {
					// Er lijkt slechts één groep per keer opgeslagen te kunnen worden!
					settings_fields('oxfam-options-local');
				}
				
				if ( current_user_can('update_core') and get_current_blog_id() === 24 ) {
					$partner_id = get_option( 'oxfam_mollie_partner_id_new', 2485891 );
					do_mollie_reseller_api_logic( $partner_id, true );
				} else {
					$partner_id = get_option( 'oxfam_mollie_partner_id', 2485891 );
					do_mollie_reseller_api_logic( $partner_id );
				}
				
				if ( does_sendcloud_delivery() ) {
					echo "<tr>";
						echo "<th class='left'><a href='https://panel.sendcloud.sc/' target='_blank'>Log in op je SendCloud-verzendaccount &raquo;</a></th>";
						echo "<td class='right'>Merk op dat het wachtwoord van deze account volledig los staat van de webshop.</td>";
					echo "</tr>";
				}
			?>
			
			<tr valign="top">
				<th class="left">
					<label for="blog_id">Blog-ID:</label>
				</th>
				<td class="right">
					<input type="text" name="blog_id" class="text-input" value="<?= get_current_blog_id(); ?>" readonly>
				</td>
			</tr>
			<tr valign="top">
				<th class="left">
					<label for="oxfam_shop_node" title="Aan de hand van deze ID halen we adressen en openingsuren op uit de database achter de publieke site van Oxfam België.">Node OBE-site:</label>
				</th>
				<td class="right">
					<input type="text" name="oxfam_shop_node" class="text-input" value="<?= get_option('oxfam_shop_node'); ?>"<?php if ( ! current_user_can('create_sites') ) echo ' readonly'; ?>>
				</td>
			</tr>
			<tr valign="top">
				<th class="left">
					<label for="oxfam_mollie_partner_id" title="Je betaalaccount valt onder het contract dat Oxfam Fair Trade sloot met Mollie. Aan de hand van deze ID kunnen we de nodige API-keys invullen en in geval van nood inloggen op jullie lokale account.">Partner-ID Mollie:</label>
				</th>
				<td class="right">
					<input type="text" name="oxfam_mollie_partner_id" class="text-input" value="<?= esc_attr( $partner_id ); ?>" readonly>
				</td>
			</tr>
			<?php
				if ( is_regional_webshop() ) {
					echo "<tr valign='top'>";
						echo "<th class='left'><label for='oxfam_member_shops' title=''>Regiosamenwerking</label></th>";
						echo "<td class='right'>";
							echo "<input type='text' name='oxfam_member_shops' class='text-input' value='".esc_attr( trim_and_uppercase( implode( ', ', get_option('oxfam_member_shops') ) ) )."'";
							if ( ! current_user_can('create_sites') ) echo " readonly";
							echo ">";
						echo "</td>";
					echo "</tr>";
				}
				$b2b_shipping_options = get_option('woocommerce_b2b_home_delivery_settings');
				$oww_store_data = get_external_wpsl_store( get_option('oxfam_shop_node') );
			?>
			<tr valign="top">
				<th class="left">
					<?php
						$zips = get_oxfam_covered_zips();
						if ( does_home_delivery() and count( $zips ) > 0 ) {
							$delivery_info = 'Het is mogelijk dat sommige postcodes in overlap met andere webshops bediend worden.';
						} else {
							$delivery_info = 'Jullie organiseren geen thuislevering.';
						}
					?>
					<label for="oxfam_covered_zips">Postcodes waar deze webshop aan huis levert (<?= count( $zips ); ?>):<br/><small><?= $delivery_info; ?> Deze lijst kan enkel vanuit het NS gewijzigd worden.</small></label>
				</th>
				<td class="right">
					<textarea name="oxfam_covered_zips" rows="3" class="text-input" placeholder="<?= implode( ', ', $zips ); ?>" readonly><?= esc_textarea( implode( ', ', $zips ) ); ?></textarea>
				</td>
			</tr>
			<?php
				if ( does_home_delivery() ) {
					?>
						<tr valign="top">
							<th class="left">
								<label for="oxfam_minimum_free_delivery">Minimumbedrag voor gratis thuislevering:<br/><small>Dit bedrag verschijnt ook in de balk bovenaan je webshop, tenzij je een afwijkende boodschap liet plaatsen. Je kunt geen bedrag instellen dat hoger ligt dan het afgesproken nationale serviceniveau (50 euro).</small></label>
							</th>
							<td class="right">
								<input type="number" name="oxfam_minimum_free_delivery" class="text-input" value="<?= get_option( 'oxfam_minimum_free_delivery', get_site_option('oxfam_minimum_free_delivery') ); ?>" step="5" min="0" max="<?= get_site_option('oxfam_minimum_free_delivery'); ?>" <?php if ( current_user_can('create_sites') ) echo ' readonly'; ?>>
							</td>
						</tr>
						<tr valign="top">
							<th class="left">
								<label for="oxfam_b2c_delivery_cost">Leverkost voor betalende thuislevering:<br/><small>Je kunt geen bedrag instellen dat hoger ligt dan het afgesproken nationale serviceniveau (6,95 euro). Opgelet: indien je thuislevering volledig gratis wil maken, dien je het minimumbedrag hierboven op 0 te zetten (en niet dit bedrag). Vergeet de prijs van de artikels WEB6 en WEB21 niet aan te passen in ShopPlus!</small></label>
							</th>
							<td class="right">
								<input type="number" name="oxfam_b2c_delivery_cost" class="text-input" value="<?= get_option( 'oxfam_b2c_delivery_cost', get_site_option('oxfam_b2c_delivery_cost') ); ?>" step="0.05" min="0.95" max="6.95" <?php if ( current_user_can('create_sites') ) echo ' readonly'; ?>>
							</td>
						</tr>
						<tr valign="top">
							<th class="left">
								<label for="oxfam_does_risky_delivery">Schakel beperkingen op thuislevering uit:<br/><small>Als je dit aanvinkt, wordt de logica uitgeschakeld die de thuislevering van grote flessen fruitsap en volledige bakken fruitsap/bier verhindert in gewone bestellingen van particulieren.</small></label>
							</th>
							<td class="right">
								<input type="checkbox" name="oxfam_does_risky_delivery" value="yes" <?php checked( get_option('oxfam_does_risky_delivery'), 'yes' ); ?> <?php if ( current_user_can('create_sites') ) echo ' disabled'; ?>>
							</td>
						</tr>
						<!-- tr valign="top">
							<th class="left">
								<label for="oxfam_disable_local_pickup">Schakel afhalingen in de winkel tijdelijk uit:<br/><small>Opgelet: klanten zullen enkel nog kunnen afrekenen als ze een postcode ingeven die in jullie levergebied ligt! Als je afhaling op afspraak wil behouden tijdens een lockdown/vakantie is het beter om dit niet aan te vinken en gewoon uitzondelijke sluitingsdagen toe te voegen aan je winkelpagina op oxfambelgie.be. Indien de winkel de komende 7 dagen niet geopend is, verschijnt bij het afrekenen en in de mails automatisch een tekst dat jullie de klant zullen contacteren om een afspraak te maken.</small></label>
							</th>
							<td class="right">
								<input type="checkbox" name="oxfam_disable_local_pickup" value="yes" <?php // checked( get_option('oxfam_disable_local_pickup'), 'yes' ); ?> <?php // if ( current_user_can('create_sites') ) echo ' disabled'; ?>>
							</td>
						</tr -->
					<?php
				}
			?>
			<tr valign="top">
				<th class="left">
					<label for="oxfam_custom_webshop_telephone">Webshoptelefoonnummer:<br/><small>Ongeldige Belgische telefoonnummers (= geen 9 of 10 cijfers) zullen niet opgeslagen worden. Wis het telefoonnummer om het algemene telefoonnummer dat vermeld staat op <a href="https://oxfambelgie.be/winkels<?= $oww_store_data['slug']; ?>" target="_blank">jullie winkelpagina op oxfambelgie.be</a> opnieuw automatisch over te nemen.</small></label>
				</th>
				<td class="right">
					<input type="text" name="oxfam_custom_webshop_telephone" class="text-input" value="<?= esc_attr( get_option( 'oxfam_custom_webshop_telephone', '' ) ); ?>" placeholder="<?= get_oxfam_shop_data( 'telephone', 0, true ); ?>" <?php if ( current_user_can('create_sites') ) echo ' readonly'; ?>>
				</td>
			</tr>
			<!-- Deze instelling maakt geen deel meer uit van de geregistreerde opties en worden dus niet automatisch bijgewerkt! -->
			<!-- Ook sluitingsdagen van alle secundaire afhaalpunten tonen ter info? Maar wat met externe leverpunten? -->
			<tr valign="top">
				<th class="left">
					<label for="oxfam_holidays" title="Deze dagen tellen niet mee in de berekening van de levertermijn. Bovendien zal op deze dagen onderaan de webshop een banner verschijnen zodat het voor de klanten duidelijk is dat jullie winkel gesloten is.">Uitzonderlijke sluitingsdagen:<br/><small>Deze datums worden overgenomen uit <a href="https://oxfambelgie.be/winkels<?= $oww_store_data['slug']; ?>" target="_blank">jullie winkelpagina op oxfambelgie.be</a>. Het algoritme voor de uiterste leverdatum houdt rekening met deze dagen voor alle levermethodes. Indien er extra afhaalpunten zijn, wordt bij afhaling rekening gehouden met de sluitingsdagen van de gekozen winkel.</small></label>
				</th>
				<td class="right">
					<textarea name="oxfam_holidays" rows="3" class="text-input" readonly><?= esc_textarea( implode( ', ', get_site_option( 'oxfam_holidays_'.get_option('oxfam_shop_node') ) ) ); ?></textarea>
				</td>
			</tr>
			<tr valign="top">
				<th class="left">
					<label for="oxfam_sitewide_banner_top">Afwijkende bannertekst:<br/><small>Deze tekst verschijnt in de blauwe balk bovenaan elke pagina van de webshop en vervangt de standaardtekst. Hou het bondig en spelfoutenvrij! HTML-tags zijn niet toegestaan en zullen verwijderd worden. Wis alle tekst om opnieuw de standaardbanner te tonen.</small></label>
				</th>
				<td class="right">
					<textarea name="oxfam_sitewide_banner_top" rows="2" maxlength="200" class="text-input" placeholder="<?= get_default_local_store_notice(); ?>" <?php if ( current_user_can('create_sites') ) echo ' readonly'; ?>><?= esc_textarea( get_option('oxfam_sitewide_banner_top') ); ?></textarea>
				</td>
			</tr>
			<tr valign="top">
				<th class="left">
					<label for="oxfam_b2b_delivery_enabled">B2B-levering beschikbaar?<br/><small>Standaard is levering op locatie beschikbaar voor alle geregistreerde B2B-klanten (ongeacht de postcode in hun verzendadres). Op termijn kun je dit hier uitschakelen.</small></label>
				</th>
				<td class="right">
					<input type="checkbox" name="oxfam_b2b_delivery_enabled" value="yes" <?php checked( $b2b_shipping_options['enabled'], 'yes' ); ?> disabled>
				</td>
			</tr>
			<tr valign="top">
				<th class="left">
					<label for="oxfam_b2b_delivery_cost" title="">Kostprijs voor B2B-levering:<br/><small>Indien ingeschakeld verschijnt de levering op locatie voor alle B2B-klanten als gratis, maar op termijn kun je hier een uniform tarief instellen (indien gewenst).</small></label>
				</th>
				<td class="right">
					<input type="text" name="oxfam_b2b_delivery_cost" class="text-input" value="<?= strip_tags( wc_price( $b2b_shipping_options['cost'] ) ).' excl. BTW'; ?>" readonly>
				</td>
			</tr>
			<tr valign="top">
				<th class="left">
					<label for="oxfam_b2b_invitation_text">Afwijkende tekst onderaan uitnodigingsmail naar B2B-klanten:<br/><small>HTML-tags zijn niet toegestaan en zullen verwijderd worden. Wis alle tekst om opnieuw de standaardzin te gebruiken.</small></label>
				</th>
				<td class="right">
					<textarea name="oxfam_b2b_invitation_text" rows="2" class="text-input" placeholder="<?php _e( 'Zesde alinea in de uitnodingsmail aan B2B-gebruikers.', 'oxfam-webshop' ); ?>" <?php if ( current_user_can('create_sites') ) echo ' readonly'; ?>><?= esc_textarea( get_option('oxfam_b2b_invitation_text') ); ?></textarea>
				</td>
			</tr>
			<tr valign="top">
				<th class="left">
					<label for="oxfam_remove_excel_header">Laat header met klantgegevens weg uit pick-Excel:<br/><small>Hierdoor kun je de file zonder aanpassingen overnemen in ShopPlus (druk in het verkoopscherm op F10 en vervolgens op F12, op de kassacomputer moet Microsoft Excel geïnstalleerd zijn). Zo hoef je de producten niet meer handmatig in te scannen. Je verliest wel de dubbelcheck op de compleetheid van de bestelling en ziet het adres van de klant niet meer op het document.</small></label>
				</th>
				<td class="right">
					<input type="checkbox" name="oxfam_remove_excel_header" value="yes" <?php checked( get_option('oxfam_remove_excel_header'), 'yes' ); ?> <?php if ( current_user_can('create_sites') ) echo ' disabled'; ?>>
				</td>
			</tr>
			<?php
				// Altijd tonen, nu er ook weer lokale instellingen zijn
				echo "<tr><th class='left'></th><td class='right'>";
				submit_button();
				echo "</td></tr>";
			?>
			<!-- Deze 'instellingen' maken geen deel uit van de geregistreerde opties en worden dus niet automatisch opgeslagen in database! -->
			<tr valign="top">
				<th class="left">
					<label for="oxfam_tax" title="Komt voorlopig nog uit de OWW-site, maar kan beter uit Mollie getrokken worden want dat is de winkelinfo die de klant te zien krijgt indien hij een betaling betwist.">BTW-nummer: <?php if ( isset($tax_warning) ) echo $tax_warning; ?><br/><small><a href="https://kbopub.economie.fgov.be/kbopub/zoeknummerform.html?nummer=<?= str_replace( 'BE ', '', get_oxfam_shop_data('tax') ); ?>&actionlu=zoek" target="_blank">Kloppen jullie gegevens in de KBO-databank nog?</a></small></label>
				</th>
				<td class="right">
					<input type="text" name="oxfam_tax" class="text-input" value="<?= get_oxfam_shop_data('tax'); ?>" readonly>
				</td>
			</tr>
			<!-- Rekeningnummer zit niet langer in winkelpagina's, dus verbergen -->
			<tr valign="top" style="display: none;">
				<th class="left">
					<label for="oxfam_account" title="Komt voorlopig nog uit de OWW-site, maar kan beter uit Mollie getrokken worden want dat is de winkelinfo die de klant te zien krijgt indien hij een betaling betwist.">IBAN-rekeningnummer: <?php if ( isset($account_warning) ) echo $account_warning; ?></label>
				</th>
				<td class="right">
					<input type="text" name="oxfam_account" class="text-input" value="<?= get_oxfam_shop_data('account'); ?>" readonly>
				</td>
			</tr>
			<tr valign="top">
				<th class="left">
					<label for="oxfam_company" title="Dit is ook de titel van deze subsite en kan enkel door Frederik gewijzigd worden.">Bedrijfsnaam: <?php if ( isset($name_warning) ) echo $name_warning; ?></label>
				</th>
				<td class="right">
					<input type="text" name="oxfam_company" class="text-input" value="<?= get_webshop_name(); ?>" readonly>
				</td>
			</tr>
			<tr valign="top">
				<th class="left">
					<label for="oxfam_place" title="Zie je een fout staan? Werk je adres bij op de publieke site van Oxfam-Wereldwinkels.">Straat en huisnummer:</label>
				</th>
				<td class="right">
					<input type="text" name="oxfam_place" class="text-input" value="<?= get_oxfam_shop_data('place'); ?>" readonly>
				</td>
			</tr>
			<tr valign="top">
				<th class="left">
					<label for="oxfam_zipcode" title="Zie je een fout staan? Werk je adres bij op de publieke site van Oxfam-Wereldwinkels.">Postcode:</label>
				</th>
				<td class="right">
					<input type="text" name="oxfam_zipcode" class="text-input" value="<?= get_oxfam_shop_data('zipcode'); ?>" readonly>
				</td>
			</tr>
			<tr valign="top">
				<th class="left">
					<label for="oxfam_city" title="Zie je een fout staan? Werk je adres bij op de publieke site van Oxfam-Wereldwinkels.">Gemeente:</label>
				</th>
				<td class="right">
					<input type="text" name="oxfam_city" class="text-input" value="<?= get_oxfam_shop_data('city'); ?>" readonly>
				</td>
			</tr>
			<tr valign="top">
				<th class="left">
					<label for="oxfam_email" title="Deze Office 365-mailbox wordt ingesteld als het algemene contactadres van deze subsite en is initieel ook ekoppeld aan de lokale beheeraccount. Opgelet: via de profielpagina kun je deze hoofdgebruiker aan een andere mailbox linken (of schakelen we dat uit? niet handig indien we voor meerdere lokale beheerders opteren!) maar het contactadres naar klanten blijft altijd dit e-mailadres!">E-mailadres: <?php if ( isset($mail_warning) ) echo $mail_warning; ?></label>
				</th>
				<td class="right">
					<input type="text" name="oxfam_email" class="text-input" value="<?= get_webshop_email(); ?>" readonly>
				</td>
			</tr>
		</table>
	</form>
</div>