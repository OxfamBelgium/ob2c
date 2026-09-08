<?php
/**
 * Single coupon ( for the "Select Free Product" on Cart or Checkout page )
 *
 * @version     2.6.0
 */

defined( 'ABSPATH' ) or die();

/**************************************************************************

Available variables:

	$coupon                  : The WC_Coupon object
	$coupon_code             : The coupon code
	$allow_multiple_products : True if multiplication is enabled for this coupon
	$form_items              : WJECF_Free_Product_Item objects. Contains all info about the free products
	$selected_quantity       : Amount of items selected by the customer
	$max_quantity            : The max amount of free products for this coupon
	$name_prefix             : The name prefix for all form input elements (checkbox / radiobutton / input type="number") for this coupon (e.g. 'wjecf_free_sel[0]')
	$id_prefix               : The unique prefix to use for all DOM elements for this coupon ( e.g. 'wjecf_free_sel_0')
	$totalizer_id            : The id of the <input> that is used to count the total amount of selected items (e.g. 'wjecf_free_sel_0_total_qty')
	$template                : The template helper object (WJECF_Pro_Free_Products_Template)

**************************************************************************

The form must return:

	{$name_prefix}["coupon"] = $coupon_code
	{$name_prefix}["product"][]["product_id"] = product_id
	{$name_prefix}["product"][]["quantity"]   = quantity
	{$name_prefix}["product"][]["attributes"] = attributes for variations

Or, for radiobuttons:
	{$name_prefix}["selected_product"] = $product_id

**************************************************************************/

$input_type = $allow_multiple_products ? 'number' : 'radio';
$tooltip    = sprintf(
	_n( 'You can select one free product.', 'You can select up to %d free products.', $max_quantity, 'woocommerce-jos-autocoupon' ),
	$max_quantity
);

// Decide what is the prettiest amount of columns to display
// Defaults to 4, 3 on large screen, 2 on small screen. Use less columns if possible without adding an extra row on screen.
// GEWIJZIGD: Toon producten altijd in 3 kolommen
$n = 3;
$class = 'wjecf-cols cols-' . ceil( $n / ceil( $n / 4 ) ) . ' cols-lg-' . ceil( $n / ceil( $n / 3 ) ) . ' cols-sm-' . ceil( $n / ceil( $n / 2 ) );

?>
<div id="wjecf-select-free-products" class="wjecf-select-free-products coupon-<?php echo esc_attr( sanitize_title( $coupon_code ) ); ?>">
	<!-- GEWIJZIGD: H4 i.p.v. H3 en maximum aantal gratis producten vermelden -->
	<h4><?= WJECF_API()->get_select_free_product_message( $coupon ); ?> (max. <?= $max_quantity; ?> repen):</h4>
	<input type="hidden" name="<?php echo $name_prefix; ?>[coupon]" value="<?php echo esc_attr( $coupon_code ); ?>" />
	<input type="hidden" id="<?php echo $totalizer_id; ?>" data-wjecf-qty-max="<?php echo $max_quantity; ?>" />
	<ul class="<?php echo esc_attr( $class ); ?>">
	<?php
	foreach ( $form_items as $key => $form_item ) :
		$product = $form_item->getProduct();
		// GEWIJZIGD: Check of het product ook niet tijdelijk uit voorraad is
		if ( ! $product->is_on_backorder() ) :
		?>
			<li data-wjecf-free-product-group="<?php echo $form_item->field_id; ?>">
				<?php
					// GEWIJZIGD: Toon packshot en productnaam vòòr input
					echo $product->get_image() . '<br/>';
					echo '<label for="' . $form_item->field_id . '">' . esc_html( $product->get_name(), 'woocommerce' ) . '</label>';
					$template->render_form_item_variations( $form_item );
					
					$template->render_form_item_input(
						$form_item, array(
							'type'  => $input_type,
							// GEWIJZIGD: Schakel tooltip uit
							// 'title' => $tooltip,
							// GEWIJZIGD: Voeg een klasse toe zodat we via JavaScript een cart refresh kunnen triggeren
							'class' => 'trigger-cart-refresh',
						)
					);
				?>
			</li>
		<?php
		endif;
	endforeach;
	?>
	</ul>
</div>