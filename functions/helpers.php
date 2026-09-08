<?php
	
	if ( ! defined('ABSPATH') ) exit;
	
	// $args = array( 'site_id' => 80 );
	// clean_old_product_terms_callback( $args );
	add_action( 'clean_old_product_terms', 'clean_old_product_terms_callback', 10, 1 );
	
	function clean_old_product_terms_callback( $args ) {
		if ( get_site( $args['site_id'] ) !== NULL ) {
			switch_to_blog( $args['site_id'] );
		
			$cnt = 0;
			$start = microtime(true);
			$to_delete = array(
				'pa_bio',
				'pa_choavl',
				'pa_ean',
				'pa_eenheid',
				'pa_ener',
				'pa_eprijs',
				'pa_fairtrade',
				'pa_famscis',
				'pa_fapucis',
				'pa_fasat',
				'pa_fat',
				'pa_fibtg',
				'pa_ompak',
				'pa_polyl',
				'pa_pro',
				'pa_salteq',
				'pa_shopplus',
				'pa_starch',
				'pa_sugar',
				'product_allergen',
				'product_grape',
				'product_recipe',
				'product_taste',
			);
			
			foreach ( $to_delete as $taxonomy ) {
				// Anders vinden we niks, ook al zwerven ze nog rond in de database!
				register_taxonomy( $taxonomy, 'product' );
				
				$terms = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false ) );
				foreach ( $terms as $term ) {
					$name = $term->name;
					if ( wp_delete_term( $term->term_id, $taxonomy ) ) {
						$cnt++;
					}
				}
			}
			write_log( get_bloginfo('name').": deleted ".$cnt." terms in ".number_format( microtime(true)-$start, 2, ',', '.' )." seconds" );
			
			restore_current_blog();
		}
		
		if ( $args['site_id'] < 86 ) {
			$args['site_id'] += 1;
			if ( as_schedule_single_action( strtotime(), 'clean_old_product_terms', array( 'args' => $args ), 'Cleanup' ) > 0 ) {
				write_log("Cleanup for site-ID ".$args['site_id']." scheduled");
			}
		} else {
			write_log("Cleanup terminated");
		}
	}
	
	// Voorbereidende functie om de API voor shopinfo te switchen van de oude WordPress naar de nieuwe Drupal
	// Verwacht een lijst WP_Sites, bv. get_sites( array( 'path__not_in' => array('/') ) );
	function migrate_store_settings( $sites ) {
		// Lijst die post-ID's van oxfamwereldwinkels.be vertaalt naar nodes van oxfambelgie.be
		// Opgelet: komt zowel voor in 'oxfam_shop_node'-optie als in afhaalinstellingen (indien meerdere winkels per webshop)
		// We doen geen koppeling o.b.v. naam, omdat we daar niet altijd consequent zijn, terwijl deze ID's in principe nooit wijzigen
		// Tenzij we natuurlijk nog eens beslissen om onze sites from scratch opnieuw te bouwen :)
		// Wel oppassen als je een winkelpagina volledig zou wissen en opnieuw aanmaken, dan wijzigt de node!
		$shop_post_ids_to_nodes = array(
			3216 => 207,
			3217 => 208,
			3218 => 209,
			3219 => 210,
			3220 => 211,
			3226 => 212,
			3228 => 213,
			3230 => 214,
			3232 => 215,
			3235 => 216,
			3237 => 217,
			3239 => 218,
			3241 => 219,
			3243 => 220,
			3245 => 221,
			3247 => 222,
			3249 => 223,
			3251 => 224,
			3252 => 225,
			3253 => 226,
			3259 => 228,
			3261 => 229,
			3300 => 230,
			3302 => 231,
			3304 => 232,
			3305 => 233,
			3307 => 234,
			3316 => 235,
			3317 => 236,
			3318 => 237,
			3321 => 238,
			3331 => 239,
			3334 => 240,
			3335 => 241,
			3338 => 242,
			3346 => 243,
			3347 => 244,
			3350 => 245,
			3353 => 246,
			3356 => 247,
			3362 => 248,
			3365 => 249,
			3368 => 250,
			3371 => 251,
			3374 => 252,
			3377 => 253,
			3380 => 254,
			3383 => 255,
			3386 => 256,
			3387 => 257,
			3390 => 258,
			3393 => 259,
			3396 => 260,
			3399 => 261,
			3406 => 263,
			3409 => 264,
			3412 => 265,
			3415 => 266,
			3418 => 267,
			3419 => 268,
			3422 => 269,
			3425 => 270,
			3428 => 271,
			3431 => 272,
			3434 => 273,
			3437 => 274,
			3440 => 275,
			3444 => 277,
			3447 => 278,
			3448 => 279,
			3451 => 280,
			3454 => 281,
			3457 => 282,
			3460 => 283,
			3463 => 284,
			3466 => 285,
			3467 => 286,
			3468 => 287,
			3471 => 288,
			3472 => 289,
			3475 => 290,
			3478 => 291,
			3481 => 292,
			3484 => 293,
			3485 => 294,
			3488 => 295,
			3489 => 296,
			3492 => 297,
			3495 => 298,
			3498 => 299,
			3501 => 300,
			3570 => 301,
			3573 => 302,
			3577 => 303,
			3580 => 304,
			3584 => 305,
			3587 => 306,
			3588 => 307,
			3590 => 308,
			3591 => 309,
			3594 => 310,
			3597 => 311,
			3598 => 312,
			3601 => 313,
			3604 => 314,
			3607 => 315,
			3610 => 316,
			3611 => 317,
			3614 => 318,
			3617 => 319,
			3619 => 320,
			3623 => 321,
			3624 => 322,
			3630 => 323,
			3633 => 324,
			3634 => 325,
			3643 => 326,
			3646 => 327,
			3649 => 328,
			3655 => 330,
			3658 => 331,
			3661 => 332,
			3662 => 333,
			3663 => 334,
			3664 => 335,
			3667 => 336,
			3670 => 337,
			3673 => 338,
			3676 => 339,
			3679 => 340,
			3682 => 341,
			3683 => 342,
			3686 => 343,
			3689 => 344,
			3690 => 345,
			3693 => 346,
			3696 => 347,
			3699 => 348,
			3700 => 349,
			3706 => 350,
			3709 => 351,
			3712 => 352,
			3718 => 353,
			3721 => 354,
			3725 => 355,
			3729 => 357,
			3730 => 358,
			3733 => 359,
			3734 => 360,
			3737 => 361,
			3740 => 362,
			3741 => 363,
			3744 => 364,
			3747 => 365,
			3748 => 366,
			3749 => 367,
			3752 => 368,
			3758 => 369,
			3761 => 370,
			3764 => 371,
			3767 => 372,
			3770 => 373,
			3776 => 374,
			3779 => 375,
			3782 => 376,
			3785 => 377,
			3788 => 378,
			3791 => 379,
			3792 => 380,
			3795 => 381,
			3796 => 382,
			3797 => 383,
			3798 => 384,
			3801 => 385,
			3804 => 386,
			3805 => 387,
			3806 => 388,
			3912 => 389,
			3930 => 390,
			3931 => 391,
			3934 => 392,
			3937 => 393,
			3941 => 394,
			3944 => 395,
			3945 => 396,
			3948 => 397,
			3951 => 398,
			3955 => 399,
			3958 => 400,
			3961 => 401,
			3962 => 402,
			17802 => 403,
			19382 => 404,
			32947 => 405,
		);
		
		$keys_to_modify = array( 'address_1', 'postcode', 'city', 'phone', 'note' );
		
		foreach ( $sites as $site ) {
			switch_to_blog( $site->blog_id );
			
			echo get_option('oxfam_mollie_partner_id').' - '.get_webshop_name().'<br/>';
			$shop_post_id = get_option('oxfam_shop_post_id');
			echo 'Shop post-ID: '.$shop_post_id.'<br/>';
			echo 'Shop node: '.get_option('oxfam_shop_node').'<br/>';
			if ( array_key_exists( $shop_post_id, $shop_post_ids_to_nodes ) ) {
				if ( update_option( 'oxfam_shop_node', $shop_post_ids_to_nodes[ $shop_post_id ] ) ) {
					echo 'Shop node (modified): '.get_option('oxfam_shop_node').'<br/>';
				}
			}
			
			$locations = get_option('woocommerce_pickup_locations');
			if ( is_array( $locations ) ) {
				foreach ( $locations as $location_key => $location ) {
					// Converteer secundaire afhaalpunten
					if ( stristr( $location['note'], ' id=' ) ) {
						// Opletten bij KLT: afhaalpunt Vorselaar heeft geen ID in openingsuren (want ontbreken in OWW-site), handmatig te updaten
						$parts = explode( ' id=', $location['note'] );
						
						if ( isset( $parts[1] ) ) {
							$shop_post_id = str_replace( ']', '', $parts[1] );
							if ( ! is_numeric( $shop_post_id ) ) {
								// Bij externe afhaalpunten vervangen we 'id' gewoon door 'node'
								foreach ( $keys_to_modify as $key_to_modify ) {
									$locations[ $location_key ][ $key_to_modify ] = str_replace( ' id=', ' node=', $locations[ $location_key ][ $key_to_modify ] );
								}
							} else {
								$shop_post_id = intval( $shop_post_id );
								if ( array_key_exists( $shop_post_id, $shop_post_ids_to_nodes ) ) {
									foreach ( $keys_to_modify as $key_to_modify ) {
										$locations[ $location_key ][ $key_to_modify ] = str_replace( ' id='.$shop_post_id, ' node='.$shop_post_ids_to_nodes[ $shop_post_id ], $locations[ $location_key ][ $key_to_modify ] );
									}
								}
							}
						}
					}
				}
				
				if ( update_option( 'woocommerce_pickup_locations', $locations ) ) {
					echo 'Extra locations were modified!<br/>';
					var_dump_pre( $locations );
				}
			}
			
			global $wpdb;
			$wpdb->query( "DELETE FROM `$wpdb->options` WHERE `option_name` LIKE ('jetpack_%')" );
			delete_option('cookie_notice_options');
			delete_option('laatste_registratie_timestamp');
			delete_option('oxfam_zip_codes');
			delete_option('oxfam_shop_post_id');
			
			echo '<br/>';
		}
	}
	
	
	
	#############
	# POSTCODES #
	#############
	
	function get_flemish_zips_and_cities() {
		$zips = get_site_option('oxfam_flemish_zip_codes');
		foreach ( $zips as $zip => $cities ) {
			$parts = explode( '/', $cities );
			foreach ( $parts as $city ) {
				$content[] = array( 'label' => $zip.' '.trim($city), 'value' => $zip );
			}
		}
		return $content;
	}
	
	function get_oxfam_covered_zips() {
		if ( false === ( $zips = get_transient('oxfam_covered_zips') ) ) {
			global $wpdb;
			$zips = array();
			
			// Hou enkel rekening met ingeschakelde zones
			$locations = $wpdb->get_results( "SELECT * FROM ".$wpdb->prefix."woocommerce_shipping_zone_locations LEFT JOIN ".$wpdb->prefix."woocommerce_shipping_zone_methods ON ".$wpdb->prefix."woocommerce_shipping_zone_methods.zone_id = ".$wpdb->prefix."woocommerce_shipping_zone_locations.zone_id WHERE ".$wpdb->prefix."woocommerce_shipping_zone_locations.location_type = 'postcode' AND ".$wpdb->prefix."woocommerce_shipping_zone_methods.is_enabled = 1" );
			
			if ( count( $locations ) > 0 ) {
				foreach ( $locations as $row ) {
					$zips[] = $row->location_code;
				}
				$zips = array_unique( $zips );
				
				// Verwijder de default '9999'-waarde uit ongebruikte verzendmethodes
				if ( ( $key = array_search( '9999', $zips ) ) !== false ) {
					unset( $zips[ $key ] );
				}
				
				sort( $zips, SORT_NUMERIC );
			}
			
			set_transient( 'oxfam_covered_zips', $zips, WEEK_IN_SECONDS );
		}
		
		return $zips;
	}
	
	// Deze actie wordt doorlopen na elke wijziging aan WooCommerce-instellingen
	add_action( 'woocommerce_settings_saved', 'invalidate_custom_transients' );
	// Maar niet bij opslaan verzendzones, daarom ook deze actie
	add_action( 'woocommerce_after_shipping_zone_object_save', 'invalidate_custom_transients' );
	
	function invalidate_custom_transients() {
		if ( delete_transient('oxfam_covered_zips') ) {
			write_log( "Transient 'oxfam_covered_zips' reset in ".get_bloginfo('name') );
		}
	}
	
	
	
	#############
	# SHOP INFO #
	#############
	
	function get_webshop_name( $shortened = false ) {
		$webshop_name = get_bloginfo('name');
		if ( $shortened ) {
			$webshop_name = str_replace( 'Oxfam-Wereldwinkel ', '', $webshop_name );
		}
		return $webshop_name;
	}
	
	function get_webshop_email() {
		return get_option('admin_email');
	}
	
	// Of rechtstreeks ophalen uit WPSL op hoofdniveau?
	function get_shop_name( $atts = [] ) {
		$atts = shortcode_atts( array( 'node' => get_option('oxfam_shop_node') ), $atts );
		// Te integreren in get_oxfam_shop_data()
		$oww_store_data = get_external_wpsl_store( $atts['node'] );
		if ( $oww_store_data !== false ) {
			return 'Oxfam-Wereldwinkel '.str_replace( 'Oxfam-Wereldwinkel ', '', $oww_store_data['title'] );
		} else {
			return false;
		}
	}
	
	// Of rechtstreeks ophalen uit WPSL op hoofdniveau?
	function get_shop_email( $atts = [] ) {
		$atts = shortcode_atts( array( 'node' => get_option('oxfam_shop_node') ), $atts );
		// Te integreren in get_oxfam_shop_data()
		$oww_store_data = get_external_wpsl_store( $atts['node'] );
		if ( $oww_store_data !== false ) {
			return $oww_store_data['location']['mail'];
		} else {
			return false;
		}
	}
	
	function get_shop_contact( $atts = [] ) {
		$atts = shortcode_atts( array( 'node' => get_option('oxfam_shop_node') ), $atts );
		return get_shop_address( $atts )."<br/>".get_oxfam_shop_data( 'telephone', $atts['node'] )."<br/>".get_oxfam_shop_data( 'tax', $atts['node'] );
	}
	
	function get_shop_address( $atts = [] ) {
		$atts = shortcode_atts( array( 'node' => get_option('oxfam_shop_node') ), $atts );
		return get_oxfam_shop_data( 'place', $atts['node'] )."<br/>".get_oxfam_shop_data( 'zipcode', $atts['node'] )." ".get_oxfam_shop_data( 'city', $atts['node'] );
	}
	
	function get_company_and_year() {
		return get_webshop_name().' &copy; 2017-'.date_i18n('Y');
	}
	
	function get_return_terms_disclaimer( $order = false, $short_version = false ) {
		$base_text = __( 'Oxfam-Wereldwinkels hanteert een retourbeleid van 14 dagen voor producten zonder houdbaarheidsdatum. Wil je binnen die termijn je bestelling annuleren of producten terugzenden, %s.', 'oxfam-webshop' );
		
		if ( $order instanceof WC_Order ) {
			// In dit geval tonen we een maillink met het ordernummer in het onderwerp
			$label = __( 'hier', 'oxfam-webshop' );
			
			$return_url = add_query_arg(
				array(
					'subject' => sprintf( __( 'Aanvraag tot retournering van bestelling %s', 'oxfam-webshop' ), $order->get_order_number() ),
					'body' => 'Ik zou graag volgende producten zonder houdbaarheidsdatum retourneren:',
				),
				'mailto:'.get_option('woocommerce_email_from_address')
			);
			$label = '<a href="'.esc_url( $return_url ).'" target="_blank">' . $label . '</a>';
			
			return sprintf( $base_text, sprintf( __( 'klik dan %s', 'oxfam-webshop' ), $label ) ) . ' ' . __( 'Eén van de vrijwilligers van de webshop waar je je bestelling plaatste, neemt eerstdaags met jou contact om de retour af te handelen.', 'oxfam-webshop' );
		} else {
			// In dit geval tonen we een maillink met het ordernummer in het onderwerp
			$label = __( 'retourbeleid', 'oxfam-webshop' );
			if ( $short_version ) {
				// Begin met hoofdletter
				$label = ucfirst( $label );
			}
			
			if ( wc_terms_and_conditions_page_id() > 0 ) {
				// Anchor lijkt te werken in alle subsites (omdat de voorwaarden gekopieerd werden van het hoofdniveau)
				$return_url = get_permalink( wc_terms_and_conditions_page_id() ).'#tab-1494189921569-6-6';
				$label = '<a href="'.esc_url( $return_url ).'">' . $label . '</a>';
			}
			
			if ( $short_version ) {
				return $label;
			} else {
				return sprintf( $base_text, sprintf( __( 'lees dan ons %s', 'oxfam-webshop' ), $label ) );
			}
		}
	}
	
	
	
	###################
	# SHOP PROPERTIES #
	###################
	
	function does_risky_delivery() {
		// Zet 'yes'-waarde om in een echte boolean
		return ( get_option('oxfam_does_risky_delivery') === 'yes' );
	}
	
	function does_home_delivery( $zip = false ) {
		if ( ! $zip ) {
			// Check of de array met postcodes leeg is
			return boolval( get_oxfam_covered_zips() );
		} else {
			// Check of de specifieke postcode voorkomt in het gebied waar de webshop thuislevert
			return in_array( $zip, get_oxfam_covered_zips() );
		}
	}
	
	function does_local_pickup() {
		$pickup_settings = get_option('woocommerce_local_pickup_plus_settings');
		if ( is_array( $pickup_settings ) ) {
			return ( 'yes' === $pickup_settings['enabled'] );
		} else {
			return false;
		}
	}
	
	function does_sendcloud_delivery() {
		if ( does_home_delivery() ) {
			$sendcloud_zone_id = 3;
			$zone = WC_Shipping_Zones::get_zone_by( 'zone_id', $sendcloud_zone_id );
			if ( $zone ) {
				// Enkel actieve methodes meetellen
				$methods = $zone->get_shipping_methods( true );
				if ( count( $methods ) > 0 ) {
					return true;
				} else {
					return false;
				}
			} else {
				return false;
			}
		} else {
			return false;
		}
	}
	
	function is_regional_webshop() {
		// Gentbrugge, Antwerpen, Leuven, Mechelen en Wetteren
		$regions = array( 15, 24, 28, 40, 53 );
		// Opgelet: vergeet de custom orderstatus 'claimed' niet te publiceren naar deze subsites!
		return in_array( get_current_blog_id(), $regions );
	}
	
	
	
	######################
	# PRODUCT PROPERTIES #
	######################
	
	// Kan zowel productobject als post-ID ontvangen
	function is_national_product( $object ) {
		if ( is_main_site() ) {
			// Producten op het hoofdniveau zijn per definitie nationaal!
			return true;
		}
		
		if ( $object instanceof WC_Product ) {
			return ( intval( $object->get_meta('_woonet_network_is_child_site_id') ) === 1 );
		} else {
			return ( intval( get_post_meta( $object, '_woonet_network_is_child_site_id', true ) ) === 1 );
		}
	}
	
	// Kan zowel productobject als post-ID ontvangen
	function is_crafts_product( $object ) {
		if ( $object instanceof WC_Product ) {
			$shopplus = $object->get_meta('_shopplus_code');
		} else {
			$shopplus = get_post_meta( $object, '_shopplus_code', true );
		}
		// Als het ShopPlus-nummer met een M begint, komt het van MDM
		return ( strpos( $shopplus, 'M' ) === 0 );
	}
	
	
	
	#############
	# UTILITIES #
	#############
	
	function get_current_url() {
		$url = ( isset( $_SERVER['HTTPS'] ) and 'on' === $_SERVER['HTTPS'] ) ? 'https' : 'http';
		$url .= '://' . $_SERVER['SERVER_NAME'];
		$url .= in_array( $_SERVER['SERVER_PORT'], array( '80', '443' ) ) ? '' : ':' . $_SERVER['SERVER_PORT'];
		$url .= $_SERVER['REQUEST_URI'];
		return $url;
	}
	
	// Verwissel twee associatieve keys in een array
	function array_swap_assoc( $key1, $key2, $array ) {
		$new_array = array();
		foreach ( $array as $key => $value ) {
			if ( $key == $key1 ) {
				$new_array[ $key2 ] = $array[ $key2 ];
			} elseif ( $key == $key2 ) {
				$new_array[ $key1 ] = $array[ $key1 ];
			} else {
				$new_array[ $key ] = $value;
			}
		}
		return $new_array;
	}
	
	// Creëer een random sequentie
	// Niet gebruiken voor echte beveiliging!
	function generate_pseudo_random_string( $length = 10 ) {
		$characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
		$characters_length = strlen( $characters );
		$random_string = '';
		for ( $i = 0; $i < $length; $i++ ) {
			$random_string .= $characters[ rand( 0, $characters_length - 1 ) ];
		}
		return $random_string;
	}
	
	function ends_with( $haystack, $needle ) {
		return $needle === '' or ( ( $temp = strlen( $haystack ) - strlen( $needle ) ) >= 0 and strpos( $haystack, $needle, $temp ) !== false );
	}
	
	function sort_by_time( $a, $b ) {
		return $b['timestamp'] - $a['timestamp'];
	}
	
	// Verstuur een mail naar de helpdesk uit naam van de lokale webshop
	function send_automated_mail_to_helpdesk( $subject, $body ) {
		if ( wp_get_environment_type() !== 'production' ) {
			$subject = 'TEST - '.$subject.' - NO ACTION REQUIRED';
			// Mails eventueel volledig uitschakelen
			// return;
		}
		
		$headers = array();
		$headers[] = 'From: '.get_webshop_name().' <'.get_option('admin_email').'>';
		$headers[] = 'Content-Type: text/html';
		// $body moét effectief HTML-code bevatten, anders werpt WP Mail Log soms een error op!
		wp_mail( get_staged_recipients('webshop@oft.be'), $subject, $body, $headers );
	}
	
	// Definitie van labels en verplichte voedingswaarden
	function get_food_api_labels() {
		return array(
			'_ingredients' => __( 'Ingrediënten', 'oxfam-webshop' ),
			'_energy' => __( 'Energie', 'oxfam-webshop' ),
			'_fat' => __( 'Vetten', 'oxfam-webshop' ),
			'_fasat' => __( 'waarvan verzadigde vetzuren', 'oxfam-webshop' ),
			'_famscis' => __( 'waarvan enkelvoudig onverzadigde vetzuren', 'oxfam-webshop' ),
			'_fapucis' => __( 'waarvan meervoudig onverzadigde vetzuren', 'oxfam-webshop' ),
			'_choavl' => __( 'Koolhydraten', 'oxfam-webshop' ),
			'_sugar' => __( 'waarvan suikers', 'oxfam-webshop' ),
			'_polyl' => __( 'waarvan polyolen', 'oxfam-webshop' ),
			'_starch' => __( 'waarvan zetmeel', 'oxfam-webshop' ),
			'_fibtg' => __( 'Vezels', 'oxfam-webshop' ),
			'_pro' => __( 'Eiwitten', 'oxfam-webshop' ),
			'_salteq' => __( 'Zout', 'oxfam-webshop' ),
		);
	}
	
	
	
	##############
	# FORMATTING #
	##############
	
	function trim_and_uppercase( $value ) {
		return str_replace( 'Oww ', 'OWW ', implode( '.', array_map( 'ucwords', explode( '.', implode( '(', array_map( 'ucwords', explode( '(', implode( '-', array_map( 'ucwords', explode( '-', mb_strtolower( trim($value) ) ) ) ) ) ) ) ) ) ) );
	}
	
	function format_tax( $value ) {
		$value = str_replace( 'BE', '', $value );
		$value = preg_replace( '/[\s\-\.\/]/', '', $value );
		if ( mb_strlen($value) === 9 ) {
			$value = '0'.$value;
		}
		
		if ( mb_strlen($value) === 10 ) {
			$digit_8 = intval( substr( $value, 0, 8 ) );
			$checksum = 97 - ( $digit_8 - intval( $digit_8 / 97 ) * 97 );
			if ( $checksum === intval( substr( $value, 8, 2 ) ) ) {
				return 'BE '.substr( $value, 0, 4 ).".".substr( $value, 4, 3 ).".".substr( $value, 7, 3 );
			} else {
				return 'INVALID CHECKSUM';
			}
		} elseif ( mb_strlen($value) >= 1 ) {
			return 'INVALID LENGTH';
		} else {
			return '';
		}
	}
	
	function format_account( $iban ) {
		$countries = array( 'BE' => 16, 'NL' => 18 );
		$translate_chars = array(
			'A' => 10,
			'B' => 11,
			'C' => 12,
			'D' => 13,
			'E' => 14,
			'F' => 15,
			'G' => 16,
			'H' => 17,
			'I' => 18,
			'J' => 19,
			'K' => 20,
			'L' => 21,
			'M' => 22,
			'N' => 23,
			'O' => 24,
			'P' => 25,
			'Q' => 26,
			'R' => 27,
			'S' => 28,
			'T' => 29,
			'U' => 30,
			'V' => 31,
			'W' => 32,
			'X' => 33,
			'Y' => 34,
			'Z' => 35,
		);
		
		$iban = str_replace( 'IBAN', '', mb_strtoupper($iban) );
		$iban = preg_replace( '/[\s\-\.\/]/', '', $iban );
		
		if ( array_key_exists( substr( $iban, 0, 2 ), $countries ) and strlen($iban) === $countries[substr( $iban, 0, 2 )] ) {
			$moved_char = substr( $iban, 4 ).substr( $iban, 0, 4 );
			$moved_char_array = str_split($moved_char);
			$controll_string = '';
			
			foreach ( $moved_char_array as $key => $value ) {
				if ( ! is_numeric($moved_char_array[$key]) ) {
					$moved_char_array[$key] = $translate_chars[$moved_char_array[$key]];
				}
				$controll_string .= $moved_char_array[$key];
			}
			
			if ( intval($controll_string) % 97 === 1 ) {
				return substr( $iban, 0, 4 )." ".substr( $iban, 4, 4 )." ".substr( $iban, 8, 4 )." ".substr( $iban, 12, 4 );
			} else {
				return 'INVALID CHECKSUM';
			}
		} else {
			return 'INVALID LENGTH';
		}
	}
	
	function format_place( $value ) {
		return trim_and_uppercase( $value );
	}
	
	function format_zipcode( $value ) {
		// Opgelet: niet-numerieke tekens bewust niet verwijderen, anders problemen met NL-postcodes!
		// Gebruik eventueel WC_Validation::is_postcode( $postcode, $country )
		return trim( $value );
	}
	
	function format_city( $value ) {
		return trim_and_uppercase( $value );
	}
	
	function format_mail( $value ) {
		return mb_strtolower( trim($value) );
	}
	
	function format_headquarter( $value ) {
		return trim_and_uppercase( $value );
	}
	
	// Sta een optionele parameter toe om puntjes te zetten in plaats van spaties (maar: wordt omgezet in streepjes door wc_format_phone() dus niet gebruiken in verkoop!)
	function format_phone_number( $value, $delim = ' ' ) {
		if ( $delim === '.' ) {
			$slash = '/';
		} else {
			$slash = $delim;
		}
		
		// Verwijder alle non-digits
		$value = preg_replace('/[^0-9]/', '', str_replace( '+32', '0032', $value ) );
		
		// Wis Belgische landcodes
		// @toDo: Andere landcodes checken en ook formatteren?
		if ( substr( $value, 0, 4 ) === '0032' ) {
			$value = substr( $value, 4 );
		}
		
		// Voeg indien nodig leading zero toe
		if ( substr( $value, 0, 1 ) !== '0' ) {
			$value = '0' . $value;
		}
		
		if ( strlen( $value ) == 9 ) {
			// Vaste telefoonnummers
			if ( intval( $value[1] ) == 2 or intval( $value[1] ) == 3 or intval( $value[1] ) == 4 or intval( $value[1] ) == 9 ) {
				// Zonenummer van twee cijfers
				$phone = substr( $value, 0, 2 ) . $slash . substr( $value, 2, 3 ) . $delim . substr( $value, 5, 2 ) . $delim . substr( $value, 7, 2 );
			} else {
				// Zonenummer van drie cijfers
				$phone = substr( $value, 0, 3 ) . $slash . substr( $value, 3, 2 ) . $delim . substr( $value, 5, 2 ) . $delim . substr( $value, 7, 2 );
			}
		} elseif ( strlen( $value ) == 10 ) {
			// Mobiele telefoonnummers
			$phone = substr( $value, 0, 4 ) . $slash . substr( $value, 4, 2 ) . $delim . substr( $value, 6, 2 ) . $delim . substr( $value, 8, 2 );
		} else {
			// Wis ongeldige nummers
			if ( is_checkout() ) {
				// Behalve op checkout, want dan triggeren we een obscure 'Gelieve de verplichte velden in te vullen'-foutmelding!
				$phone = $value;
			} else {
				$phone = '';
			}
		}
		
		return $phone;
	}
	
	function format_hour( $value ) {
		if ( strlen($value) === 5 ) {
			// Wordpress: geen wijzigingen meer nodig!
			return $value;
		} elseif ( strlen($value) === 4 ) {
			// Drupal: voeg dubbele punt toe in het midden
			return substr( $value, 0, 2 ) . ':' . substr( $value, 2, 2 );
		} else {
			// Drupal: voeg nul toe vooraan bij ochtenduren
			return '0'.substr( $value, 0, 1 ) . ':' . substr( $value, 1, 2 );
		}
	}
	
	function format_date( $value ) {
		$new_value = preg_replace( '/[\s\-\.\/]/', '', $value );
		if ( strlen($new_value) === 8 ) {
			return substr( $new_value, 0, 2 ) . '/' . substr( $new_value, 2, 2 ) . '/' . substr( $new_value, 4, 4 );
		} elseif ( strlen($new_value) === 0 ) {
			// Ontbrekende datum
			return '';
		} else {
			// Ongeldige datum (dit laat ons toe om het onderscheid te maken!)
			return '31/12/2100';
		}
	}
	
	
	
	#############
	# DEBUGGING #
	#############
	
	// Print variabelen op een duidelijke manier naar een niet-publieke file
	if ( ! function_exists( 'write_log' ) ) {
		function write_log( $log ) {
			if ( defined('WP_DEBUG_LOG') and WP_DEBUG_LOG ) {
				if ( is_array( $log ) or is_object( $log ) ) {
					$log = serialize( $log );
				}
				error_log( "[".date_i18n('d/m/Y H:i:s')."] " . $log . "\n", 3, dirname( ABSPATH, 1 ) . '/activity.log' );
			}
		}
	}
	
	// Overzichtelijke dump naar het scherm
	function var_dump_pre( $variable ) {
		echo '<pre>';
		var_dump( $variable );
		echo '</pre>';
		return;
	}