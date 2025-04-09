<?php
	/**
	 * Copyright (c) 2012, Mollie B.V.
	 * All rights reserved.
	 *
	 * @license     Berkeley Software Distribution License (BSD-License 2) http://www.opensource.org/licenses/bsd-license.php
	 * @author      Mollie B.V. <info@mollie.com>
	 * @copyright   Copyright © 2012 Mollie B.V.
	 * @link        https://www.mollie.com
	 * @category    Mollie
	 * @version     1.10.4
	 *
	 */
	
	// WordPress volledig inladen, zodat we alle helper functies en constanten kunnen aanspreken
	// Relatief pad enkel geldig vanuit subfolder in subfolder van themamap!
	require_once '../../../../../wp-load.php';
	
	if ( $_GET['import_key'] !== IMPORT_KEY ) {
		die( "Access prohibited!" );
	}
	
	$blog_id_not_wp = 24;
	$login = 'antwerpencentrum';
	$email = 'wannes.perck@gmail.com';
	$shop_node = 212;
	$fname = 'Wannes';
	$lname = 'Perck';
	
	switch_to_blog( $blog_id_not_wp );
	$company = get_bloginfo('name');
	
	$test_mode = false;
	/*********************************/
	/* UITSCHAKELEN INDIEN VOOR ECHT */
	/*********************************/
	$test_mode = true;
	
	if ( $test_mode ) {
		$user_args = array(
			'user_login' => $login,
			'user_email' => $email,
			'display_name' => $fname,
			'first_name' => $fname,
			'last_name' => $lname,
			'role' => 'local_manager',
		);
		// Creëer onmiddellijk user
		$user_id = wp_insert_user( $user_args );
		if ( is_numeric( $user_id ) ) {
			echo 'User-ID '.$user_id.' aangemaakt<br/>';
			// Klantenrol wordt niet langer automatisch toegevoegd!
			// $new_user = get_user_by( 'ID', $user_id );
			// if ( $new_user ) {
			// 	$new_user->remove_role('customer');
			// }
		}
		
		if ( update_option( 'admin_email', $email ) ) {
			echo "Adminadres gewijzigd naar ".$email."!<br/>";
		}
		if ( update_option( 'oxfam_shop_node', $shop_node ) ) {
			echo "Winkelnode gewijzigd naar ".$shop_node."!<br/>";
		}
		if ( update_option( 'woocommerce_email_from_name', $company ) ) {
			echo "Afzendnaam gewijzigd naar ".$company."!<br/>";
		}
		if ( update_option( 'woocommerce_email_from_address', $email ) ) {
			echo "Algemeen afzendadres gewijzigd naar ".$email."!<br/>";
		}
		$wp_mail_smtp = get_option('wp_mail_smtp');
		if ( is_array( $wp_mail_smtp ) ) {
			$wp_mail_smtp['mail']['from_email'] = $email;
			$wp_mail_smtp['mail']['from_name'] = $company;
			if ( update_option( 'wp_mail_smtp', $wp_mail_smtp ) ) {
				echo "SMTP-afzender gewijzigd naar ".$company." (".$email.")!<br/>";
			}
		}
		$new_order_settings = get_option('woocommerce_new_order_settings');
		if ( is_array( $new_order_settings ) ) {
			$new_order_settings['recipient'] = $email;
			if ( update_option( 'woocommerce_new_order_settings', $new_order_settings ) ) {
				echo "Afzendadres nieuwe bestelling gewijzigd naar ".$email."!<br/>";
			}
		}
		$cancelled_order_settings = get_option('woocommerce_cancelled_order_settings');
		if ( is_array( $cancelled_order_settings ) ) {
			$cancelled_order_settings['recipient'] = $email;
			if ( update_option( 'woocommerce_cancelled_order_settings', $cancelled_order_settings ) ) {
				echo "Afzendadres geannuleerde bestelling gewijzigd naar ".$email."!<br/>";
			}
		}
		$failed_order_settings = get_option('woocommerce_failed_order_settings');
		if ( is_array( $failed_order_settings ) ) {
			$failed_order_settings['recipient'] = $email;
			if ( update_option( 'woocommerce_failed_order_settings', $failed_order_settings ) ) {
				echo "Afzendadres mislukte bestelling gewijzigd naar ".$email."!<br/>";
			}
		}
		// @toDo: Aanpassen aan nieuwe datastructuur WooCommerce Local Pickup Plus 2.9+
		$pickup_locations = get_option('woocommerce_pickup_locations');
		if ( is_array( $pickup_locations ) ) {
			// We wijzigen per definitie de eerste locatie
			$pickup_locations[0]['shipping_company'] = $company;
			if ( update_option( 'woocommerce_pickup_locations', $pickup_locations ) ) {
				echo "Afhaalpunt hernoemd naar ".$company."!<br/>";
			}
		}
	}
	
	require_once WP_PLUGIN_DIR.'/mollie-reseller-api/autoloader.php';
	Mollie_Autoloader::register();
	$mollie = new Mollie_Reseller( MOLLIE_PARTNER, MOLLIE_PROFILE, MOLLIE_APIKEY );
	
	try {
		// Parameters op te halen uit site
		$address = get_oxfam_shop_data('place');
		$zip = get_oxfam_shop_data('zipcode');
		$city = get_oxfam_shop_data('city');
		$phone = '32'.str_replace( '/', '', str_replace( '.', '', substr( get_oxfam_shop_data('telephone'), 1 ) ) );
		$email = get_blog_option( $blog_id_not_wp, 'admin_email' );
		$btw = str_replace( ' ', '', str_replace( '.', '', get_oxfam_shop_data('tax') ) );
		$url = get_bloginfo('url');
		
		// HEADQUARTER EN ACCOUNT ZIJN LEEG SINDS NIEUWE OBE-SITE
		// $headquarter = get_oxfam_shop_data('headquarter');
		// $lines = explode( ', ', $headquarter, 2 );
		// $billing_address = trim($lines[0]);
		// $parts = explode( ' ', $lines[1], 2 );
		// $billing_zip = trim($parts[0]);
		// $billing_city = trim($parts[1]);
		
		$login = 'oww'.$login;
		$representative = $fname.' '.$lname;
		$billing_address = $address;
		$billing_zip = $zip;
		$billing_city = $city;
		$iban = 'BE56001813666388';
		$bic = 'GEBABEBB';
		
		// Check of we deze KBO-parameters niet handmatig moeten overrulen
		// Moet in de praktijk toch opnieuw in Mollie, dus niet zo belangrijk
		// $representative = '';
		
		$parameters = array(
			'name' => $representative,
			'company_name' => $company,
			'url' => $url,
			'address' => $address,
			'zipcode' => $zip,
			'city' => $city,
			'country' => 'BE',
			'email' => $email,
			'registration_number' => str_replace( 'BE', '', $btw ),
			'legal_form' => 'vzw-be',
			'vat_number' => $btw,
			'representative' => $representative,
			'bankaccount_iban' => $iban,
			'bankaccount_bic' => $bic,
			'locale' => 'nl_BE',
		);
		
		echo '<pre>'.var_export( $parameters, true ).'</pre>';
		
		if ( $test_mode ) {
			echo '<a href="https://kbopub.economie.fgov.be/kbopub/zoeknummerform.html?nummer='.str_replace( 'BE', '', $btw ).'&actionLu=Zoek" target="_blank">KBO-fiche</a><br/>';
			echo '<a href="https://www.ibanbic.be/default.aspx?textboxBBAN='.$iban.'" target="_blank">BIC-code</a><br/>';
			echo '<a href="https://outlook.office.com/oww.be?login_hint='.$email.'" target="_blank">Mailbox</a>';
			$parameters['testmode'] = 1;
		}
		
		$accountxml = $mollie->accountCreate( $login, $parameters );
		echo '<pre>'.var_export( $accountxml, true ).'</pre>';
	} catch (Mollie_Exception $e) {
		die( "An error occurred: ".$e->getMessage() );
	}
	
	if ( $accountxml->resultcode == '10' and ! array_key_exists( 'testmode', $parameters ) ) {
		echo "<p>".$accountxml->resultmessage."</p>";
		
		echo "Partner-ID: ".$accountxml->partner_id."<br/>";
		// SimpleXML-node expliciet casten naar string indien geen context!
		if ( update_blog_option( $blog_id_not_wp, 'oxfam_mollie_partner_id', (string) $accountxml->partner_id ) ) {
			echo "Partner-ID gewijzigd in webshop!<br/>";
		}
		
		echo "Wachtwoord: ".$accountxml->password."<br/>";
		$user = get_user_by( 'email', $email );
		if ( $user ) {
			wp_set_password( (string) $accountxml->password, $user->ID );
			echo "Wachtwoord gekopieerd naar lokale beheerder!<br/>";
		}
		
		echo "<p>&nbsp;</p>";
		
		$profilexml = $mollie->profileCreateByPartnerId( $accountxml->partner_id, $company, $url, $email, $phone, 5499 );
		
		if ( $profilexml->resultcode == '10' ) {
			echo "<p>".$profilexml->resultmessage."</p>";
			
			echo "LIVE API: ".$profilexml->profile->api_keys->live."<br/>";
			if ( update_blog_option( $blog_id_not_wp, 'mollie-payments-for-woocommerce_live_api_key', (string) $profilexml->profile->api_keys->live ) ) {
				echo "Live API-key gewijzigd in webshop!<br/>";
			}
			
			echo "TEST API: ".$profilexml->profile->api_keys->test."<br/>";
			if ( update_blog_option( $blog_id_not_wp, 'mollie-payments-for-woocommerce_test_api_key', (string) $profilexml->profile->api_keys->test ) ) {
				echo "Test API-key gewijzigd in webshop!<br/>";
			}
		} else {
			echo '<pre>'.var_export( $profilexml, true ).'</pre>';
		}
	} else {
		echo '<pre>'.var_export( $accountxml, true ).'</pre>';
	}
	
	restore_current_blog();
	
?>