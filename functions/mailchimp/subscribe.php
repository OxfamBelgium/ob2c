<?php

	header('Content-Type: application/json');
	
	// WordPress volledig inladen, zodat we alle helper functies en constanten kunnen aanspreken
	// dirname() enkel geldig vanuit subfolder in subfolder van themamap
	require_once dirname( __FILE__, 6 ) . '/wp-load.php';
	
	require_once WP_PLUGIN_DIR.'/mailchimp-api-wrapper.php';
	use \DrewM\MailChimp\MailChimp;
	
	$response = array(
		'status' => '',
		'error' => '',
	);
	
	$allowed_domains = array(
		'https://shop.oxfamwereldwinkels.be',
		'https://www.oxfamfairtrade.be',
		'https://apps.oxfamwereldwinkels.be',
		'https://dev.oxfamwereldwinkels.be',
		'https://dev.oxfamfairtrade.be',
	);
	$allowed_ips = array(
		'141.138.168.148',
	);
	
	$origin = $_SERVER['HTTP_ORIGIN'];
	if ( in_array( $origin, $allowed_domains ) ) {
		
		// Ofwel komt de request via AJAX vanaf een toegelaten domein
		header('Access-Control-Allow-Origin: ' . $origin);
		
	} elseif ( in_array( $_SERVER['REMOTE_ADDR'], $allowed_ips ) or ( isset( $_POST['import_key'] ) and $_POST['import_key'] === IMPORT_KEY ) ) {
		
		// Ofwel komt de request via PHP vanaf een toegelaten IP-adres EN/OF met de juiste parameter
		// POST-data over HTTPS wordt geëncrypteerd, dus afscherming geheime parameter is in orde
		
	} else {
		
		$response['status'] = 'unauthenticated';
		
	}
	
	if ( $response['status'] !== 'unauthenticated' ) {
		
		$MailChimp = new MailChimp( MAILCHIMP_APIKEY );
		$settings = array( 'marketing_permissions' => array() );
		
		$fname = trim_and_uppercase( $_POST['fname'] );
		$lname = trim_and_uppercase( $_POST['lname'] );
		$email = strtolower( trim( $_POST['email'] ) );
		
		if ( isset( $_POST['company'] ) ) {
			$company = trim( $_POST['company'] );
		} else {
			$company = '';
		}
		
		if ( isset( $_POST['school'] ) ) {
			$school = trim( $_POST['school'] );
		} else {
			$school = '';
		}
		
		if ( isset( $_POST['zip'] ) ) {
			$zip = trim( $_POST['zip'] );
		} else {
			$zip = '';
		}
		
		if ( isset( $_POST['gender'] ) ) {
			// Eventueel nog te valideren tot één van de geldige opties, zoals gedefinieerd in MailChimp
			$gender = trim( $_POST['gender'] );
		} else {
			$gender = '';
		}
		
		if ( isset( $_POST['shop'] ) ) {
			$shop = trim( $_POST['shop'] );
		} else {
			$shop = '';
		}
		
		if ( isset( $_POST['source'] ) ) {
			$source = trim( $_POST['source'] );
		} else {
			$source = '';
		}
		
		if ( isset( $_POST['minutes'] ) ) {
			$minutes = trim( $_POST['minutes'] );
		} else {
			$minutes = '';
		}
		
		if ( isset( $_POST['persons'] ) ) {
			$persons = trim( $_POST['persons'] );
		} else {
			$persons = '';
		}
		
		if ( isset( $_POST['language'] ) ) {
			$language = strtoupper( $_POST['language'] );
			// Zet om in één van de geldige opties, zoals gedefinieerd in MailChimp
			$languages = array( 'NL' => 'Nederlands', 'FR' => 'Français', 'EN' => 'English' );
			if ( array_key_exists( $language, $languages ) ) {
				$language = $languages[ $language ];
			} else {
				$language = '';
			}
		} else {
			$language = '';
		}
		
		if ( isset( $_POST['list_id'] ) ) {
			$list_id = $_POST['list_id'];
		} else {
			// Neem Digizine als default
			$list_id = '5cce3040aa';
		}
		
		// ID's blijven bewaard als omschrijving wijzigt!
		switch ( $list_id ) {
			// Oxfam-Wereldwinkels Digizine
			case '5cce3040aa':
				// GDPR-permissie 'maandelijkse nieuwsbrief' activeren, tenzij expliciet geweigerd
				if ( ! ( isset( $_POST['digizine'] ) and $_POST['digizine'] === 'no' ) ) {
					$settings['marketing_permissions'][] = array(
						'marketing_permission_id' => '496c25fb49',
						'enabled' => true,
					);
				}
				// GDPR-permissie 'commerciële berichten' activeren
				if ( isset( $_POST['marketing'] ) and $_POST['marketing'] === 'yes' ) {
					$settings['marketing_permissions'][] = array(
						'marketing_permission_id' => 'c1cbf23458',
						'enabled' => true,
					);
				}
				break;
				
			// Oxfam-Wereldwinkels Scholen
			case '66358ad206':
				// GDPR-permissie 'scholennieuwsbrief' activeren, tenzij expliciet geweigerd
				if ( ! ( isset( $_POST['scholennieuwsbrief'] ) and $_POST['scholennieuwsbrief'] === 'no' ) ) {
					$settings['marketing_permissions'][] = array(
						'marketing_permission_id' => '95c61bd28f',
						'enabled' => true,
					);
				}
				break;
				
			// Oxfam Fair Trade B2B
			case '5c591564e3':
				// GDPR-permissie 'Email' altijd activeren
				$settings['marketing_permissions'][] = array(
					'marketing_permission_id' => '304ac42a1d',
					'enabled' => true,
				);
				break;
		}
		
		$interest_id = false;
		switch ( $_POST['gift'] ) {
			case 'chocolade':
			case 'biochocolade':
				$interest_id = '06e8fd3def';
				break;
			case 'choco':
				$interest_id = '412f06881d';
				break;
			case 'koffie':
				$interest_id = 'e6b6ad17fe';
				break;
			case 'wijn':
				$interest_id = 'fc91138f9a';
				break;
			case 'het Partnerfonds':
				$interest_id = 'ba909a267b';
				break;
		}
		
		if ( $interest_id ) {
			// Wordt automatisch met reeds bestaande interesses gemerged
			$settings['interests'] = array( $interest_id => true );
		}
		
		$retrieve = $MailChimp->get( 'lists/'.$list_id.'/members/'.md5( $email ) );
		
		if ( $_POST['newsletter'] === 'yes' ) {
			
			if ( $retrieve['status'] !== 404 ) {
				
				$settings['merge_fields'] = array();
				
				// Werk deze velden enkel bij indien ze nog leeg zijn in MailChimp
				$fields = array( 'FNAME', 'LNAME', 'COMPANY', 'ZIP', 'GENDER', 'SHOP', 'LANGUAGE' );
				foreach ( $fields as $key ) {
					if ( trim( $retrieve['merge_fields'][ $key ] ) === '' ) {
						$variable = strtolower( $key );
						// Let op het dubbele dollarteken!
						$settings['merge_fields'][ $key ] = $$variable;
					}
				}
				
				// Werk deze velden altijd bij (op voorwaarde dat ze niet leeg zijn in $_POST!)
				$fields = array( 'SCHOOL', 'MINUTES', 'PERSONS' );
				foreach ( $fields as $key ) {
					$variable = strtolower( $key );
					// Let op het dubbele dollarteken!
					if ( empty( trim( $$variable ) ) ) {
						$settings['merge_fields'][ $key ] = $$variable;
					}
				}
				
				if ( $retrieve['status'] !== 'subscribed' ) {
					// De statussen 'cleaned', 'unsubscribed' en 'pending' moeten we opnieuw inschrijven via opt-in (anders compliance error)
					$settings['status'] = 'pending';
					// In dit geval mogen we de oorsprong ook overschrijven met de nieuwe source!
					$settings['merge_fields']['SOURCE'] = $source;
				}
				
				// Werk de bestaande abonnee bij
				$update = $MailChimp->patch( 'lists/'.$list_id.'/members/'.md5( $email ), $settings );
				
				if ( $update['status'] === 'subscribed' ) {
					
					$response['status'] = 'updated';
					// Dit zou de datum moeten geven sinds wanneer het e-mailadres geabonneerd is ...
					$response['member_since'] = $update['timestamp_opt'];
					
				} elseif ( $update['status'] === 'pending' ) {
					
					$response['status'] = 'resubscribed';
					// Double opt-in wordt automatisch verstuurd door MailChimp, informeer de gebruiker!
					
				} else {
					
					$response['status'] = 'update error';
					$response['error'] = $update['detail'];
					
				}
				
			} else {
				
				$settings['email_address'] = $email;
				$settings['status'] = 'subscribed';
				// @toCheck: Is het helemaal geen probleem als we velden doorgeven die niet gedefinieerd zijn op de lijst, zoals 'SCHOOL' of 'LANGUAGE'?
				$settings['merge_fields'] = array(
					'FNAME' => $fname,
					'LNAME' => $lname,
					'COMPANY' => $company,
					'SCHOOL' => $school,
					'ZIP' => $zip,
					'GENDER' => $gender,
					'SHOP' => $shop,
					'SOURCE' => $source,
					'LANGUAGE' => $language,
					'MINUTES' => $minutes,
					'PERSONS' => $persons,
				);
				
				// Volledig nieuwe member registreren met alle velden én oorsprong
				$create = $MailChimp->post( 'lists/'.$list_id.'/members', $settings );
				
				if ( $create['status'] === 'subscribed' ) {
					
					$response['status'] = 'subscribed';
					
				} else {
					
					// Reden dat dit vaker en vaker voorkomt zijn 'permanent verwijderde adressen' die wel een 404 genereren maar toch niet zomaar opnieuw geabonneerd kunnen worden!
					// Zet de status op 'pending', zodat we een double opt-in uitlokken, en probeer het opnieuw
					$settings['status'] = 'pending';
					$create = $MailChimp->post( 'lists/'.$list_id.'/members', $settings );
					
					if ( $create['status'] === 'pending' ) {
						
						$response['status'] = 'subscribed as pending';
						file_put_contents( dirname( ABSPATH, 1 )."/mailchimp-logs-".date('Y').".csv", date_i18n('d/m/Y H:i:s')."\t".$create['detail']."\n", FILE_APPEND );
						
					} else {
						
						$response['status'] = 'create error';
						// @toDo: Formulierlink variabel maken volgens gekozen list-ID
						if ( ( stristr( $create['detail'], 'cannot be re-imported' ) or stristr( $create['detail'], 'list member in compliance state' ) ) and $list_id === '5cce3040aa' ) {
							$signup_url = 'https://oxfamwereldwinkels.us3.list-manage.com/subscribe?u=d66c099224e521aa1d87da403&id='.$list_id.'&FNAME='.$fname.'&LNAME='.$lname.'&EMAIL='.$email;
							$response['error'] = 'Je was eerder al eens geabonneerd op ons Digizine maar schreef je uit. Om misbruik tegen te gaan dien je opnieuw in te schrijven <a href="'.$signup_url.'" target="_blank">via het reguliere formulier</a>.';
						} else {
							$response['error'] = $create['detail'];
						}
						file_put_contents( dirname( ABSPATH, 1 )."/mailchimp-logs-".date('Y').".csv", date_i18n('d/m/Y H:i:s')."\t".$create['detail']."\n", FILE_APPEND );
						
					}
				}
				
			}
			
		} else {
			
			$response['status'] = 'did not sign up';
			
		}
		
		$notes = array();
		$segment_ids = array();
		
		switch ( $source ) {
			case 'webshop':
				$segment_ids[] = '48330';
				$notes[] = $fname.' plaatste een online bestelling bij '.$shop.'.';
				break;
				
			case 'klantenkaart':
				$segment_ids[] = '48338';
				$notes[] = $fname.' ruilde in OWW '.$shop.' een klantenkaart in voor '.$_POST['gift'].' (ingegeven door Copain-gebruiker '.$_POST['copain_user'].').';
				break;
				
			case 'scholenactie2021':
				$segment_ids[] = '48390';
				$notes[] = $fname.' nam met '.$school.' deel aan de scholenactie van 2021.';
				break;
				
			case 'weekvandefairtrade2021':
				$segment_ids[] = '48402';
				$notes[] = $email.' organiseerde een koffieklets met '.$persons.' personen.';
				break;
				
			case 'lotjeswedstrijd2021':
				$segment_ids[] = '48326';
				$notes[] = $fname.' nam deel aan de lotjeswedstrijd.';
				break;
				
			case 'proefpakket':
				$segment_ids[] = '48398';
				$notes[] = $fname.' bestelde voor '.$company.' een OFT-proefpakket.';
				break;
		}
		
		// Stel alle tags in op de abonnee
		foreach ( $segment_ids as $segment_id ) {
			// Tag toevoegen door abonnee in statisch segment te stoppen
			$tag = $MailChimp->post( 'lists/'.$list_id.'/segments/'.$segment_id, array(
				'members_to_add' => array( $email ),
			) );
			
			// Check of de tag effectief goed toegevoegd werd
			// write_log( print_r( $tag['total_added'], true ) );
		}
		
		// Plaats alle notities op het profiel van de abonnee
		foreach ( $notes as $note ) {
			$MailChimp->post( 'lists/'.$list_id.'/members/'.md5( $email ).'/notes', array( 'note' => $note ) );
		}
		
		$str = date_i18n('d/m/Y H:i:s')."\t".$_SERVER['REMOTE_ADDR']."\t".$email.": ".$response['status']."\t".$source."\t".$list_id."\t".$origin."\n";
		file_put_contents( dirname( ABSPATH, 1 )."/mailchimp-logs-".date('Y').".csv", $str, FILE_APPEND );
		
	}
	
	echo json_encode( $response );
	
?>