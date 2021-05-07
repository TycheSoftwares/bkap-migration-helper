<?php
/**
 * Booking & Appointment Plugin for WooCommerce Migration Helper
 *
 * @author      Tyche Softwares
 * @category    Core
 * @package     BKAPMH/Uninstall
 * @version     1.0.0
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

require_once ABSPATH . 'wp-admin/includes/upgrade.php';

/**
 * Delete the data for the WordPress Multisite.
 */
if ( is_multisite() ) {

	$bkap_blog_list = get_sites();

	foreach ( $bkap_blog_list as $bkap_blog_list_key => $bkap_blog_list_value ) {


		$bkap_blog_id = $bkap_blog_list_value->blog_id;

		/**
		 * It indicates the sub site id.
		 */
		if ( $bkap_blog_id > 1 ) {
			$bkap_multisite_prefix = $wpdb->prefix . $bkap_blog_id . '_';
		} else {
			$bkap_multisite_prefix = $wpdb->prefix;
		}

		delete_blog_option( $bkap_blog_id, 'woocommerce_booking_alter_queries' );

		// Delete the option records for DB update.
		delete_blog_option( $bkap_blog_id, 'bkap_400_manual_update_count' );
		delete_blog_option( $bkap_blog_id, 'bkap_400_update_db_status' );

		delete_blog_option( $bkap_blog_id, 'bkap_410_manual_update_count' );
		delete_blog_option( $bkap_blog_id, 'bkap_410_update_db_status' );

		delete_blog_option( $bkap_blog_id, 'bkap_420_update_gcal_meta' );
		delete_blog_option( $bkap_blog_id, 'bkap_420_update_stats' );
		delete_blog_option( $bkap_blog_id, 'bkap_420_update_db_status' );
		delete_blog_option( $bkap_blog_id, 'bkap_420_gcal_update_stats' );

		delete_blog_option( $bkap_blog_id, 'bkap_420_update_gcal_status' );
		delete_blog_option( $bkap_blog_id, 'bkap_420_manual_update_count' );
	}
} else {

	delete_option( 'woocommerce_booking_alter_queries' );

	// Delete the option records for DB update.
	delete_option( 'bkap_400_manual_update_count' );
	delete_option( 'bkap_400_update_db_status' );

	delete_option( 'bkap_410_manual_update_count' );
	delete_option( 'bkap_410_update_db_status' );

	delete_option( 'bkap_420_update_gcal_meta' );
	delete_option( 'bkap_420_update_stats' );
	delete_option( 'bkap_420_update_db_status' );
	delete_option( 'bkap_420_gcal_update_stats' );

	delete_option( 'bkap_420_update_gcal_status' );
	delete_option( 'bkap_420_manual_update_count' );
}

// Clear any cached data that has been removed.
wp_cache_flush();
