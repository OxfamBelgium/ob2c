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
	 * @version     1.8
	 *
	 */

	// Laad de WordPress-omgeving (relatief pad geldig vanuit elk thema)
	require_once '../../../wp-blog-header.php';

	// Register autoloader
	require_once '../../plugins/mollie-reseller-api/autoloader.php';
	Mollie_Autoloader::register();

	if ( $_GET['import_key'] !== IMPORT_KEY ) {
		die("Helaba, dit mag niet!");
	}

	// Define configuration
	$partner_id = 2485891;
	$profile_key = 'C556F53A';

	// Instantiate Mollie class
	$mollie = new Mollie_Reseller( $partner_id, $profile_key, MOLLIE_APIKEY );

	$partner_id_customer = '2842281';

	try {
		// $simplexml = $mollie->accountCreate( 'owwoostende', array( 'testmode' => 1, 'name' => 'Melissa Vandewalle', 'company_name' => 'Oxfam-Wereldwinkel Oostende', 'address' => 'Torhoutsesteenweg 25', 'zipcode' => '8400', 'city' => 'Oostende', 'country' => 'BE', 'email' => 'webshop.oostende@oxfamwereldwinkels.be', 'registration_number' => '0446474568', 'legal_form' => 'vzw-be', 'vat_number' => 'BE0446474568', 'representative' => 'Roland Dehoorne', 'billing_address' => 'Torhoutsesteenweg 25', 'billing_zipcode' => '8400', 'billing_city' => 'Oostende', 'billing_country' => 'BE', 'bankaccount_iban' => 'BE82 0010 7723 0668', 'bankaccount_bic' => 'GEBA BE BB', 'bankaccount_bankname' => 'BNP Paribas Fortis', 'bankaccount_location' => 'Brussel', ) );
		// Account werd nog aangemaakt zonder IBAN-gegevens! LIJKT GEEN ZIN TE HEBBEN
		$simplexml = $mollie->accountEditByPartnerId( $partner_id_customer, array( 'name' => 'Melissa Vandewalle', 'company_name' => 'Oxfam-Wereldwinkel Oostende', 'address' => 'Torhoutsesteenweg 25', 'zipcode' => '8400', 'city' => 'Oostende', 'country' => 'BE', 'email' => 'webshop.oostende@oxfamwereldwinkels.be', 'registration_number' => '0446474568', 'legal_form' => 'vzw-be', 'vat_number' => 'BE0446474568', 'representative' => 'Roland Dehoorne', 'billing_address' => 'Torhoutsesteenweg 25', 'billing_zipcode' => '8400', 'billing_city' => 'Oostende', 'billing_country' => 'BE', 'bankaccount_iban' => 'BE82 0010 7723 0668', 'bankaccount_bic' => 'GEBA BE BB', 'bankaccount_bankname' => 'BNP Paribas Fortis', 'bankaccount_location' => 'Brussel', ) );
		// $simplexml = $mollie->profileCreateByPartnerId( $partner_id_customer, 'Oxfam-Wereldwinkel Oostende', 'https://demo.oxfamwereldwinkels.be/oostende', 'webshop.oostende@oxfamwereldwinkels.be', '059 51 37 00', 5499 );
	} catch (Mollie_Exception $e) {
		die('An error occurred: '.$e->getMessage());
	}

	if ( $simplexml->resultcode == '10' ) {
		echo "<p>".$simplexml->resultmessage."</p>";
		if ( isset($simplexml->profile) ) {
			echo "<p>Profiel ".$simplexml->profile->website." voor ".$simplexml->profile->name." succesvol gecreëerd!<p>";
			echo "<p>TEST API: ".$simplexml->profile->api_keys->test."</p>";
			echo "<p>LIVE API: ".$simplexml->profile->api_keys->live."</p>";
		}
		echo "<p></p>";
	}

	echo '<pre>'.var_export($simplexml, true).'</pre>';
?>