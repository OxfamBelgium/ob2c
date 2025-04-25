<?php
/**
 * The template for displaying product content within loops
 *
 * @see     https://docs.woocommerce.com/document/template-structure/
 * @package WooCommerce/Templates
 * @version 3.6.0
 NM: Modified */

defined( 'ABSPATH' ) || exit;

global $product, $nm_globals, $nm_theme_options, $woocommerce_loop, $position_in_grid;

if ( ! isset( $position_in_grid ) ) {
    $position_in_grid = 1;
}

// Ensure visibility
if ( empty( $product ) || false === wc_get_loop_product_visibility( $product->get_id() ) || ! $product->is_visible() ) {
	return;
}

nm_add_page_include( 'products' );

// Wrapper link
remove_action( 'woocommerce_before_shop_loop_item', 'woocommerce_template_loop_product_link_open', 10 );
remove_action( 'woocommerce_after_shop_loop_item', 'woocommerce_template_loop_product_link_close', 5 );

// Product variation attributes
$attributes_escaped = function_exists( 'nm_template_loop_attributes' ) ? nm_template_loop_attributes() : null;
$product_class = ( $attributes_escaped ) ? 'nm-has-attributes' : '';

// Title
remove_action( 'woocommerce_shop_loop_item_title', 'woocommerce_template_loop_product_title', 10 );
add_action( 'woocommerce_shop_loop_item_title', 'nm_template_loop_product_title', 10 );

// Rating
if ( ! $nm_theme_options['product_rating'] ) {
    remove_action( 'woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_rating', 5 );
}

// Action link
if ( ! $nm_theme_options['product_action_link'] ) {
    remove_action( 'woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart', 10 );
}
?>

<?php if ( in_array( $woocommerce_loop['name'], array( '', 'sale_products' ) ) ) : ?>
    <?php $coupon = new WC_Coupon('202505-wftd'); ?>
    
    <!-- Geen is_valid() gebruiken, zal pas true retourneren als de korting al effectief in het winkelmandje zit! -->
    <?php if ( $coupon->get_date_expires() instanceof WC_DateTime and date_i18n('Y-m-d') < $coupon->get_date_expires()->date_i18n('Y-m-d') ) : ?>
        <?php
            $couscous = wc_get_product( wc_get_product_id_by_sku('27055') );
            $term_link = get_term_link( 'promotie', 'product_tag' );
        ?>
        
        <?php if ( $couscous !== false and $couscous->get_stock_status() === 'instock' ) : ?>
            <?php if ( ( $position_in_grid === 1 ) ) : ?>
                <li class="promo-banner vertical">
                    <?php
                        $image = '<img src="'.esc_attr( get_stylesheet_directory_uri().'/images/promoties/koffie-2025-staand.jpg' ).'" />';
                        if ( ! is_wp_error( $term_link ) ) {
                            echo '<a href="'.esc_url( $term_link ).'#nm-shop-products">'.$image.'</a>';
                        } else {
                            echo $image;
                        }
                        $position_in_grid++;
                    ?>
                </li>
            <?php endif; ?>
            
            <?php if ( 1 === 2 ) : ?>
                <li class="promo-banner horizontal">
                    <?php
                        $image = '<img src="'.esc_attr( get_stylesheet_directory_uri().'/images/promoties/koffie-2025-liggend.jpg' ).'" />';
                        if ( ! is_wp_error( $term_link ) ) {
                            echo '<a href="'.esc_url( $term_link ).'#nm-shop-products">'.$image.'</a>';
                        } else {
                            echo $image;
                        }
                        $position_in_grid++;
                    ?>
                </li>
            <?php endif; ?>
        <?php endif; ?>
    <?php endif; ?>
<?php endif; ?>

<li <?php wc_product_class( $product_class, $product ); ?> data-product-id="<?php echo esc_attr( $product->get_id() ); ?>">
	<div class="nm-shop-loop-product-wrap">
        <?php
            /**
             * Wishlist button - Note: Centered layout only
             */
            if ( $nm_globals['wishlist_enabled'] && $nm_theme_options['products_layout'] == 'centered' ) { nm_wishlist_button(); }
        ?>

        <?php
        /**
         * Hook: woocommerce_before_shop_loop_item.
         *
         * NM: Removed - @hooked woocommerce_template_loop_product_link_open - 10
         */
        do_action( 'woocommerce_before_shop_loop_item' );
        ?>

        <div class="nm-shop-loop-thumbnail">
            <a href="<?php echo esc_url( get_permalink() ); ?>" class="nm-shop-loop-thumbnail-link woocommerce-LoopProduct-link">
            <?php
            /**
             * Hook: woocommerce_before_shop_loop_item_title.
             *
             * @hooked woocommerce_show_product_loop_sale_flash - 10
             * @hooked woocommerce_template_loop_product_thumbnail - 10
             */
            do_action( 'woocommerce_before_shop_loop_item_title' );
            ?>
            </a>
        </div>
        
        <?php
            /**
             * Product variation attributes
             */
            if ( $attributes_escaped ) {
                echo $attributes_escaped;
            }
        ?>
        
        <div class="nm-shop-loop-details">
            <?php
                /**
                 * Wishlist button
                 */
                if ( $nm_globals['wishlist_enabled'] && $nm_theme_options['products_layout'] !== 'centered' ) { nm_wishlist_button(); }
            ?>

            <div class="nm-shop-loop-title-price">
            <?php
            /**
             * Hook: woocommerce_shop_loop_item_title.
             *
             * NM: Removed - @hooked woocommerce_template_loop_product_title - 10
             * NM: Added - @hooked nm_template_loop_product_title - 10
             */
            do_action( 'woocommerce_shop_loop_item_title' );
            
            /**
             * Hook: woocommerce_after_shop_loop_item_title.
             *
             * @hooked woocommerce_template_loop_rating - 5
             * @hooked woocommerce_template_loop_price - 10
             */
            do_action( 'woocommerce_after_shop_loop_item_title' );
            ?>
            </div>

            <div class="nm-shop-loop-actions">
            <?php
            /**
             * Hook: woocommerce_after_shop_loop_item.
             *
             * NM: Removed - @hooked woocommerce_template_loop_product_link_close - 5
             * @hooked woocommerce_template_loop_add_to_cart - 10
             */
            do_action( 'woocommerce_after_shop_loop_item' );
            
            /**
             * Quick view link
             */
            if ( $nm_theme_options['product_quickview'] && $nm_theme_options['product_quickview_link'] ) {
                echo apply_filters( 'nm_product_quickview_link', '<a href="' . esc_url( get_permalink() ) . '" class="nm-quickview-btn">' . esc_html__( 'Show more', 'nm-framework' ) . '</a>' );
            }
            ?>
            </div>
        </div>
    </div>
</li>

<?php
    if ( in_array( $woocommerce_loop['name'], array( '', 'sale_products' ) ) ) {
        $position_in_grid++;
    }
?>
