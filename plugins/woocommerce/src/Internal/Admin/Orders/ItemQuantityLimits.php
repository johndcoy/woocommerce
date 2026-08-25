<?php
declare( strict_types = 1 );

namespace Automattic\WooCommerce\Internal\Admin\Orders;

use WC_Order_Factory;
use WC_Order_Item_Product;
use WC_Product;

/**
 * Admin-side rules for the quantities an order line item accepts.
 *
 * The admin order editor renders quantity inputs with a minimum of 0, so
 * merchants cannot enter negative quantities. Orders created through the
 * REST API or by extensions may contain negative quantities; those are not
 * supported in the admin editor, and editing them requires raising the
 * quantity to the minimum (or filtering it via
 * 'woocommerce_quantity_input_min_admin').
 */
class ItemQuantityLimits {

	/**
	 * Validate the quantity requested for a product being added to an order.
	 *
	 * @since 11.2.0
	 * @param float      $qty     Requested quantity.
	 * @param WC_Product $product Product being added.
	 * @return void
	 * @throws \Exception When the quantity is below the allowed minimum.
	 */
	public function validate_new_item_quantity( float $qty, WC_Product $product ): void {
		/**
		 * This filter is documented in includes/admin/meta-boxes/views/html-order-item.php
		 *
		 * @since 5.8.0
		 */
		$min = (float) apply_filters( 'woocommerce_quantity_input_min_admin', '0', $product, 'add' );

		if ( $qty < $min ) {
			throw new \Exception(
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- The message is shown in a JS alert, not rendered as HTML.
				html_entity_decode(
					wp_strip_all_tags(
						sprintf(
							/* translators: 1: product name, 2: minimum quantity accepted */
							__( 'The quantity of "%1$s" must be %2$s or higher.', 'woocommerce' ),
							$product->get_name(),
							wc_format_localized_decimal( (string) $min )
						)
					),
					ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401
				)
			);
		}
	}

	/**
	 * Validate the order_item_qty values posted by the admin order items screen.
	 *
	 * @since 11.2.0
	 * @param array $items Posted items, as parsed from the serialized form data
	 *                     (the same shape wc_save_order_items receives).
	 * @return void
	 * @throws \Exception When a quantity is below the allowed minimum.
	 */
	public function validate_posted_item_quantities( array $items ): void {
		if ( empty( $items['order_item_qty'] ) || ! is_array( $items['order_item_qty'] ) ) {
			return;
		}

		foreach ( $items['order_item_qty'] as $item_id => $posted_qty ) {
			$item = WC_Order_Factory::get_order_item( absint( $item_id ) );

			if ( ! $item instanceof WC_Order_Item_Product ) {
				continue;
			}

			$qty = (float) wc_stock_amount( wp_unslash( $posted_qty ) );

			/**
			 * This filter is documented in includes/admin/meta-boxes/views/html-order-item.php
			 *
			 * @since 5.8.0
			 */
			$min = (float) apply_filters( 'woocommerce_quantity_input_min_admin', '0', $item->get_product(), 'edit' );

			if ( $qty < $min ) {
				throw new \Exception(
					// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- The message is shown in a JS alert, not rendered as HTML.
					html_entity_decode(
						wp_strip_all_tags(
							sprintf(
								/* translators: 1: order item name, 2: minimum quantity accepted */
								__( 'The quantity of "%1$s" must be %2$s or higher.', 'woocommerce' ),
								$item->get_name(),
								wc_format_localized_decimal( (string) $min )
							)
						),
						ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401
					)
				);
			}
		}
	}
}
