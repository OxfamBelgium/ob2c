<?php
	
	if ( ! defined('ABSPATH') ) exit;
	
	function get_external_wpsl_store( $shop_node ) {
		$store_data = false;
		$uri = 'oxfambelgie.be/api/v1/stores';
		$shop_id = intval( $shop_node );
		$context = array( 'source' => 'Drupal API' );
		
		if ( $shop_id > 0 ) {
			if ( false === ( $store_data = get_site_transient( $shop_id.'_store_data' ) ) ) {
				$response = wp_remote_get( 'https://'.$uri.'/'.$shop_id );
				
				if ( wp_remote_retrieve_response_code( $response ) === 200 ) {
					// Zet het JSON-object om in een PHP-array
					$store_data = json_decode( wp_remote_retrieve_body( $response ), true );
					set_site_transient( $shop_id.'_store_data', $store_data, DAY_IN_SECONDS );
				} else {
					$logger = wc_get_logger();
					$extra_info = '';
					if ( is_wp_error( $response ) ) {
						$extra_info = ': '.$response->get_error_message();
					}
					$logger->notice( 'Could not retrieve shop data for ID '.$shop_id.$extra_info, $context );
				}
			}
		}
		
		return $store_data;
	}
	
	function get_external_wpsl_stores( $page = 0 ) {
		$uri = 'oxfambelgie.be/api/v1/stores';
		$context = array( 'source' => 'Drupal API' );
		// Doet niks (altijd per 50)
		$per_page = 50;
		
		// Enkel gepubliceerde winkels zijn beschikbaar via API, net wat we willen!
		$response = wp_remote_get( 'https://'.$uri.'?per_page='.$per_page.'&page='.$page );
		
		if ( wp_remote_retrieve_response_code( $response ) === 200 ) {
			// Zet het JSON-object om in een PHP-array
			$stores = json_decode( wp_remote_retrieve_body( $response ), true );
			
			if ( $page === 0 ) {
				$extra_stores = $stores;
				// Helaas geen header die het totaal aantal pagina's aangeeft ...
				while ( count( $extra_stores ) === $per_page ) {
					$page++;
					$extra_stores = get_external_wpsl_stores( $page );
					$stores = array_merge( $stores, $extra_stores );
				}
			}
		} else {
			$logger = wc_get_logger();
			$logger->critical( 'Could not retrieve shops on page '.$page, $context );
		}
		
		return $stores;
	}
	
	function get_external_partner( $partner_name, $domain = 'www.oxfamfairtrade.be/nl' ) {
		$partner_data = array();
		$partner_slug = sanitize_title( $partner_name );
		
		if ( false === ( $partner_data = get_site_transient( $partner_slug.'_partner_data' ) ) ) {
			// API zoekt standaard enkel naar objecten met status 'publish'
			// API is volledig publiek, dus geen authorization header nodig
			$response = wp_remote_get( 'https://'.$domain.'/wp-json/wp/v2/partners/?slug='.$partner_slug );
			
			// Log alles op de hoofdsite
			switch_to_blog(1);
			$logger = wc_get_logger();
			$context = array( 'source' => 'WordPress API' );
			
			if ( wp_remote_retrieve_response_code( $response ) === 200 ) {
				// Zet het JSON-object om in een PHP-array
				$matching_partners = json_decode( wp_remote_retrieve_body( $response ), true );
				
				if ( count( $matching_partners ) > 1 ) {
					$logger->warning( 'Multiple partners found for '.$partner_slug, $context );
				} else {
					if ( count( $matching_partners ) === 1 ) {
						$partner_data = $matching_partners[0];
						
						// Fallback voor OWW-partnerpagina's waar 'type' nog de partnercategorie bevat
						if ( in_array( $partner_data['type'], array( 'A', 'B' ) ) ) {
							$partner_data['type'] = 'partner';
						}
						
						$logger->info( 'Partner data for '.$partner_slug.' cached in transient', $context );
					} else {
						$partner_data = array( 'type' => 'not-found' );
						// Vermijd dat we de data telkens opnieuw blijven ophalen, ze bestaat wellicht gewoon niet
						$logger->notice( 'No partner data found for '.$partner_slug.', but still cached in transient', $context );
					}
					set_site_transient( $partner_slug.'_partner_data', $partner_data, DAY_IN_SECONDS );
				}
			} else {
				$logger->error( 'Could not retrieve partner for '.$partner_slug, $context );
			}
			
			restore_current_blog();
		}
		
		return $partner_data;
	}
	
	// Antwoordt met een array indien gevonden
	// Antwoordt met 'not-found' indien mislukt (en wellicht normaal, dus in cache plaatsen)
	// Antwoordt met false indien mislukt (en wellicht abnormaal, dus geen caching)
	function get_external_product( $product, $domain = 'oxfamfairtrade.be' ) {
		$logger = wc_get_logger();
		$context = array( 'source' => 'WooCommerce API' );
		
		if ( ! $product instanceof WC_Product ) {
			$logger->warning( 'No valid product object provided', $context );
			return false;
		}
		
		// Check of het product nog niet gecached werd
		if ( false !== ( $oft_quality_data = get_site_transient( $product->get_sku().'_quality_data' ) ) ) {
			return $oft_quality_data;
		}
		
		if ( $domain = 'oxfamfairtrade.be' ) {
			$base = 'https://www.'.$domain.'/wp-json/wc/v3';
			$key = OFT_WC_KEY;
			$secret = OFT_WC_SECRET;
		} else {
			$logger->warning( 'No valid domain set', $context );
			return false;
		}
		
		$api_url = $base.'/products?consumer_key='.$key.'&consumer_secret='.$secret.'&sku='.$product->get_sku().'&lang=nl';
		$oft_quality_data = parse_external_product_api_response( wp_remote_get( $api_url ), $product, $api_url );
		
		if ( $oft_quality_data !== false ) {
			set_site_transient( $product->get_sku().'_quality_data', $oft_quality_data, DAY_IN_SECONDS );
		}
		
		return $oft_quality_data;
	}
	
	function parse_external_product_api_response( $response, $product, $api_url ) {
		$logger = wc_get_logger();
		$context = array( 'source' => 'WooCommerce API' );
		$oft_quality_data = 'not-found';
		
		if ( wp_remote_retrieve_response_code( $response ) === 200 ) {
			$found_products = json_decode( wp_remote_retrieve_body( $response ) );
			if ( count( $found_products ) > 0 ) {
				if ( count( $found_products ) > 1 ) {
					$logger->error( 'Multiple products found for SKU '.$product->get_sku(), $context );
					return false;
				}
				
				$oft_quality_data = array();
				$oft_product = reset( $found_products );
				
				// Stop voedingswaarden én ingrediënten in een array met als keys de namen van de eigenschappen
				$food_api_labels = get_food_api_labels();
				foreach ( $oft_product->meta_data as $meta_data ) {
					// Functie array_key_exists() werkt ook op objecten
					if ( array_key_exists( $meta_data->key, $food_api_labels ) ) {
						$oft_quality_data['food'][ $meta_data->key ] = $meta_data->value;
					}
				}
				
				// Stop allergenen in een array met als keys de slugs van de allergenen
				foreach ( $oft_product->product_allergen as $product_allergen ) {
					$oft_quality_data['allergen'][ $product_allergen->slug ] = $product_allergen->name;
				}
			} elseif ( strpos( $api_url, '&status=trash' ) === false ) {
				// REST API met search naar SKU vindt enkel gepubliceerde producten!
				// Zie https://github.com/kloon/WooCommerce-REST-API-Client-Library/issues/7
				// Indien het product inmiddels verwijderd is, moeten we expliciet zoeken naar de status 'trash'
				// Om loop te vermijden moeten we checken of de parameter al niet toegevoegd werd
				$api_url .= '&status=trash';
				$oft_quality_data = parse_external_product_api_response( wp_remote_get( $api_url ), $product, $api_url );
			}
		} else {
			$logger->error( 'Response received for SKU '.$product->get_sku().': '.wp_remote_retrieve_response_message( $response ), $context );
			return false;
		}
		
		return $oft_quality_data;
	}