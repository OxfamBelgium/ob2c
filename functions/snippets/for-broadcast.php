<?php
	
	#######################
	# MAANDELIJKSE IMPORT #
	#######################
	
	// Alle product-ID's in nieuwe, geplande kortingsbonnen lokaal maken
	// Niet langer nodig voor leeggoed / waardebonnen in B2B-kortingsregels: criterium vervangen door regex /(^voeding$|^$)/i op '_tax_class'-metaveld
	$args = array(
		'post_type' => 'shop_coupon',
		'post_status' => 'future',
		// Alternatieve methode (bv. als de bon inmiddels al op publish staat)
		// 'Opgelet: 'post_name__in' kijkt naar de (onzichtbare) originele slug, dit kan afwijken van de (zichtbare) titel indien achteraf gewijzigd!
		// 'post_name__in' => array( '202509-chips' ),
		'posts_per_page' => -1,
	);
	$all_coupons = new WP_Query( $args );
	
	// Beveiliging tegen lokaal proberen maken van kortingsbonnen op hoofdniveau
	if ( $all_coupons->have_posts() and ! is_main_site() ) {
		while ( $all_coupons->have_posts() ) {
			$all_coupons->the_post();
			$keys_to_localize = array( 'product_ids', 'exclude_product_ids', '_wjecf_free_product_ids', 'product_categories', 'exclude_product_categories' );
			
			foreach ( $keys_to_localize as $key ) {
				$global_value = get_post_meta( get_the_ID(), $key, true );
				
				if ( str_contains( $key, '_categories' ) ) {
					if ( is_array( $global_value ) ) {
						translate_main_to_local_ids( get_the_ID(), $key, $global_value, 'product_cat' );
					}
				} else {
					if ( $global_value !== '' ) {
						$global_ids = explode( ',', $global_value );
						translate_main_to_local_ids( get_the_ID(), $key, $global_ids );
					}
				}
			}
			
			write_log( get_bloginfo('name').": made coupon '".get_the_title()."' local" );
		}
		wp_reset_postdata();
	}
	
	
	
	// Alle nieuwe artikels lokaal uit voorraad zetten
	$new_skus = get_site_option( 'oxfam_shop_dashboard_notice_new_products', array() );
	
	if ( count( $new_skus ) > 0 and ! is_main_site() ) {
		foreach ( $new_skus as $sku ) {
			$product_id = wc_get_product_id_by_sku( $sku );
			if ( $product_id ) {
				$product = wc_get_product( $product_id );
				// On first publish wordt nationale voorraadbeheer automatisch ook lokaal geactiveerd
				// Dit moet sowieso op 'no' gezet worden, anders zal het lokale voorraadbeheer niet functioneren!
				$product->set_manage_stock('no');
				$product->set_stock_status('outofstock');
				$product->save();
				write_log( get_bloginfo('name').": stock status of SKU ".$sku." reset to 'outofstock'" );
			}
		}
	}
	
	
	
	// Voorraad overzetten van oud naar nieuw ompaknummer (enkel indien consumenteneenheid identiek gebleven is!)
	// Na afloop plaats je het oude ompaknummer in concept (voor latere definitieve verwijdering)
	$replaced_skus = get_site_option( 'oxfam_shop_dashboard_notice_replaced_products', array() );
	foreach ( $replaced_skus as $old_new ) {
		$parts = explode( '-', $old_new );
		if ( count( $parts ) !== 2 ) {
			continue;
		}
		$old_sku = $parts[0];
		$new_sku = $parts[1];
		
		$product_id = wc_get_product_id_by_sku( $new_sku );
		if ( $product_id ) {
			$product = wc_get_product( $product_id );
			$old_product_id = wc_get_product_id_by_sku( $old_sku );
			if ( $old_product_id ) {
				$old_product = wc_get_product( $old_product_id );
				$product->set_manage_stock('no');
				// Ook publicatiedatum kopiëren zodat het product niet als 'nieuw' verschijnt?
				// $product->set_date_created( $old_product->get_date_created()->__toString() );
				$product->set_stock_status( $old_product->get_stock_status() );
				$product->save();
				write_log( get_bloginfo('name').": stock status '".$old_product->get_stock_status()."' of old SKU ".$old_sku." copied to new SKU ".$new_sku );
			}
		}
	}
	
	
	
	// Voeg nieuwe superadmins toe als beheerder aan alle blogs
	// Dit is enkel nodig opdat de lijst blogs op https://shop.oxfamwereldwinkels.be/wp-admin/admin.php?page=threewp_broadcast_php_code zou verschijnen
	// Zo kunnen zij deze PHP-snippets makkelijk uitvoeren in alle subsites!
	$super_admins = get_super_admins();
	$sites = get_sites();
	foreach ( $sites as $site ) {
		switch_to_blog( $site->blog_id );
		foreach ( $super_admins as $login ) {
			$user = get_user_by( 'login', $login );
			if ( is_user_member_of_blog( $user->ID, $site->blog_id ) ) {
				write_log( 'User '.$login.' is already on '.get_bloginfo('name') );
			} elseif ( add_user_to_blog( $site->blog_id, $user->ID, 'administrator' ) ) {
				write_log( 'User '.$login.' added to '.get_bloginfo('name') );
			}
		}
		restore_current_blog();
	}
	
	
	
	#####################
	# OPKUIS ALLERHANDE #
	#####################
	
	// Verwijder oude bestellingen (> 3 jaar geleden)
	$logger = wc_get_logger();
	$context = array( 'source' => 'Cleanup' );
	$query_args = array(
		// Eventueel limiteren tot een bepaalde status
		// 'status' => 'wc-cancelled',
		// Alle shop_order_refund's op het order worden automatisch mee verwijderd!
		'type' => 'shop_order',
		'date_created' => '<2023-01-01',
		// Als we in blokken van 3 à 4 maanden wissen, moet het lukken om alle orders in alle webshops in één keer te wissen (zonder time-out)
		'limit' => -1,
		// Begin met de oudste orders
		'order' => 'ASC',
		'orderby' => 'date',
	);
	$orders_to_delete = wc_get_orders( $query_args );
	
	$cnt = 0;
	foreach ( $orders_to_delete as $order ) {
		$order_number = $order->get_order_number();
		$order_date = $order->get_date_created()->date_i18n('d/m/Y H:i');
		$file_path = '/uploads/xlsx/'.$order->get_meta('_excel_file_name');
		$option_key = 'number_of_'.$order->get_status().'_orders_deleted';
		
		// Doe een harde delete (dus niet via prullenmand)
		if ( $order->delete(true) ) {
			$cnt++;
			// Registreer hoeveel orders we wissen in de database
			update_site_option( $option_key, get_site_option( $option_key, 0 ) + 1 );
			$logger->info( $order_number . ' (' . $order_date . ') deleted', $context );
			
			// Verwijder ook de map met de pick-Excel automatisch
			// In de praktijk kuisen we de 'xslx'-map reeds handmatig na +/- 6 maanden, dus wellicht zijn alle files al weg
			// Extra check op $file_path om accidenten te vermijden!
			if ( file_exists( WP_CONTENT_DIR . $file_path ) and strlen( $file_path ) === 38 ) {
				$dirname = dirname( WP_CONTENT_DIR . $file_path );
				// Verwijder alle files in de map
				array_map( 'unlink', glob( $dirname . '/*.*' ) );
				// Nu de map leeg is kunnen we rmdir() aanroepen
				if ( rmdir( $dirname ) ) {
					$logger->info( 'Directory ' . $dirname . ' with Excel for ' . $order_number . ' deleted', $context );
				}
			}
		} else {
			$logger->warning( $order_number . ' could not be deleted', $context );
		}
	}
	write_log( get_bloginfo('name').": ".$cnt." orders ".$query_args['date_created']." deleted" );
	
	
	
	// Verwijder ongebruikte gebruikersaccounts
	$args = array(
		'role' => 'customer',
		'meta_query' => array(
		   'relation' => 'OR',
			array(
				'key' => 'wc_last_active',
				'compare' => 'NOT EXISTS',
			),
			array(
				// Pas bijgehouden na upgrade van WooCommerce op 07/10/2020
				// Alle bestaande klanten kregen timestamp '1602028800' tijdens migratie
				'key' => 'wc_last_active',
				// Pas bijgehouden na installatie van WordFence op 21/12/2020
				// Alle bestaande klanten kregen timestamp '1608508800' tijdens migratie
				// 'key' => 'wfls-last-login',
				'value' => strtotime('2023-01-01'),
				'compare' => '<=',
			),
		),
		'number' => -1,
		'order' => 'ASC',
		'orderby' => 'ID',
	);
	$stale_users = new WP_User_Query( $args );
	
	if ( ! function_exists( 'wpmu_delete_user' ) ) {
		require_once ABSPATH . '/wp-admin/includes/ms.php';
	}
	
	foreach ( $stale_users->get_results() as $user ) {
		write_log( "User-ID ".$user->ID." (".$user->user_login."): last active on ".date_i18n( 'd/m/Y', get_user_meta( $user->ID, 'wc_last_active', true ) ) );
		
		$customer_orders = wc_get_orders( array( 'customer_id' => $user->ID, 'limit' => -1 ) );
		if ( count( $customer_orders ) > 0 ) {
			// Er zijn nog orders gelinkt aan de user, account niet wissen
			write_log( "Account tied to ".$user->user_email." has orders left" );
			continue;
		}
		
		$user_to_delete = true;
		$member_sites = get_blogs_of_user( $user->ID );
		foreach ( $member_sites as $site_id => $site ) {
			switch_to_blog( $site_id );
			$roles = get_userdata( $user->ID )->roles;
			if ( count( array_intersect( array( 'administrator', 'shop_manager', 'local_manager', 'local_helper' ), $roles ) ) > 0 ) {
				// User is geen gewone klant, account niet wissen
				write_log( "Account tied to ".$user->user_email." is no ordinary customer in ".$site->path );
				$user_to_delete = false;
			} else {
				$customer_orders = wc_get_orders( array( 'customer_id' => $user->ID, 'limit' => -1 ) );
				if ( count( $customer_orders ) > 0 ) {
					// Er zijn nog orders in een andere webshop gelinkt aan de user, account niet wissen
					write_log( "Account tied to ".$user->user_email." has orders left in ".$site->path );
					$user_to_delete = false;
				}
			}
			restore_current_blog();
		}
		
		if ( count( $member_sites ) === 0 or $user_to_delete ) {
			// User is enkel en alleen lid van deze site (of enkel lid van een inmiddels gearchiveerde webshop)
			write_log( "Account tied to ".$user->user_email." has no orders left and is not a manager, schedule delete ..." );
			if ( wpmu_delete_user( $user->ID ) ) {
				write_log( "User deleted from the entire network!" );
				update_site_option( 'number_of_users_deleted', get_site_option( 'number_of_users_deleted', 0 ) + 1 );
			}
		}
	}
	
	
	
	// Verwijder overtollige BTW-schalen (duiken soms spontaan weer op!)
	$tax_classes = array( 'gereduceerd-tarief', 'nultarief' );
	foreach ( $tax_classes as $tax_class ) {
		// Verwijdert automatisch eventuele tax rates met deze klasse
		if ( WC_Tax::delete_tax_class_by( 'slug', $tax_class ) ) {
			write_log( get_bloginfo('name').": deleted '".$tax_class."' tax class" );
		} else {
			write_log( get_bloginfo('name').": could not delete '".$tax_class."' tax class" );
		}
	}
	
	

	// Verwijder overtollige verzendklasses
	$taxonomy = 'product_shipping_class';
	if ( taxonomy_exists( $taxonomy ) ) {
		$terms = array( 'retourneerbaar' );
		foreach ( $terms as $term ) {
			$term_to_delete = get_term_by( 'slug', $term, $taxonomy );
			if ( $term_to_delete !== false ) {
				if ( wp_delete_term( $term_to_delete->term_id, $taxonomy ) ) {
					write_log( get_bloginfo('name').": deleted '".$term_to_delete->name."' shipping class" );
				} else {
					write_log( get_bloginfo('name').": could not delete '".$term_to_delete->name."' shipping class" );
				}
			} else {
				write_log( get_bloginfo('name').": shipping class '".$term."' not found" );
			}
		}
	}
	
	

	// Verwijder overtollige producttags
	$taxonomy = 'product_tag';
	if ( taxonomy_exists( $taxonomy ) ) {
		$terms = array( 'b2b', 'alcoholische-dranken' );
		foreach ( $terms as $term ) {
			$term_to_delete = get_term_by( 'slug', $term, $taxonomy );
			if ( $term_to_delete !== false ) {
				if ( wp_delete_term( $term_to_delete->term_id, $taxonomy ) ) {
					write_log( get_bloginfo('name').": deleted '".$term_to_delete->name."' product tag" );
				} else {
					write_log( get_bloginfo('name').": could not delete '".$term_to_delete->name."' product tag" );
				}
			} else {
				write_log( get_bloginfo('name').": product tag '".$term."' not found" );
			}
		}
	}
	
	
	
	// Verwijder verouderde taxonomieën en termen (die niet langer als WooCommerce-productattribuut geregistreerd zijn!)
	$cnt = 0;
	$start = microtime(true);
	$to_delete = array( 'pa_bio', 'pa_choavl', 'pa_ean', 'pa_eenheid', 'pa_ener', 'pa_eprijs', 'pa_fairtrade', 'pa_famscis', 'pa_fapucis', 'pa_fasat', 'pa_fat', 'pa_fibtg', 'pa_ompak', 'pa_polyl', 'pa_pro', 'pa_salteq', 'pa_shopplus', 'pa_starch', 'pa_sugar', 'product_allergen', 'product_grape', 'product_recipe', 'product_taste' );
	foreach ( $to_delete as $taxonomy ) {
		// Anders vinden we niks, ook al zwerven ze nog rond in de database!
		register_taxonomy( $taxonomy, 'product' );
		$terms = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false ) );
		foreach ( $terms as $term ) {
			if ( wp_delete_term( $term->term_id, $taxonomy ) ) {
				$cnt++;
			}
		}
	}
	write_log( get_bloginfo('name').": deleted ".$cnt." terms in ".number_format( microtime(true)-$start, 2, ',', '.' )." seconds" );
	
	

	// Werk een productcategorie bij
	$taxonomy = 'product_cat';
	if ( taxonomy_exists( $taxonomy ) ) {
		$terms = array(
			'wonen-mode-speelgoed' => 'Wonen',
			'geschenken' => 'Geschenken & wenskaarten',
		);
		foreach ( $terms as $old_term_slug => $new_term_name ) {
			$term_to_update = get_term_by( 'slug', $old_term_slug, $taxonomy );
			if ( $term_to_update !== false ) {
				if ( is_wp_error( wp_update_term( $term_to_update->term_id, $taxonomy, array( 'slug' => sanitize_title( $new_term_name ), 'name' => $new_term_name ) ) ) ) {
					write_log("COULD NOT UPDATE ".$term_to_update->name);
				}
			} else {
				write_log( get_bloginfo('name').": product category '".$old_term_slug."' not found" );
			}
		}
	}
	
	

	// Verwijder overtollige productcategorieën (gaat niet automatisch bij syncen taxonomie via Broadcast)
	// Import maakt onbestaande categorieën automatisch aan, als er iets misliep in de mapping moet je dus wat opkuis doen
	$taxonomy = 'product_cat';
	if ( taxonomy_exists( $taxonomy ) ) {
		$terms = array( 'voeding' );
		foreach ( $terms as $term ) {
			$term_to_delete = get_term_by( 'slug', $term, $taxonomy );
			if ( $term_to_delete !== false ) {
				if ( wp_delete_term( $term_to_delete->term_id, $taxonomy ) ) {
					write_log( get_bloginfo('name').": deleted '".$term_to_delete->name."' product category" );
				} else {
					write_log( get_bloginfo('name').": could not delete '".$term_to_delete->name."' product category" );
				}
			} else {
				write_log( get_bloginfo('name').": product category '".$term."' not found" );
			}
		}
	}
	
	

	// Verwijder uitgefaseerde partners
	$taxonomy = 'product_partner';
	if ( taxonomy_exists( $taxonomy ) ) {
		$terms = array( 'upromabio', 'oromia', 'pinora', 'konafcoop', 'eksteenkuil', 'groot-eiland-farm', 'progressive-farmers', 'suikerpartners-uit-india', 'canaan', 'poo-pluk-saparot', 'samroiyod', 'tofta', 'fundopo', 'capim', 'educe', 'miel-del-sul', 'tzeltal-tzotzil', 'cecocafen', 'del-campo', 'ucasa', 'caic', 'lipimavida', 'fapecafes', 'cac-divisoria-ltda', 'naranjillo' );
		foreach ( $terms as $term ) {
			$term_to_delete = get_term_by( 'slug', $term, $taxonomy );
			if ( $term_to_delete !== false ) {
				if ( wp_delete_term( $term_to_delete->term_id, $taxonomy ) ) {
					write_log( get_bloginfo('name').": deleted '".$term_to_delete->name."' partner" );
				} else {
					write_log( get_bloginfo('name').": could not delete '".$term_to_delete->name."' partner" );
				}
			} else {
				write_log( get_bloginfo('name').": partner '".$term."' not found" );
			}
		}
	}
	
	
	
	###########################
	# OPZETTEN NIEUWE WEBSHOP #
	###########################
	
	// Nieuwe webshops voorlopig afschermen
	$oxfam_blocked_sites = array( 5, 74 );
	update_site_option( 'oxfam_blocked_sites', $oxfam_blocked_sites );
	
	// Maak accounts voor lokale assistenten aan
	$new_logins = array( 'hello@gmail.com' => 'Voornaam;Familienaam' );
	foreach ( $new_logins as $email => $name ) {
		$parts = explode( ';', $name );
		$fname = $parts[0];
		$lname = $parts[1];
		$user_args = array(
			'user_login' => sanitize_user( strtolower( str_replace( ' ', '.', str_replace( ';', '.', trim( $name ) ) ) ) ),
			'user_email' => $email,
			'display_name' => $fname,
			'first_name' => $fname,
			'last_name' => $lname,
			'role' => 'local_helper',
		);
		// Creëer user en nodig ze meteen uit door wachtwoordreset te triggeren
		$user_id = wp_insert_user( $user_args );
		if ( is_numeric( $user_id ) ) {
			echo 'User-ID '.$user_id.' aangemaakt';
			$new_user = get_user_by( 'ID', $user_id );
			if ( $new_user ) {
				if ( retrieve_password_for_customer( $new_user ) ) {
					echo ' en wachtwoordreset uitgevoerd!';
				}
			}
		} else {
			echo $email.' bestaat reeds!';
		}
		echo '<br/>';
	}
	
	
	
	// Startpagina instellen
	$homepage = get_page_by_title('Startpagina');
	if ( $homepage !== NULL ) {
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $homepage->ID );
	}
	
	

	// Voorwaardenpagina instellen
	$terms = get_page_by_title('Algemene voorwaarden');
	if ( $terms !== NULL ) {
		update_option( 'woocommerce_terms_page_id', $terms->ID );
	}
	
	

	// Hoofdwinkelpagina instellen
	$shop_page = get_page_by_title('Producten');
	if ( $shop_page !== NULL ) {
		update_option( 'woocommerce_shop_page_id', $shop_page->ID );
		// Lost het probleem met de lege shoppagina op na het toevoegen van nieuwe categorieën via Broadcast sync!
		flush_rewrite_rules();
	}
	
	

	// Leeggoed verbergen en op voorraad zetten
	$args = array(
		'post_type'			=> 'product',
		'post_status'		=> array('publish'),
		'posts_per_page'	=> -1,
	);
	$all_products = new WP_Query( $args );

	if ( $all_products->have_posts() ) {
		while ( $all_products->have_posts() ) {
			$all_products->the_post();
			$product = wc_get_product( get_the_ID() );
			// Ook themafuncties kunnen opgeroepen worden vanuit snippets!
			if ( $product !== false and in_array( $product->get_sku(), get_oxfam_empties_skus_array() ) ) {
				$product->set_stock_status('instock');
				// Wordt sinds WooMultistore 2.0+ automatisch gekopieerd bij aanmaak
				$product->set_catalog_visibility('hidden');
				$product->save();
			}
		}
		wp_reset_postdata();
	}

	
	
	// Alles behalve leeggoed uit voorraad zetten
	$args = array(
		'post_type'			=> 'product',
		'post_status'		=> array('publish'),
		'posts_per_page'	=> -1,
	);
	$all_products = new WP_Query( $args );

	if ( $all_products->have_posts() ) {
		while ( $all_products->have_posts() ) {
			$all_products->the_post();
			$product = wc_get_product( get_the_ID() );
			// Ook themafuncties kunnen opgeroepen worden vanuit snippets!
			if ( $product !== false and ! in_array( $product->get_sku(), get_oxfam_empties_skus_array() ) ) {
				$product->set_stock_status('outofstock');
				// On first publish wordt voorraadbeheer en uitlichting van nationaal ook lokaal geactiveerd!
				$product->set_manage_stock('no');
				$product->set_featured('no');
				$product->save();
			}
		}
		wp_reset_postdata();
	}

	
	
	// Stel de openingsuren in van een niet-OWW-afhaalpunt
	$node = 'stoasje';
	$hours = array(
		1 => false,
		2 => false,
		3 => false,
		4 => array(
			array(
				'start' => '14:30',
				'end' => '19:00'
			),
		),
		5 => false,
		6 => false,
		7 => false,
	);
	update_site_option( 'oxfam_opening_hours_'.$node , $hours );
	
	
	
	#########################
	# INSTELLINGEN WIJZIGEN #
	#########################
	
	// Relevanssi-index opnieuw opbouwen
	relevanssi_build_index();
	
	
	
	// Default productcategorie wijzigen
	$term = get_term_by( 'slug', 'geschenken-wenskaarten', 'product_cat' );
	if ( $term !== false ) {
		update_option( 'default_product_cat', $term->term_id );
	}
	
	
	
	// Instellingen van een betaalmethode wijzigen
	$cod_settings = get_option('woocommerce_cod_settings');
	if ( $cod_settings !== false ) {
		$cod_settings['enabled'] = 'yes';
		$cod_settings['title'] = 'Op factuur';
		$cod_settings['description'] = 'Als geregistreerde B2B-klant dien je de goederen pas achteraf te betalen, na ontvangst van de factuur.

Bij grote bestellingen kan de levering omwille van onze beperkte voorraad iets langer op zich laten wachten dan bij particulieren. Neem contact met ons op indien het om een dringende bestelling gaat.';
		$cod_settings['instructions'] = 'Na de levering van de goederen bezorgen we je een factuur met het definitieve bedrag en de betaalinstructies.';
		$cod_settings['enable_for_virtual'] = 'no';
		update_option( 'woocommerce_cod_settings', $cod_settings );
	}
	
	

	// Instellingen van een WooCommerce-mail wijzigen
	$possible_keys = array(
		'woocommerce_new_order_settings',
		'woocommerce_customer_new_account_settings',
		'woocommerce_customer_processing_order_settings',
		'woocommerce_customer_completed_order_settings',
		'woocommerce_customer_reset_password_settings',
		'woocommerce_customer_note_settings',
	);

	$chosen_key = 'woocommerce_customer_processing_order_settings';
	switch_to_blog(1);
	$mail_settings = get_option( $chosen_key );
	restore_current_blog();

	if ( is_array( $mail_settings ) ) {
		// $mail_settings['enabled'] = 'yes';
		// Let op in subsites, altijd overschrijven in adminmails!
		// $mail_settings['recipient'] = get_webshop_email();
		// $mail_settings['subject'] = 'Actie vereist: nieuwe online bestelling ({order_number}) – {order_date}';
		// $mail_settings['heading'] = 'Hoera, een nieuwe bestelling!';
		$mail_settings['additional_content'] = '';
		update_option( $chosen_key, $mail_settings );

		$local_settings = get_option( $chosen_key );
		// Voor klantenmails bestaat deze waarde niet
		write_log( "BLOG ".get_current_blog_id().": ".$local_settings['recipient'] );
		write_log( "BLOG ".get_current_blog_id().": ".$local_settings['subject'] );
		write_log( "BLOG ".get_current_blog_id().": ".$local_settings['heading'] );
		write_log( "BLOG ".get_current_blog_id().": ".$local_settings['additional_content'] );
		unset( $local_settings );
	}
	
	

	// Instellingen van WP Mail Log kopiëren naar subsites
	switch_to_blog(1);
	$wpml_settings = get_option('wpml_settings');
	restore_current_blog();
	if ( is_array($wpml_settings) ) {
		update_option( 'wpml_settings', $wpml_settings );
		delete_option('wpml_settings-transients');
	}
	
	
	
	// Vlaamse gemeentelijst bijwerken
	$zips = array( 1000 => "Brussel", 1020 => "Laken", 1030 => "Schaarbeek", 1040 => "Etterbeek", 1050 => "Elsene", 1060 => "Sint-Gillis", 1070 => "Anderlecht", 1080 => "Sint-Jans-Molenbeek", 1081 => "Koekelberg", 1082 => "Sint-Agatha-Berchem", 1083 => "Ganshoren", 1090 => "Jette", 1120 => "Neder-over-Heembeek", 1130 => "Haren", 1140 => "Evere", 1150 => "Sint-Pieters-Woluwe", 1160 => "Oudergem", 1170 => "Watermaal-Bosvoorde", 1180 => "Ukkel", 1190 => "Vorst", 1200 => "Sint-Lambrechts-Woluwe", 1210 => "Sint-Joost-ten-Node", 1500 => "Halle", 1501 => "Buizingen", 1502 => "Lembeek", 1540 => "Herne / Herfelingen", 1541 => "Sint-Pieters-Kapelle", 1547 => "Bever", 1560 => "Hoeilaart", 1570 => "Galmaarden / Tollembeek / Vollezele", 1600 => "Sint-Pieters-Leeuw / Oudenaken / Sint-Laureins-Berchem", 1601 => "Ruisbroek", 1602 => "Vlezenbeek", 1620 => "Drogenbos", 1630 => "Linkebeek", 1640 => "Sint-Genesius-Rode", 1650 => "Beersel", 1651 => "Lot", 1652 => "Alsemberg", 1653 => "Dworp", 1654 => "Huizingen", 1670 => "Pepingen / Bogaarden / Heikruis", 1671 => "Elingen", 1673 => "Beert", 1674 => "Bellingen", 1700 => "Dilbeek / Sint-Martens-Bodegem / Sint-Ulriks-Kapelle", 1701 => "Itterbeek", 1702 => "Groot-Bijgaarden", 1703 => "Schepdaal", 1730 => "Asse / Bekkerzeel / Kobbegem / Mollem", 1731 => "Zellik / Relegem", 1740 => "Ternat", 1741 => "Wambeek", 1742 => "Sint-Katherina-Lombeek", 1745 => "Opwijk / Mazenzele", 1750 => "Lennik / Gaasbeek / Sint-Kwintens-Lennik / Sint-Martens-Lennik", 1755 => "Gooik / Kester / Leerbeek / Oetingen", 1760 => "Roosdaal / Onze-Lieve-Vrouw-Lombeek / Pamel / Strijtem", 1761 => "Borchtlombeek", 1770 => "Liedekerke", 1780 => "Wemmel", 1785 => "Merchtem / Brussegem / Hamme", 1790 => "Affligem / Essene / Hekelgem / Teralfene", 1800 => "Vilvoorde / Peutie", 1804 => "Cargovil", 1818 => "VTM", 1820 => "Steenokkerzeel / Melsbroek / Perk", 1830 => "Machelen", 1831 => "Diegem", 1840 => "Londerzeel / Malderen / Steenhuffel", 1850 => "Grimbergen", 1851 => "Humbeek", 1852 => "Beigem", 1853 => "Strombeek-Bever", 1860 => "Meise", 1861 => "Wolvertem", 1880 => "Kapelle-op-den-Bos / Nieuwenrode / Ramsdonk", 1910 => "Kampenhout / Berg / Buken / Nederokkerzeel", 1930 => "Zaventem / Nossegem", 1931 => "Brucargo", 1932 => "Sint-Stevens-Woluwe", 1933 => "Sterrebeek", 1934 => "Brussel X", 1950 => "Kraainem", 1970 => "Wezembeek-Oppem", 1980 => "Zemst / Eppegem", 1981 => "Hofstade", 1982 => "Elewijt / Weerde", 2000 => "Antwerpen", 2018 => "Antwerpen", 2020 => "Antwerpen", 2030 => "Antwerpen", 2040 => "Antwerpen / Berendrecht / Lillo / Zandvliet", 2050 => "Antwerpen", 2060 => "Antwerpen", 2070 => "Zwijndrecht / Burcht", 2100 => "Deurne", 2110 => "Wijnegem", 2140 => "Borgerhout", 2150 => "Borsbeek", 2160 => "Wommelgem", 2170 => "Merksem", 2180 => "Ekeren", 2200 => "Herentals / Morkhoven / Noorderwijk", 2220 => "Heist-op-den-Berg / Hallaar", 2221 => "Booischot", 2222 => "Itegem / Wiekevorst", 2223 => "Schriek", 2230 => "Herselt / Ramsel", 2235 => "Hulshout / Houtvenne / Westmeerbeek", 2240 => "Zandhoven / Massenhoven / Viersel", 2242 => "Pulderbos", 2243 => "Pulle", 2250 => "Olen", 2260 => "Westerlo / Oevel / Tongerlo / Zoerle-Parwijs", 2270 => "Herenthout", 2275 => "Lille / Gierle / Poederlee / Wechelderzande", 2280 => "Grobbendonk", 2288 => "Bouwel", 2290 => "Vorselaar", 2300 => "Turnhout", 2310 => "Rijkevorsel", 2320 => "Hoogstraten", 2321 => "Meer", 2322 => "Minderhout", 2323 => "Wortel", 2328 => "Meerle", 2330 => "Merksplas", 2340 => "Beerse / Vlimmeren", 2350 => "Vosselaar", 2360 => "Oud-Turnhout", 2370 => "Arendonk", 2380 => "Ravels", 2381 => "Weelde", 2382 => "Poppel", 2387 => "Baarle-Hertog", 2390 => "Malle / Oostmalle / Westmalle", 2400 => "Mol", 2430 => "Laakdal / Eindhout / Vorst", 2431 => "Varendonk / Veerle", 2440 => "Geel", 2450 => "Meerhout", 2460 => "Kasterlee / Lichtaart / Tielen", 2470 => "Retie", 2480 => "Dessel", 2490 => "Balen", 2491 => "Olmen", 2500 => "Lier / Koningshooikt", 2520 => "Ranst / Broechem / Emblem / Oelegem", 2530 => "Boechout", 2531 => "Vremde", 2540 => "Hove", 2547 => "Lint", 2550 => "Kontich / Waarloos", 2560 => "Nijlen / Bevel / Kessel", 2570 => "Duffel", 2580 => "Putte / Beerzel", 2590 => "Berlaar / Gestel", 2600 => "Berchem", 2610 => "Wilrijk", 2620 => "Hemiksem", 2627 => "Schelle", 2630 => "Aartselaar", 2640 => "Mortsel", 2650 => "Edegem", 2660 => "Hoboken", 2800 => "Mechelen / Walem", 2801 => "Heffen", 2811 => "Hombeek / Leest", 2812 => "Muizen", 2820 => "Bonheiden / Rijmenam", 2830 => "Willebroek / Blaasveld / Heindonk / Tisselt", 2840 => "Rumst / Reet / Terhagen", 2845 => "Niel", 2850 => "Boom", 2860 => "Sint-Katelijne-Waver", 2861 => "Onze-Lieve-Vrouw-Waver", 2870 => "Puurs / Breendonk / Liezele / Ruisbroek", 2880 => "Bornem / Hingene / Mariekerke / Weert", 2890 => "Sint-Amands / Lippelo / Oppuurs", 2900 => "Schoten", 2910 => "Essen", 2920 => "Kalmthout", 2930 => "Brasschaat", 2940 => "Stabroek / Hoevenen", 2950 => "Kapellen", 2960 => "Brecht / Sint-Job-in-'t-Goor / Sint-Lenaarts", 2970 => "Schilde / 's Gravenwezel", 2980 => "Zoersel / Halle", 2990 => "Wuustwezel / Loenhout", 3000 => "Leuven", 3001 => "Heverlee", 3010 => "Kessel-Lo", 3012 => "Wilsele", 3018 => "Wijgmaal", 3020 => "Herent / Veltem-Beisem / Winksele", 3040 => "Huldenberg / Loonbeek / Neerijse / Ottenburg / Sint-Agatha-Rode", 3050 => "Oud-Heverlee", 3051 => "Sint-Joris-Weert", 3052 => "Blanden", 3053 => "Haasrode", 3054 => "Vaalbeek", 3060 => "Bertem / Korbeek-Dijle", 3061 => "Leefdaal", 3070 => "Kortenberg", 3071 => "Erps-Kwerps", 3078 => "Everberg / Meerbeek", 3080 => "Tervuren / Duisburg / Vossem", 3090 => "Overijse", 3110 => "Rotselaar", 3111 => "Wezemaal", 3118 => "Werchter", 3120 => "Tremelo", 3128 => "Baal", 3130 => "Begijnendijk / Betekom", 3140 => "Keerbergen", 3150 => "Haacht / Tildonk / Wespelaar", 3190 => "Boortmeerbeek", 3191 => "Hever", 3200 => "Aarschot / Gelrode", 3201 => "Langdorp", 3202 => "Rillaar", 3210 => "Lubbeek / Linden", 3211 => "Binkom", 3212 => "Pellenberg", 3220 => "Holsbeek / Kortrijk-Dutsel / Sint-Pieters-Rode", 3221 => "Nieuwrode", 3270 => "Scherpenheuvel-Zichem", 3271 => "Averbode / Zichem", 3272 => "Messelbroek / Testelt", 3290 => "Diest / Deurne / Schaffen / Webbekom", 3293 => "Kaggevinne", 3294 => "Molenstede", 3300 => "Tienen / Bost / Goetsenhoven / Hakendover / Kumtich / Oorbeek / Oplinter / Sint-Margriete-Houtem / Vissenaken", 3320 => "Hoegaarden / Meldert", 3321 => "Outgaarden", 3350 => "Linter / Drieslinter / Melkwezer / Neerhespen / Neerlinter / Orsmaal-Gussenhoven / Overhespen / Wommersom", 3360 => "Bierbeek / Korbeek-Lo / Lovenjoel / Opvelp", 3370 => "Boutersem / Kerkom / Neervelp / Roosbeek / Vertrijk / Willebringen", 3380 => "Glabbeek-Zuurbemde / Bunsbeek", 3381 => "Kapellen", 3384 => "Attenrode", 3390 => "Tielt-Winge / Houwaart / Sint-Joris-Winge / Tielt", 3391 => "Meensel-Kiezegem", 3400 => "Landen / Eliksem / Ezemaal / Laar / Landen / Neerwinden / Overwinden / Rumsdorp / Wange", 3401 => "Waasmont / Walsbets / Walshoutem / Wezeren", 3404 => "Attenhoven / Neerlanden", 3440 => "Zoutleeuw / Budingen / Dormaal / Halle-Booienhoven / Helen-Bos", 3450 => "Geetbets / Grazen", 3454 => "Rummen", 3460 => "Bekkevoort / Assent", 3461 => "Molenbeek-Wersbeek", 3470 => "Kortenaken / Ransberg", 3471 => "Hoeleden", 3472 => "Kersbeek-Miskom", 3473 => "Waanrode", 3500 => "Hasselt / Sint-Lambrechts-Herk", 3501 => "Wimmertingen", 3510 => "Kermt / Spalbeek", 3511 => "Kuringen / Stokrooie", 3512 => "Stevoort", 3520 => "Zonhoven", 3530 => "Houthalen-Helchteren", 3540 => "Herk-de-Stad / Berbroek / Donk / Schulen", 3545 => "Halen / Loksbergen / Zelem", 3550 => "Heusden-Zolder", 3560 => "Lummen / Linkhout / Meldert", 3570 => "Alken", 3580 => "Beringen", 3581 => "Beverlo", 3582 => "Koersel", 3583 => "Paal", 3590 => "Diepenbeek", 3600 => "Genk", 3620 => "Lanaken / Gellik / Neerharen / Veldwezelt", 3621 => "Rekem", 3630 => "Maasmechelen / Eisden / Leut / Mechelen-aan-de-Maas / Meeswijk /Opgrimbie / Vucht", 3631 => "Boorsem / Uikhoven", 3640 => "Kinrooi / Kessenich / Molenbeersel / Ophoven", 3650 => "Dilsen-Stokkem / Elen / Lanklaar / Rotem / Stokkem", 3660 => "Opglabbeek / Oudsbergen", 3665 => "As", 3668 => "Niel-bij-As", 3670 => "Meeuwen-Gruitrode / Ellikom / Neerglabbeek / Wijshagen / Oudsbergen", 3680 => "Maaseik / Neeroeteren / Opoeteren", 3690 => "Zutendaal", 3700 => "Tongeren / 's Herenelderen / Berg / Diets-Heur / Haren / Henis / Kolmont / Koninksem / Lauw / Mal / Neerrepen / Nerem / Overrepen / Piringen / Riksingen / Rutten / Sluizen / Vreren / Widooie", 3717 => "Herstappe", 3720 => "Kortessem", 3721 => "Vliermaalroot", 3722 => "Wintershoven", 3723 => "Guigoven", 3724 => "Vliermaal", 3730 => "Hoeselt / Romershoven / Sint-Huibrechts-Hern / Werm", 3732 => "Schalkhoven", 3740 => "Bilzen / Beverst / Eigenbilzen / Grote-Spouwen / Hees / Kleine-Spouwen / Mopertingen / Munsterbilzen / Rijkhoven / Rosmeer / Spouwen / Waltwilder", 3742 => "Martenslinde", 3746 => "Hoelbeek", 3770 => "Riemst / Genoelselderen / Herderen / Kanne / Membruggen / Millen / Val-Meer / Vlijtingen / Vroenhoven / Zichen-Zussen-Bolder", 3790 => "Voeren / Moelingen / Sint-Martens-Voeren", 3791 => "Remersdaal", 3792 => "Sint-Pieters-Voeren", 3793 => "Teuven", 3798 => "'s Gravenvoeren", 3800 => "Sint-Truiden / Aalst / Brustem / Engelmanshoven / Gelinden / Groot-Gelmen / Halmaal / Kerkom-bij-Sint-Truiden / Ordingen / Zepperen", 3803 => "Duras / Gorsem / Runkelen / Wilderen", 3806 => "Velm", 3830 => "Wellen / Berlingen", 3831 => "Herten", 3832 => "Ulbeek", 3840 => "Borgloon / Bommershoven / Broekom / Gors-Opleeuw / Gotem / Groot-Loon / Haren / Hendrieken / Hoepertingen / Jesseren / Kerniel / Kolmont / Kuttekoven / Rijkel / Voort", 3850 => "Nieuwerkerken / Binderveld / Kozen / Wijer", 3870 => "Heers / Batsheers / Bovelingen / Gutschoven / Heks / Horpmaal / Klein-Gelmen / Mechelen-Bovelingen / Mettekoven / Opheers / Rukkelingen-Loon / Vechmaal / Veulen", 3890 => "Gingelom / Boekhout / Jeuk / Kortijs / Montenaken / Niel-bij-Sint-Truiden / Vorsen", 3891 => "Borlo / Buvingen / Mielen-Boven-Aalst / Muizen", 3900 => "Overpelt", 3910 => "Neerpelt / Sint-Huibrechts-Lille", 3920 => "Lommel", 3930 => "Hamont-Achel", 3940 => "Hechtel-Eksel", 3941 => "Eksel", 3945 => "Ham / Kwaadmechelen / Oostham", 3950 => "Bocholt / Kaulille / Reppel", 3960 => "Bree / Beek / Gerdingen / Opitter / Tongerlo", 3970 => "Leopoldsburg", 3971 => "Heppen", 3980 => "Tessenderlo", 3990 => "Peer / Grote-Brogel / Kleine-Brogel / Peer / Wijchmaal", 8000 => "Brugge / Koolkerke", 8020 => "Oostkamp / Hertsberge / Ruddervoorde / Waardamme", 8200 => "Sint-Andries / Sint-Michiels", 8210 => "Zedelgem / Loppem / Veldegem", 8211 => "Aartrijke", 8300 => "Knokke-Heist / Westkapelle", 8301 => "Heist-aan-Zee / Ramskapelle", 8310 => "Assebroek / Sint-Kruis", 8340 => "Damme / Hoeke / Lapscheure / Moerkerke / Oostkerke / Sijsele", 8370 => "Blankenberge / Uitkerke", 8377 => "Zuienkerke / Houtave / Meetkerke / Nieuwmunster", 8380 => "Dudzele / Lissewege / Zeebrugge", 8400 => "Oostende / Stene / Zandvoorde", 8420 => "De Haan / Klemskerke / Wenduine", 8421 => "Vlissegem", 8430 => "Middelkerke", 8431 => "Wilskerke", 8432 => "Leffinge", 8433 => "Mannekensvere / Schore / Sint-Pieters-Kapelle / Slijpe / Spermalie", 8434 => "Lombardsijde / Westende", 8450 => "Bredene", 8460 => "Oudenburg / Ettelgem / Roksem / Westkerke", 8470 => "Gistel / Moere / Snaaskerke / Zevekote", 8480 => "Ichtegem / Bekegem / Eernegem", 8490 => "Jabbeke / Snellegem / Stalhille / Varsenare / Zerkegem", 8500 => "Kortrijk", 8501 => "Bissegem / Heule", 8510 => "Bellegem / Kooigem / Marke / Rollegem", 8511 => "Aalbeke", 8520 => "Kuurne", 8530 => "Harelbeke", 8531 => "Bavikhove / Hulste", 8540 => "Deerlijk", 8550 => "Zwevegem", 8551 => "Heestert", 8552 => "Moen", 8553 => "Otegem", 8554 => "Sint-Denijs", 8560 => "Wevelgem / Gullegem / Moorsele", 8570 => "Anzegem / Gijzelbrechtegem / Ingooigem / Vichte", 8572 => "Kaster", 8573 => "Tiegem", 8580 => "Avelgem", 8581 => "Kerkhove / Waarmaarde", 8582 => "Outrijve", 8583 => "Bossuit", 8587 => "Spiere-Helkijn", 8600 => "Diksmuide / Beerst / Driekapellen / Esen / Kaaskerke / Keiem / Lampernisse / Leke / Nieuwkapelle / Oostkerke / Oudekapelle / Pervijze / Sint-Jacobskapelle / Stuivekenskerke / Vladslo / Woumen", 8610 => "Kortemark / Handzame / Werken / Zarren", 8620 => "Nieuwpoort / Ramskapelle / Sint-Joris", 8630 => "Veurne / Avekapelle / Beauvoorde / Booitshoeke / Bulskamp / De Moeren / Eggewaartskapelle / Houtem / Steenkerke / Vinkem / Wulveringem / Zoutenaaie", 8640 => "Vleteren / Oostvleteren / Westvleteren / Woesten", 8647 => "Lo-Reninge / Noordschote / Pollinkhove", 8650 => "Houthulst / Klerken / Merkem", 8660 => "De Panne / Adinkerke", 8670 => "Koksijde / Oostduinkerke / Wulpen", 8680 => "Koekelare / Bovekerke / Zande", 8690 => "Alveringem / Hoogstade / Oeren / Sint-Rijkers", 8691 => "Beveren-aan-den-IJzer / Gijverinkhove / Izenberge / Leisele / Stavele", 8700 => "Tielt / Aarsele / Kanegem / Schuiferskapelle", 8710 => "Wielsbeke / Ooigem / Sint-Baafs-Vijve", 8720 => "Dentergem / Markegem / Oeselgem / Wakken", 8730 => "Beernem / Oedelem / Sint-Joris", 8740 => "Pittem / Egem", 8750 => "Wingene / Zwevezele", 8755 => "Ruiselede", 8760 => "Meulebeke", 8770 => "Ingelmunster", 8780 => "Oostrozebeke", 8790 => "Waregem", 8791 => "Beveren-Leie", 8792 => "Desselgem", 8793 => "Sint-Eloois-Vijve", 8800 => "Roeselare / Beveren / Oekene / Rumbeke", 8810 => "Lichtervelde", 8820 => "Torhout", 8830 => "Hooglede / Gits", 8840 => "Staden / Oostnieuwkerke / Westrozebeke", 8850 => "Ardooie", 8851 => "Koolskamp", 8860 => "Lendelede", 8870 => "Izegem / Emelgem / Kachtem", 8880 => "Ledegem / Rollegem-Kapelle / Sint-Eloois-Winkel", 8890 => "Moorslede / Dadizele", 8900 => "Ieper / Brielen / Dikkebus / Sint-Jan", 8902 => "Hollebeke / Voormezele / Zillebeke", 8904 => "Boezinge / Zuidschote", 8906 => "Elverdinge", 8908 => "Vlamertinge", 8920 => "Langemark-Poelkapelle / Bikschote", 8930 => "Menen / Lauwe / Rekkem", 8940 => "Wervik / Geluwe", 8950 => "Heuvelland / Nieuwkerke", 8951 => "Dranouter", 8952 => "Wulvergem", 8953 => "Wijtschate", 8954 => "Westouter", 8956 => "Kemmel", 8957 => "Mesen", 8958 => "Loker", 8970 => "Poperinge / Reningelst", 8972 => "Krombeke / Proven / Roesbrugge-Haringe", 8978 => "Watou", 8980 => "Zonnebeke / Beselare / Geluveld / Passendale / Zandvoorde", 9000 => "Gent", 9030 => "Mariakerke", 9031 => "Drongen", 9032 => "Wondelgem", 9040 => "Sint-Amandsberg", 9041 => "Oostakker", 9042 => "Desteldonk / Mendonk / Sint-Kruis-Winkel", 9050 => "Gentbrugge / Ledeberg", 9051 => "Afsnee / Sint-Denijs-Westrem", 9052 => "Zwijnaarde", 9060 => "Zelzate", 9070 => "Destelbergen / Heusden", 9080 => "Lochristi / Beervelde / Zaffelare / Zeveneken", 9090 => "Melle / Gontrode", 9100 => "Sint-Niklaas / Nieuwkerken-Waas", 9111 => "Belsele", 9112 => "Sinaai-Waas", 9120 => "Beveren-Waas / Haasdonk / Kallo / Melsele / Vrasene", 9130 => "Doel / Kallo / Kieldrecht / Verrebroek", 9140 => "Temse / Elversele / Steendorp / Tielrode", 9150 => "Kruibeke / Bazel / Rupelmonde", 9160 => "Lokeren / Daknam / Eksaarde", 9170 => "Sint-Gillis-Waas / De Klinge / Meerdonk / Sint-Pauwels", 9180 => "Moerbeke-Waas", 9185 => "Wachtebeke", 9190 => "Stekene", 9200 => "Dendermonde / Appels / Baasrode / Grembergen / Mespelare / Oudegem / Schoonaarde / Sint-Gillis-bij-Dendermonde", 9220 => "Hamme / Moerzeke", 9230 => "Wetteren / Massemen / Westrem", 9240 => "Zele", 9250 => "Waasmunster / ", 9255 => "Buggenhout / Opdorp", 9260 => "Wichelen / Schellebelle / Serskamp", 9270 => "Laarne / Kalken", 9280 => "Lebbeke / Denderbelle / Wieze", 9290 => "Berlare / Overmere / Uitbergen", 9300 => "Aalst", 9308 => "Gijzegem / Hofstade", 9310 => "Baardegem / Herdersem / Meldert / Moorsel", 9320 => "Erembodegem / Nieuwerkerken", 9340 => "Lede / Impe / Oordegem / Smetlede / Wanzele", 9400 => "Ninove / Appelterre-Eichem / Denderwindeke / Lieferinge / Nederhasselt / Okegem / Voorde", 9401 => "Pollare", 9402 => "Meerbeke", 9403 => "Neigem", 9404 => "Aspelare", 9406 => "Outer", 9420 => "Erpe-Mere / Aaigem / Bambrugge / Burst / Erondegem / Ottergem / Vlekkem", 9450 => "Haaltert / Denderhoutem / Heldergem", 9451 => "Kerksken", 9470 => "Denderleeuw", 9472 => "Iddergem", 9473 => "Welle", 9500 => "Geraardsbergen / Goeferdinge / Moerbeke / Nederboelare / Onkerzele / Ophasselt / Overboelare / Viane / Zarlardinge", 9506 => "Grimminge / Idegem / Nieuwenhove / Schendelbeke / Smeerebbe-Vloerzegem / Waarbeke / Zandbergen", 9520 => "Sint-Lievens-Houtem / Bavegem / Vlierzele / Zonnegem", 9521 => "Letterhoutem", 9550 => "Herzele / Hillegem / Sint-Antelinks / Sint-Lievens-Esse / Steenhuize-Wijnhuize / Woubrechtegem", 9551 => "Ressegem", 9552 => "Borsbeke", 9570 => "Lierde / Deftinge / Sint-Maria-Lierde", 9571 => "Hemelveerdegem", 9572 => "Sint-Martens-Lierde", 9600 => "Ronse", 9620 => "Zottegem / Elene / Erwetegem / Godveerdegem / Grotenberge / Leeuwergem / Oombergen / Sint-Goriks-Oudenhove / Sint-Maria-Oudenhove / Strijpen / Velzeke-Ruddershove", 9630 => "Zwalm / Beerlegem / Dikkele / Hundelgem / Meilegem / Munkzwalm / Paulatem / Roborst / Rozebeke / Sint-Blasius-Boekel / Sint-Denijs-Boekel / Sint-Maria-Latem", 9636 => "Nederzwalm-Hermelgem", 9660 => "Brakel / Elst / Everbeek / Michelbeke / Nederbrakel / Opbrakel / Zegelsem", 9661 => "Parike", 9667 => "Horebeke / Sint-Kornelis-Horebeke / Sint-Maria-Horebeke", 9680 => "Maarkedal / Etikhove / Maarke-Kerkem", 9681 => "Nukerke", 9688 => "Schorisse", 9690 => "Kluisbergen / Berchem / Kwaremont / Ruien / Zulzeke", 9700 => "Oudenaarde / Bevere / Edelare / Eine / Ename / Heurne / Leupegem / Mater / Melden / Mullem / Nederename / Volkegem / Welden", 9750 => "Zingem / Huise / Ouwegem / Kruisem", 9770 => "Kruishoutem / Kruisem", 9771 => "Nokere", 9772 => "Wannegem-Lede", 9790 => "Wortegem-Petegem / Elsegem / Moregem / Ooike / Petegem-aan-de-Schelde", 9800 => "Deinze / Astene / Bachte-Maria-Leerne / Gottem / Grammene / Meigem / Petegem-aan-de-Leie / Sint-Martens-Leerne / Vinkt / Wontergem / Zeveren", 9810 => "Nazareth / Eke", 9820 => "Merelbeke / Bottelare / Lemberge / Melsen / Munte / Schelderode", 9830 => "Sint-Martens-Latem", 9831 => "Deurle", 9840 => "De Pinte / Zevergem", 9850 => "Nevele / Hansbeke / Landegem / Merendree / Poesele / Vosselare", 9860 => "Oosterzele / Balegem / Gijzenzele / Landskouter / Moortsele / Scheldewindeke", 9870 => "Zulte / Machelen / Olsene", 9880 => "Aalter / Lotenhulle / Poeke", 9881 => "Bellem", 9890 => "Gavere / Asper / Baaigem / Dikkelvenne / Semmerzake / Vurste", 9900 => "Eeklo", 9910 => "Knesselare / Ursel", 9920 => "Lovendegem / Lievegem", 9921 => "Vinderhoute", 9930 => "Zomergem / Lievegem", 9931 => "Oostwinkel", 9932 => "Ronsele", 9940 => "Evergem / Ertvelde / Kluizen / Sleidinge", 9950 => "Waarschoot / Lievegem", 9960 => "Assenede", 9961 => "Boekhoute", 9968 => "Bassevelde / Oosteeklo", 9970 => "Kaprijke", 9971 => "Lembeke", 9980 => "Sint-Laureins", 9981 => "Sint-Margriete", 9982 => "Sint-Jan-in-Eremo", 9988 => "Waterland-Oudeman / Watervliet", 9990 => "Maldegem", 9991 => "Adegem", 9992 => "Middelburg" );
	update_site_option( 'oxfam_flemish_zip_codes', $zips );

	
	
	#############################################
	# VEROUDERDE (WELLICHT OVERBODIGE) SNIPPETS #
	#############################################
	
	// Werk de slug van een product bij
	$product_id = wc_get_product_id_by_sku('20070');
	if ( $product_id ) {
		$product = wc_get_product( $product_id );
		$slug = sanitize_title( $product->get_name() );
		$product->set_slug( $slug );
		$product->save();
	}
	
	
	
	// Verwijder een product
	$product_id = wc_get_product_id_by_sku('87505');
	if ( $product_id ) {
		$product = wc_get_product( $product_id );
		$product->delete(true);
	}
	
	
	
	// Unassigned producten opnieuw aan hoofdsite koppelen
	$args = array(
		'post_type'			=> 'product',
		'post_status'		=> array('publish'),
		'posts_per_page'	=> 100,
		'paged'				=> 1,
	);
	$all_products = new WP_Query( $args );
	
	if ( $all_products->have_posts() ) {
		while ( $all_products->have_posts() ) {
			$all_products->the_post();
			// $sku = '25628';
			// $global_product_id = wc_get_product_id_by_sku( $sku );
			$global_product_id = get_the_ID();
			$global_product = wc_get_product( $global_product_id );
			$sku = $global_product->get_sku();
			for ( $id = 7; $id < 40; $id++ ) {
				switch_to_blog( $id );
				$product_id = wc_get_product_id_by_sku( $sku );
				$product = wc_get_product( $product_id );
				$product->update_meta_data( '_woonet_child_inherit_updates', 'yes' );
				$product->update_meta_data( '_woonet_network_is_child_product_id', $global_product_id );
				$product->update_meta_data( '_woonet_network_is_child_site_id', 1 );
				$product->delete_meta_data('_woonet_network_unassigned_product_id');
				$product->delete_meta_data('_woonet_network_unassigned_site_id');
				$product->save();
				restore_current_blog();
				$global_product->update_meta_data( '_woonet_publish_to_'.$id, 'yes' );
			}
			$global_product->save();
			write_log( $global_product->get_sku() );
		}
		wp_reset_postdata();
	}
	
	
	
	// Wijziging aan een orderstatus doorvoeren
	$args = array(
		'post_type'		=> 'wc_order_status',
		'post_status'	=> 'publish',
		'name'			=> 'processing',
	);
	$order_statuses = new WP_Query( $args );
	
	if ( $order_statuses->have_posts() ) {
		while ( $order_statuses->have_posts() ) {
			$order_statuses->the_post();
			if ( update_post_meta( get_the_ID(), '_bulk_action', 'no' ) ) {
				write_log( get_bloginfo('name').": disabled bulk status action '".$args['name']."'" );
			}
			// $next_statuses = array('cancelled');
			// if ( update_post_meta( get_the_ID(), '_next_statuses', $next_statuses ) ) {
			// 	write_log( get_bloginfo('name').": set next status on '".$args['name']."' status to '".implode( "', '", $next_statuses )."'" );
			// }
		}
		wp_reset_postdata();
	}
	
	
	
	// Een reeks foto's verwijderen in lokale mediabibliotheken
	$photos_to_delete = array( 'SINT-webshop', '20410', '20260' );
	foreach ( $photos_to_delete as $sku ) {
		$photo_ids = oxfam_get_attachment_ids_by_file_name( $sku );
		if ( count( $photo_ids ) > 0 ) {
			foreach ( $photo_ids as $photo_id ) {
				// Verwijder de geregistreerde foto (en alle aangemaakte thumbnails!) volledig
				wp_delete_attachment( $photo_id, true );
			}
		}
	}
	
	
	
	// Alle foto's in een lokale mediabibliotheek verwijderen
	$args = array(
		'post_type'			=> 'attachment',
		'post_status'		=> 'inherit',
		'posts_per_page'	=> -1,
		'fields'			=> 'ids',
	);
	$photos_to_delete = new WP_Query( $args );
	
	if ( $photos_to_delete->have_posts() ) {
		foreach ( $photos_to_delete->posts as $photo_id ) {
			// Verwijder de geregistreerde foto (en alle aangemaakte thumbnails!) volledig
			wp_delete_attachment( $photo_id, true );
		}
	}
	
	
	
	// Een reeks (per ongeluk losgekoppelde) producten verwijderen
	$products_to_delete = array( '65224', '87339' );
	foreach ( $products_to_delete as $sku ) {
		$product_id = wc_get_product_id_by_sku( $sku );
		if ( $product_id !== false ) {
			$product = wc_get_product( $product_id );
			if ( $product !== false and $product->get_status() === 'publish' ) {
				$product->delete(true);
			}
		}
	}
	
	
	
	// Product weer linken aan juiste (geüpdatete) foto
	$sku = '21515';
	$product_id = wc_get_product_id_by_sku( $sku );
	$new_photo_id = oxfam_get_attachment_id_by_file_name( $sku );
	if ( $product_id and $new_photo_id ) {
		$product = wc_get_product( $product_id );
	
		// Update de mapping tussen globale en lokale foto
		switch_to_blog(1);
		// NA IMPORT BEVAT DE TITEL OP HET HOOFDNIVEAU DE OMSCHRIJVING VAN HET PRODUCT, DUS NIET OPZOEKEN VIA TITEL
		$new_global_photo_id = attachment_url_to_postid( 'https://shop.oxfamwereldwinkels.be/wp-content/uploads/'.$sku.'.jpg' );
		restore_current_blog();
		$new_value = array( $new_global_photo_id => $new_photo_id );
		update_post_meta( $product_id, '_woonet_images_mapping', $new_value );
	
		// Koppel nieuw packshot aan product
		$product->set_image_id( $new_photo_id );
		$product->save();
	
		// Stel de uploadlocatie van de nieuwe afbeelding in
		wp_update_post(
			array(
				'ID' => $new_photo_id,
				'post_parent' => $product_id,
			)
		);
	}

?>