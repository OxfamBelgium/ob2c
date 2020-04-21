<?php
	global $nm_theme_options, $nm_globals, $nm_body_class;
	
	// Favicon
	$custom_favicon = false;
	if ( ! function_exists( 'has_site_icon' ) || ! has_site_icon() ) {
		if ( isset( $nm_theme_options['favicon'] ) && strlen( $nm_theme_options['favicon']['url'] ) > 0 ) {
			$custom_favicon = true;
			$favicon_url = ( is_ssl() ) ? str_replace( 'http://', 'https://', $nm_theme_options['favicon']['url'] ) : $nm_theme_options['favicon']['url'];
		}
	}

    // Page load transition class
    $nm_body_class .= ' nm-page-load-transition-' . $nm_theme_options['page_load_transition'];
	
	// CSS animations preload class
	$nm_body_class .= ' nm-preload';
	
	// Top bar
    $top_bar = $nm_theme_options['top_bar'];
	$top_bar_column_left_size = intval( $nm_theme_options['top_bar_left_column_size'] );
	$top_bar_column_right_size = 12 - $top_bar_column_left_size;
    $nm_body_class .= ( $top_bar ) ? ' has-top-bar' : '';
    
	// Header fixed class
	$nm_body_class .= ( $nm_theme_options['header_fixed'] ) ? ' header-fixed' : '';
    
	if ( is_front_page() ) {
        // Header transparency class - Home-page
        $nm_body_class .= ( $nm_theme_options['header_transparency'] != '0' ) ? ' header-transparency' : '';
        
		// Header border class - Home-page
		$nm_body_class .= ( isset( $_GET['header_border'] ) ) ? ' header-border-1' : ' header-border-' . $nm_theme_options['home_header_border'];
	} elseif ( nm_woocommerce_activated() && ( is_shop() || is_product_taxonomy() ) ) {
        // Header transparency class - Shop
        $nm_body_class .= ( $nm_theme_options['header_transparency'] == 'home-shop' ) ? ' header-transparency' : '';
        
		// Header border class - Shop
		$nm_body_class .= ' header-border-' . $nm_theme_options['shop_header_border'];
	} else {
		// Header border class
		$nm_body_class .= ' header-border-' . $nm_theme_options['header_border'];
	}
	
    // Widget panel class
    $nm_body_class .= ' widget-panel-' . $nm_theme_options['widget_panel_color'];

	// Sticky footer class
	$sticky_footer_class = ' footer-sticky-' . $nm_theme_options['footer_sticky'];
    
    // Header: Mobile layout class
    $nm_body_class .= ' header-mobile-' . $nm_theme_options['header_layout_mobile'];

    // Header: Layout slug
    $header_slugs = array( 'default' => '', 'menu-centered' => '', 'centered' => '', 'stacked' => '', 'stacked-centered' => '' );
    $nm_globals['header_layout'] = ( isset( $_GET['header'] ) && isset( $header_slugs[$_GET['header']] ) ) ? $_GET['header'] : $nm_theme_options['header_layout'];
    
    // WooCommerce login
    if ( nm_woocommerce_activated() && ! is_user_logged_in() && is_account_page() ) {
        $nm_body_class .= ' nm-woocommerce-account-login';
    }
?>
<!DOCTYPE html>

<html <?php language_attributes(); ?> class="<?php echo esc_attr( $sticky_footer_class ); ?>">

	<head>
		<meta charset="<?php bloginfo( 'charset' ); ?>">
		<!-- GEWIJZIGD: Verhinder per ongeluk inzoomen en support voor iPhone 10+ -->
		<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">

		<link rel="profile" href="http://gmpg.org/xfn/11">
		<link rel="pingback" href="<?php bloginfo( 'pingback_url' ); ?>">

		<?php if ( $custom_favicon ) : ?>
			<link href="<?php echo esc_url( $favicon_url ); ?>" rel="shortcut icon">
		<?php endif; ?>

		<!-- GEWIJZIGD: Open Graph-tags meta descriptions toevoegen op belangrijke pagina's -->
		<?php if ( is_front_page() ) : ?>
			<meta property="og:title" content="<?php echo get_bloginfo('title'); ?>">
			<meta property="og:url" content="<?php echo get_bloginfo('url') . "/"; ?>">
			<meta property="og:description" content="Shop online in jouw wereldwinkel. Op je gemak. Wanneer het jou past. Jij kiest en betaalt online, onze plaatselijke vrijwilligers zetten je boodschappen klaar. De grootste keuze aan eerlijke voedingsproducten van het land!">
			<?php if ( does_home_delivery() ) : ?>
				<meta property="og:image" content="https://shop.oxfamwereldwinkels.be/wp-content/uploads/webshop-2020-facebook.png">
			<?php else : ?>
				<meta property="og:image" content="https://shop.oxfamwereldwinkels.be/wp-content/uploads/webshop-2020-facebook-afhaling.png">
			<?php endif; ?>
		<?php endif; ?>

		<?php if ( is_main_site() ) : ?>
			<meta name="description" content="Shop online in jouw wereldwinkel. Op je gemak. Wanneer het jou past. Jij kiest en betaalt online, onze plaatselijke vrijwilligers zetten je boodschappen klaar. De grootste keuze aan eerlijke voedingsproducten van het land!">
		<?php else : ?>
			<?php if ( is_front_page() ) : ?>
				<meta name="description" content="Shop online bij <?php echo get_company_name(); ?>. Op je gemak. Wanneer het jou past. Jij kiest en betaalt online, onze vrijwilligers zetten je boodschappen klaar. De grootste keuze aan eerlijke voedingsproducten in jouw buurt!">
			<?php elseif ( is_product_category( array( 'koffie', 'bonen', 'gemalen', 'pads', 'capsules', 'instant' ) ) ) : ?>
				<meta name="description" content="Ontdek de grootste keuze aan fairtradekoffie in jouw buurt! Bestel online bij <?php echo get_company_name(); ?> en wij zetten je boodschappen klaar. Oxfam-koffie: een opkikker voor jou én de koffieboer.">
			<?php endif; ?>
		<?php endif; ?>

		<?php wp_head(); ?>
	</head>
    
	<body <?php body_class( esc_attr( $nm_body_class ) ); ?>>
        
        <?php if ( $nm_theme_options['page_load_transition'] ) : ?>
        <div id="nm-page-load-overlay" class="nm-page-load-overlay"></div>
        <?php endif; ?>
        
        <!-- page overflow wrapper -->
        <div class="nm-page-overflow">
        
            <!-- page wrapper -->
            <div class="nm-page-wrap">
            
                <?php if ( $top_bar ) : ?>
                <!-- top bar -->
                <div id="nm-top-bar" class="nm-top-bar">
                    <div class="nm-row">
                        <div class="nm-top-bar-left col-xs-<?php echo esc_attr( $top_bar_column_left_size ); ?>">
                            <?php
								// Social icons (left column)
								if ( $nm_theme_options['top_bar_social_icons'] == 'l_c' ) {
									echo nm_get_social_profiles( 'nm-top-bar-social' ); // Args: $wrapper_class 
								}
							?>
                            
                            <div class="nm-top-bar-text">
                                <?php echo wp_kses_post( do_shortcode( $nm_theme_options['top_bar_text'] ) ); ?>
                            </div>
                        </div>
                                                
                        <div class="nm-top-bar-right col-xs-<?php echo esc_attr( $top_bar_column_right_size ); ?>">
                            <?php /*if ( is_active_sidebar( 'top-bar' ) ) : ?>
                                <ul id="nm-top-bar-widgets">
                                    <?php dynamic_sidebar( 'top-bar' ); ?>
                                </ul>
                            <?php endif;*/ ?>
                            
                            <?php
								// Social icons (right column)
								if ( $nm_theme_options['top_bar_social_icons'] == 'r_c' ) {
									echo nm_get_social_profiles( 'nm-top-bar-social' ); // Args: $wrapper_class 
								}
							?>
							
							<?php
								// Top-bar menu
								wp_nav_menu( array(
                                    'theme_location'	=> 'top-bar-menu',
                                    'container'       	=> false,
                                    'menu_id'			=> 'nm-top-menu',
                                    'fallback_cb'     	=> false,
                                    'items_wrap'      	=> '<ul id="%1$s" class="nm-menu">%3$s</ul>'
                                ) );
                            ?>
                        </div>
                    </div>                
                </div>
                <!-- /top bar -->
                <?php endif; ?>
                            
				<div class="nm-page-wrap-inner">

					<div id="nm-header-placeholder" class="nm-header-placeholder"></div>

					<?php
						// Include header layout
						if ( $nm_globals['header_layout'] == 'centered' ) {
							get_header( 'centered' );
						} else {
							get_header( 'default' );
						}

						if ( is_main_site() or does_home_delivery() ) {
							echo '<div class="general-store-notice"><p class="free-shipping">';
							if ( get_current_blog_id() === 9 or get_current_blog_id() === 15 ) {
								// Uitzondering voor De Pinte en Gentbrugge
								echo 'Nu met <b><u>gratis</u></b> thuislevering!</p>';
							} elseif ( get_current_blog_id() === 27 ) {
								// Uitzondering voor Hasselt
								echo '<b><u>Gratis</u></b> thuislevering in Hasselt! (elders vanaf 50 euro)</p>';
							} elseif ( get_current_blog_id() === 37 ) {
								// Uitzondering voor Wuustwezel
								echo 'Nu met <b><u>gratis</u></b> verzending vanaf 30 euro!';
							} elseif ( get_current_blog_id() === 38 ) {
								// Uitzondering voor Zele
								echo '<b><u>Gratis</u></b> thuislevering in Zele en Berlare! (elders vanaf 50 euro)</p>';
							} else {
								echo 'Nu met <b><u>gratis</u></b> verzending vanaf 50 euro!';
							}
							echo '</p></div>';
						} elseif ( ! is_main_site() and ! does_home_delivery() ) {
							if ( get_current_blog_id() === 26 ) {
								// Uitzondering voor Brussel
								echo '<div class="general-store-notice"><p class="local-pickup">Omwille van het coronavirus kun je je bestelling momenteel enkel <b><u>op vrijdag afhalen</u></b> in de winkel.</p></div>';
							} elseif ( get_current_blog_id() === 29 ) {
								// Uitzondering voor Roeselare
								echo '<div class="general-store-notice"><p class="local-pickup">Omwille van het coronavirus kun je je bestelling momenteel enkel <b><u>op vrijdag tussen 13u30 en 18u afhalen</u></b> in de winkel.</p></div>';
							} elseif ( get_current_blog_id() !== 12 ) {
								// Uitzondering voor Dilbeek
								echo '<div class="general-store-notice"><p class="local-pickup">Omwille van het coronavirus gebeuren alle afhalingen <b><u>op afspraak</u></b>.<br/>We contacteren je na het plaatsen van je bestelling!</p></div>';
							}
						}
					?>
