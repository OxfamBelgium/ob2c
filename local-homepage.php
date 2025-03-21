<?php
	/*
	Template Name: Lokale Homepage
	Template Post Type: page
	*/

	// Only adding the "entry-content" post class on non-woocommerce pages to avoid CSS conflicts
	$post_class = ( nm_is_woocommerce_page() ) ? '' : 'entry-content';
?>

<?php get_header(); ?>

<?php if ( have_posts() ) : ?>
	<?php while ( have_posts() ) : ?>
		<?php the_post(); ?>
		<div id="content">
			<div class="container">
				<div class="breadcrumb">
					<a href="/">Home</a> <span class="sep"></span> <span class="breadcrumb_last" aria-current="page">Webshop <?= get_webshop_name(true); ?></span>
				</div>
				<div class="col-row lh-intro">
					<div class="col-xs-12 col-md-6">
						<h2><?php echo ( ! is_main_site() ) ? 'Oxfam ' : ''; ?>Webshop <?= get_webshop_name(true); ?></h2>
						<?php the_content(); ?>
					</div>
					<div class="col-xs-12 col-md-6">
						<?php wc_get_template( 'product-searchform_nm.php' ); ?>
					</div>
					<?php get_template_part( 'template-parts/header/general-store-notice' ); ?>
				</div>
				<div class="col-row lh-header">
					<div class="col-xs-12 col-sm-6">
						<h2>Shoppen per categorie</h2>
					</div>
					<div class="col-xs-12 col-sm-6">
						<a href="<?= get_permalink( wc_get_page_id('shop') ); ?>">Alle producten</a>
					</div>
				</div>
				<div class="col-row lh-category">
					<div class="col-xs-12">
						<?= do_shortcode('[nm_product_categories orderby="menu_order" title_tag="h3" parent="0"]'); ?>
					</div>
				</div>
				<?php
					if ( count( wc_get_featured_product_ids() ) > 0 ) {
						// Check of er uitgelichte producten effectief op voorraad zijn
						$args = array(
							'stock_status' => 'instock',
							'include' => wc_get_featured_product_ids(),
						);
						$featured_products = wc_get_products( $args );
						
						if ( count( $featured_products ) > 0 ) {
							?>
							<div class="col-row lh-header">
								<div class="col-xs-12 col-sm-6">
									<h2>Producten in de kijker</h2>
								</div>
								<div class="col-xs-12 col-sm-6">
									<a href="<?= get_permalink( wc_get_page_id('shop') ); ?>">Alle producten</a>
								</div>
							</div>
							<div class="col-row lh-featured">
								<div class="col-xs-12">
									<?= do_shortcode('[nm_product_slider shortcode="featured_products" per_page="-1" columns="4" columns_mobile="1" orderby="menu_order" order="ASC" arrows="1"]'); ?>
								</div>
							</div>
							<?php
						}
					}
				?>
				
				<?php
					if ( date_i18n('Y-m-d') >= '2024-05-01' and date_i18n('Y-m-d') < '2024-06-01' ) {
						?>
							<div class="col-row lh-banner">
								<div class="col-xs-12">
									<?php
										$image = '<img src="'.esc_attr( get_stylesheet_directory_uri().'/images/promoties/palestina-2024-liggend.jpg' ).'" />';
										$term_link = get_term_link( 'promotie', 'product_tag' );
										if ( ! is_wp_error( $term_link ) ) {
											echo '<a href="'.esc_url( $term_link ).'#nm-shop-products">'.$image.'</a>';
										} else {
											echo $image;
										}
									?>
								</div>
							</div>
						<?php
					}
				?>
				
				<?php
					if ( count( wc_get_product_ids_on_sale() ) > 0 ) {
						// wc_get_product_ids_on_sale() werkt met een transient cache, waarbij toekomstige promo's ook al opgenomen zijn
						// Filter daarom nogmaals op 'vandaag in promo' én check of ze bestelbaar zijn
						$args = array(
							'stock_status' => 'instock',
							'include' => wc_get_product_ids_on_sale(),
							// Is blanco na de start van de promoperiode ...
							// 'date_on_sale_from' => '<='.date_i18n('Y-m-d'),
							// Is leeg bij de Campesino-actie zonder einddatum ...
							// 'date_on_sale_to' => '>='.date_i18n('Y-m-d'),
						);
						$sale_products = wc_get_products( $args );
						
						if ( count( $sale_products ) > 0 ) {
							$term = get_term_by( 'slug', 'promotie', 'product_tag' );
							if ( $term !== false ) {
								$term_link = get_term_link( $term );
								$url = $term_link.'#nm-shop-products';
							} else {
								$url = get_permalink( wc_get_page_id('shop') );
							}
							?>
							<div class="col-row lh-header">
								<div class="col-xs-12 col-sm-6">
									<h2>Promoties</h2>
								</div>
								<div class="col-xs-12 col-sm-6">
									<a href="<?= esc_url( $url ); ?>">Alle promoties</a>
								</div>
							</div>
							<div class="col-row lh-promo">
								<div class="col-xs-12">
									<?= do_shortcode('[nm_product_slider shortcode="sale_products" per_page="-1" columns="4" columns_mobile="1" orderby="menu_order" order="ASC" arrows="1"]'); ?>
								</div>
							</div>
							<?php
						}
					}
				?>
			</div>
		</div>
	<?php endwhile; ?>
<?php endif; ?>

<?php get_footer(); ?>