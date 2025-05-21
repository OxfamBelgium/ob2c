<?php
	
	if ( ! defined('ABSPATH') ) exit;
	
	################
	# REGIO BRUGGE #
	################
	
	add_action( 'init', 'delay_actions_and_filters_till_load_completed_25' );
	
	function delay_actions_and_filters_till_load_completed_25() {
		if ( get_current_blog_id() === 25 ) {
			// Voeg ondersteuning toe voor XML-import Adsolut
			add_action( 'woocommerce_order_actions', 'brugge_add_order_status_changing_actions', 20, 1 );
			add_action( 'woocommerce_order_action_oxfam_generate_xml', 'brugge_create_xml_for_adsolut' );
			add_action( 'ob2c_after_attach_picklist_to_email', 'brugge_create_xml_for_adsolut' );
		}
	}
	
	function brugge_add_order_status_changing_actions( $actions ) {
		$actions['oxfam_generate_xml'] = 'Regenereer XML voor Adsolut';
		return $actions;
	}
	
	function brugge_create_xml_for_adsolut( $wc_order ) {
		$xml = new SimpleXMLElement('<order/>');
		$orderrec = $xml->addChild('orderrec');
		
		$location = 'Brugge';
		$shop_codes = array( 'Brugge' => 'C1', 'Knokke' => 'C2', 'Gistel' => 'C4' );
		
		// Winkelcode variëren volgens afhaalpunt
		if ( $wc_order->has_shipping_method('local_pickup_plus') ) {
			$shipping_methods = $wc_order->get_shipping_methods();
			$shipping_method = reset( $shipping_methods );
			$pickup_location = ob2c_get_pickup_location_name( $shipping_method, false );
			foreach ( $shop_codes as $shop_location => $shop_code ) {
				if ( stristr( $pickup_location, $shop_location ) ) {
					$location = $shop_location;
				}
			}
		}
		$orderrec->addChild( 'boeken_code', $shop_codes[ $location ] );
		
		$client_number_key = 'blog_'.get_current_blog_id().'_client_number';
		$customer = new WC_Customer( $wc_order->get_customer_id() );
		if ( ! empty( $wc_order->get_meta( $client_number_key ) ) ) {
			$client_number_adsolut = $wc_order->get_meta( $client_number_key );
		} elseif ( $customer and ! empty( $customer->get_meta( $client_number_key ) ) ) {
			$client_number_adsolut = $customer->get_meta( $client_number_key );
		} else {
			// Nieuw nummer aanmaken in webshop
			$client_number_adsolut = get_option( 'ob2c_last_local_client_number', 900000 ) + 1;
			update_option( 'ob2c_last_local_client_number', $client_number_adsolut, false );
			
			// Enkel indien de gebruiker ingelogd was, kunnen we het klantnummer opslaan!
			if ( $customer ) {
				$customer->update_meta_data( 'blog_'.get_current_blog_id().'_client_number', $client_number_adsolut );
			}
		}
		$orderrec->addChild( 'relaties_code', $client_number_adsolut );
		
		// Opgepast: indien een waarde leeg is, wordt er een node geopend i.p.v. toegevoegd! ENKEL IN HTML PREVIEW
		$orderrec->addChild( 'naam', $wc_order->get_billing_first_name().' '.$wc_order->get_billing_last_name() );
		$orderrec->addChild( 'adres1', $wc_order->get_billing_address_1() );
		$orderrec->addChild( 'importland', $wc_order->get_billing_country() );
		$orderrec->addChild( 'importpostcode', $wc_order->get_billing_postcode() );
		$orderrec->addChild( 'importgemeente', $wc_order->get_billing_city() );
		$orderrec->addChild( 'landen_code', $wc_order->get_billing_country() );
		$orderrec->addChild( 'datum', $wc_order->get_date_created()->date_i18n('d/m/Y') );
		$orderrec->addChild( 'omschr', $wc_order->get_order_number() );
		
		if ( $client_number_adsolut > 900000 ) {
			$orderrec->addChild( 'relaties_naam', $wc_order->get_billing_first_name().' '.$wc_order->get_billing_last_name() );
			$orderrec->addChild( 'relaties_adres1', $wc_order->get_billing_address_1() );
			$orderrec->addChild( 'relaties_postcode', $wc_order->get_billing_postcode() );
			$orderrec->addChild( 'relaties_gemeente', $wc_order->get_billing_city() );
			// BTW-nodes weglaten indien niet van toepassing?
			$orderrec->addChild( 'relaties_btwnr', '' );
			$orderrec->addChild( 'relaties_landen_code_0', 'BE' );
			// P = Particulier, H = Handelaar, I = Intracommunautaire
			$orderrec->addChild( 'relaties_btwregimes_btwregime', 'P' );
			// N - Nederlands, F - Frans, E - Engels, D - Duits
			$orderrec->addChild( 'relaties_taalcodes_taalcode', 'N' );
			$orderrec->addChild( 'relaties_email', $wc_order->get_billing_email() );
			$orderrec->addChild( 'relaties_telefoon', $wc_order->get_billing_phone() );
		}
		
		$details = $orderrec->addChild('details');
		foreach ( $wc_order->get_items() as $item ) {
			$product = $item->get_product();
			if ( ! $product ) {
				continue;
			}
			$detail = $details->addChild('detail');
			$sku = ( ! empty( $product->get_meta('_shopplus_code') ) ) ? $product->get_meta('_shopplus_code') : $product->get_sku();
			$detail->addChild( 'artnr', $sku );
			$detail->addChild( 'aantal', $item->get_quantity() );
			// Prijs incl. BTW
			// $detail->addChild( 'brutopr', $item->get_subtotal() + $item->get_total_tax() );
			// Korting incl. BTW
			// $detail->addChild( 'korting1', $item->get_subtotal() + $item->get_subtotal_tax() - ( $item->get_total() + $item->get_total_tax() ) );
		}
		
		$logger = wc_get_logger();
		$context = array( 'source' => 'SimpleXml' );
		// Dit gaat ervan uit dat de Excel reeds aangemaakt werd én het pad correct opgeslagen is!
		$xml_folder_and_file_name = str_replace( '.xlsx', '.xml', $wc_order->get_meta('_excel_file_name') );
		$path = WP_CONTENT_DIR . '/uploads/xlsx/' . $xml_folder_and_file_name;
		
		// Elke node moet op een nieuwe lijn staan in de XML, anders begrijpt Adsolut het niet ...
		// We volgen we oplossing van https://stackoverflow.com/questions/1840148/php-simplexml-new-line
		// dom_import_simplexml creëert een DOMElement i.p.v. DOMDocument, daarom nemen we de 'ownerDocument'-property
		$dom = dom_import_simplexml( $xml )->ownerDocument;
		$dom->formatOutput = true;
		
		$file_handle = fopen( $path, 'w+' );
		if ( fwrite( $file_handle, $dom->saveXML() ) ) {
			$logger->info( $wc_order->get_order_number().": XML creation succeeded", $context );
			$wc_order->add_order_note( 'XML voor Adsolut gegenereerd en opgeslagen in zelfde map als Excel ('.get_picklist_download_link( $wc_order, true ).').', 0, false );
			
			// Doe de upload naar de SFTP-server van OWW Brugge
			// Beter inplannen via Action Scheduler (langere timeout + eventuele retries)?
			try {
				$client = new SFTPClientBruges();
				$parts = explode( '/', $xml_folder_and_file_name );
				$xml_file_name = end( $parts );
				
				// Bij connectieproblemen: neem contact op met Dirk De Wachter van OWW Brugge, die deze SFTP-server beheert
				// Opgelet: de ingebouwde SSH2-extensie van PHP is niet compatibel met ED25519-encryptie, gebruik RSA-ecnryptie van 4096 bits
				$client->auth_key( 'oxfambrugge\owwshop', OWW_BRUGGE_PRIV_KEY_PASS );
				$client->upload_file( $path, $xml_file_name );
				$client->disconnect();
				
				$wc_order->add_order_note( 'XML voor Adsolut geüpload naar SFTP-server van OWW Brugge.', 0, false );
			} catch ( Exception $e ) {
				$logger->error( $wc_order->get_order_number().": ".$e->getMessage(), $context );
			}
		} else {
			$logger->error( $wc_order->get_order_number().": XML creation failed", $context );
		}
		
		fclose( $file_handle );
	}
	
	class SFTPClientBruges {
		private $connection;
		private $sftp;
		
		public function __construct( $host = 'andries.oxfambrugge.be', $port = 2323 ) {
			$this->host = $host;
			$this->port = $port;
			
			$this->connection = @ssh2_connect( $this->host, $this->port, array( 'hostkey' => 'ssh-rsa' ) );
			if ( ! $this->connection ) {
				throw new Exception( "Failed to connect to ".$this->host." on port ".$this->port );
			}
		}
		
		public function auth_password( $username, $password ) {
			if ( ! @ssh2_auth_password( $this->connection, $username, $password ) ) {
				throw new Exception( "Failed to authenticate with user '".$username."' and password" ) ;
			}
			
			$this->sftp = @ssh2_sftp( $this->connection );
			if ( ! $this->sftp ) {
				throw new Exception( "Could not initialize SFTP subsystem" );
			}
		}
		
		public function auth_key( $username, $password ) {
			if ( $this->host === 'andries.oxfambrugge.be' ) {
				$pub_key_path = ABSPATH . '../oww-brugge.pub';
				$priv_key_path = ABSPATH . '../oww-brugge';
			} else {
				$pub_key_path = false;
				$priv_key_path = false;
			}
			
			if ( ! ssh2_auth_pubkey_file( $this->connection, $username, $pub_key_path, $priv_key_path, $password ) ) {
				throw new Exception( "Failed to authenticate with user '".$username."' and SSH key" );
			}
			
			$this->sftp = @ssh2_sftp( $this->connection );
			if ( ! $this->sftp ) {
				throw new Exception( "Could not initialize SFTP subsystem" );
			}
		}
		
		public function upload_file( $local_file, $remote_file ) {
			$logger = wc_get_logger();
			$context = array( 'source' => 'SFTPClientBruges' );
			$logger->info( "Uploading [".$local_file."] to [".$remote_file."] ...", $context );
			
			$realpath = ssh2_sftp_realpath( $this->sftp, $remote_file );
			$stream = @fopen( "ssh2.sftp://{$this->sftp}{$realpath}", 'w' );
			if ( ! $stream ) {
				throw new Exception( "Could not open remote file: ".$realpath );
			}
			
			$data_to_send = @file_get_contents( $local_file );
			if ( $data_to_send === false ) {
				throw new Exception( "Could not open local file: ".$local_file );
			}
			
			if ( @fwrite( $stream, $data_to_send ) === false ) {
				throw new Exception( "Could not send data from file: ".$local_file );
			}
			@fclose($stream);
			
			$logger->info( $remote_file." uploaded to ".$this->host, $context );
		}
		
		public function disconnect() {
			@ssh2_disconnect( $this->connection );
		}
	}