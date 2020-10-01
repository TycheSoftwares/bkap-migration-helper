<?php
/**
 * Plugin Name: Booking Plugin migration helper
 * Plugin URI: http://www.tychesoftwares.com/store/premium-plugins/woocommerce-booking-plugin
 * Description: This plugin is for migrating settings from older version to newer version of Booking plugin.
 * Version: 1.0.0
 * Author: Tyche Softwares
 * Author URI: http://www.tychesoftwares.com/
 * Text Domain: woocommerce-booking
 * Requires PHP: 5.6
 * WC requires at least: 3.9
 * WC tested up to: 4.4
 *
 * @package  BKAP
 */

add_action( 'admin_init', 'bkap_async_action_init' );

function bkap_async_action_init() {
	as_enqueue_async_action( 'bkap_migration_helper_hook', array(), '' );
}

add_action( 'bkap_migration_helper_hook', 'bkap_multiple_recurring_migration_helper' );

function bkap_multiple_recurring_migration_helper() {

	if( 'done' !== get_option( 'bkap_migrate_recurring_days_helper_as' ) ) {
		$post_status = array( 'publish', 'pending', 'draft', 'auto-draft', 'future', 'private', 'inherit' );

		$args = array(
			'post_type'      => array( 'product' ),
			'posts_per_page' => -1,
			'post_status'    => $post_status,
		);

		$product = get_posts( $args );

		foreach ( $product as $k => $value ) {
			$productid = $value->ID;

			$bookable = bkap_common::bkap_get_bookable_status( $productid );

			// if the product is bookable
			if ( $bookable ) {
				bkap_400_create_meta( $productid );
				bkap_400_recurring_data( $productid );
			}
		}
		update_option( 'bkap_migrate_recurring_days_helper_as', 'done' );
	}
}
