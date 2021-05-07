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

define( 'BKAPMH_VERSION', '1.0.0' );

if ( ! defined( 'BKAPMH_FILE' ) ) {
	define( 'BKAPMH_FILE', __FILE__ );
}

if ( ! defined( 'BKAPMH_PLUGIN_PATH' ) ) {
	define( 'BKAPMH_PLUGIN_PATH', untrailingslashit( plugin_dir_path( __FILE__ ) ) );
}

if ( ! defined( 'BKAPMH_PLUGIN_URL' ) ) {
	define( 'BKAPMH_PLUGIN_URL', untrailingslashit( plugins_url( '/', __FILE__ ) ) );
}

define( 'BKAPMH_BOOKINGS_INCLUDE_PATH', BKAPMH_PLUGIN_PATH . '/includes/' );

/**
 * Including Files.
 *
 * @since 1.0.0
 */
function bkapmh_include_files() {
	$include_files = array(
		'bkap-migration-functions.php',
	);

	foreach ( $include_files as $include_file ) {
		include_once BKAPMH_BOOKINGS_INCLUDE_PATH . $include_file;
	}
}
add_action( 'admin_init', 'bkapmh_include_files', 1 );

/**
 * Including Files.
 *
 * @since 1.0.0
 */
function bkap_async_action_init() {
	as_enqueue_async_action( 'bkap_migration_helper_hook', array(), '' );
}
//add_action( 'admin_init', 'bkap_async_action_init' );

//add_action( 'bkap_migration_helper_hook', 'bkap_multiple_recurring_migration_helper' );

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

/**
 * This function updates the required data.
 *
 * @since 1.0.0
 */
function bkap_bookings_update_db_check( $booking_plugin_version, $current_plugin_version ){

	global $wpdb;

	if ( function_exists( 'get_booking_version' ) ) {
		$booking_plugin_version = get_option( 'woocommerce_booking_db_version' );
		$current_plugin_version = get_booking_version();

		if ( $booking_plugin_version != $current_plugin_version ) {
			if ( $booking_plugin_version <= '2.4.4' ) {

				$global_settings = json_decode( get_option( 'woocommerce_booking_global_settings' ) );
				if ( isset( $global_settings ) && ! isset( $global_settings->woo_gf_product_addon_option_price ) ) {
					$global_settings->woo_gf_product_addon_option_price = 'on';
					update_option( 'woocommerce_booking_global_settings', json_encode( $global_settings ) );
				}
			}
		
			$table_name        = $wpdb->prefix . 'booking_history';
			$check_table_query = "SHOW COLUMNS FROM $table_name LIKE 'status'";
		
			$results = $wpdb->get_results( $check_table_query );
		
			if ( count( $results ) == 0 ) {
				$alter_table_query = "ALTER TABLE $table_name
											ADD `status` varchar(20) NOT NULL AFTER  `available_booking`";
				$wpdb->get_results( $alter_table_query );
			}
		
			//
		
			// add setting to set WooCommerce Price to be displayed.
			if ( $booking_plugin_version <= '2.6.2' ) {
		
				$global_settings = json_decode( get_option( 'woocommerce_booking_global_settings' ) );
				if ( ! isset( $global_settings->hide_variation_price ) ) {
					$global_settings->hide_variation_price = '';
					update_option( 'woocommerce_booking_global_settings', json_encode( $global_settings ) );
				}
			}
		
			// add setting to set WooCommerce Price to be displayed.
			if ( $booking_plugin_version <= '2.9' ) {
		
				$global_settings = json_decode( get_option( 'woocommerce_booking_global_settings' ) );
				if ( ! isset( $global_settings->display_disabled_buttons ) ) {
					$global_settings->display_disabled_buttons = '';
					update_option( 'woocommerce_booking_global_settings', json_encode( $global_settings ) );
				}
			}
		
			// add setting to set WooCommerce Price to be displayed.
			if ( $booking_plugin_version <= '3.1' ) {
		
				$global_settings = json_decode( get_option( 'woocommerce_booking_global_settings' ) );
				if ( ! isset( $global_settings->hide_booking_price ) ) {
					$global_settings->hide_booking_price = '';
					update_option( 'woocommerce_booking_global_settings', json_encode( $global_settings ) );
				}
		
				// update the booking history table.
				// 1. delete all inactive base records for recurring weekdays (with & without time slots).
				$delete_base_query = "DELETE FROM `" . $wpdb->prefix . "booking_history`
									   WHERE status = 'inactive'
									   AND weekday <> ''
									   AND start_date = '0000-00-00'";
				$wpdb->query( $delete_base_query );
		
				// 2. delete all inactive specific date records (with & without time slots).
		
				// get a past date 3 months from today.
				$date = date( 'Y-m-d', strtotime( '-3 months' ) );
				// fetch all inactive specific date records starting from 3 months past today.
				$select_specific = "SELECT id, post_id, start_date, from_time, to_time FROM `" . $wpdb->prefix . "booking_history`
									   WHERE status = 'inactive'
									   AND weekday = ''
									   AND start_date <> '0000-00-00'
									   AND end_date = '0000-00-00'
									   AND start_date >= %d";
		
				$result_specific = $wpdb->get_results( $wpdb->prepare( $select_specific, $date ) );
		
				foreach ( $result_specific as $key => $value ) {
					$select_active_specific = "SELECT id FROM `" . $wpdb->prefix . "booking_history`
											   WHERE status <> 'inactive'
											   AND post_id = %d
											   AND start_date = %s
											   AND end_date = '0000-00-00'
											   AND from_time = %s
											   AND to_time = %s";
					$results_active         = $wpdb->get_results( $wpdb->prepare( $select_active_specific, $value->post_id, $value->start_date, $value->from_time, $value->to_time ) );
		
					if ( isset( $results_active ) && 1 == count( $results_active ) ) {
		
						// delete the inactive record if a corresponding active record is found.
						$delete_inactive_specific = "DELETE FROM `" . $wpdb->prefix . "booking_history`
													   WHERE ID = '" . $value->id . "'";
						$wpdb->query( $delete_inactive_specific );
					}
				}
				// delete all inactive specific date records older than 3 months from today.
				$delete_specific = "DELETE FROM `" . $wpdb->prefix . "booking_history`
									   WHERE status = 'inactive'
									   AND weekday = ''
									   AND start_date <> '0000-00-00'
									   AND start_date < '" . $date . "'
									   AND end_date = '0000-00-00'";
				$wpdb->query( $delete_specific );
		
				//
		
				// Get the option setting to check if adbp has been updated to hrs for existing users.
				$booking_abp_hrs = get_option( 'woocommerce_booking_abp_hrs' );
		
				if ( 'HOURS' !== $booking_abp_hrs ) {
					// For all the existing bookable products, modify the ABP to hours instead of days.
					$args    = array(
						'post_type'      => 'product',
						'posts_per_page' => -1,
					);
					$product = query_posts( $args );
		
					$product_ids = array();
					foreach ( $product as $k => $v ) {
						$product_ids[] = $v->ID;
					}
		
					if ( is_array( $product_ids ) && count( $product_ids ) > 0 ) {
						foreach ( $product_ids as $k => $v ) {
							$booking_settings = get_post_meta( $v, 'woocommerce_booking_settings', true );
		
							if ( isset( $booking_settings ) && isset( $booking_settings['booking_enable_date'] ) && 'on' === $booking_settings['booking_enable_date'] ) {
		
								if ( isset( $booking_settings['booking_minimum_number_days'] ) && $booking_settings['booking_minimum_number_days'] > 0 ) {
									$advance_period_hrs                              = $booking_settings['booking_minimum_number_days'] * 24;
									$booking_settings['booking_minimum_number_days'] = $advance_period_hrs;
									update_post_meta( $v, 'woocommerce_booking_settings', $booking_settings );
								}
							}
						}
						update_option( 'woocommerce_booking_abp_hrs', 'HOURS' );
					}
				}
		
				//
		
				// Get the option setting to check if tables are set to utf8 charset.
				$alter_queries = get_option( 'woocommerce_booking_alter_queries' );
		
				if ( 'yes' !== $alter_queries ) {
					// For all the existing bookable products, modify the ABP to hours instead of days.
					$table_name = $wpdb->prefix . 'booking_history';
					$sql_alter  = "ALTER TABLE $table_name CONVERT TO CHARACTER SET utf8";
					$wpdb->get_results( $sql_alter );
		
					$order_table_name = $wpdb->prefix . 'booking_order_history';
					$order_alter_sql  = "ALTER TABLE $order_table_name CONVERT TO CHARACTER SET utf8";
					$wpdb->get_results( $order_alter_sql );
		
					$table_name_price = $wpdb->prefix . 'booking_block_price_meta';
					$sql_alter_price  = "ALTER TABLE $table_name_price CONVERT TO CHARACTER SET utf8";
					$wpdb->get_results( $sql_alter_price );
		
					$table_name_meta = $wpdb->prefix . 'booking_block_price_attribute_meta';
					$sql_alter_meta  = "ALTER TABLE $table_name_meta CONVERT TO CHARACTER SET utf8";
					$wpdb->get_results( $sql_alter_meta );
		
					$block_table_name = $wpdb->prefix . 'booking_fixed_blocks';
					$blocks_alter_sql = "ALTER TABLE $block_table_name CONVERT TO CHARACTER SET utf8";
					$wpdb->get_results( $blocks_alter_sql );
		
					update_option( 'woocommerce_booking_alter_queries', 'yes' );
				}
		
				//
		
				if ( get_option( 'bkap_update_booking_labels_settings' ) != 'yes' && $booking_plugin_version < '2.8' ) {
					$booking_date_label = get_option( 'book.date-label' );
					update_option( 'book_date-label', $booking_date_label );
		
					$booking_checkout_label = get_option( 'checkout.date-label' );
					update_option( 'checkout_date-label', $booking_checkout_label );
		
					$bkap_calendar_icon_label = get_option( 'bkap_calendar_icon_file' );
					update_option( 'bkap_calendar_icon_file', $bkap_calendar_icon_label );
		
					$booking_time_label = get_option( 'book.time-label' );
					update_option( 'book_time-label', $booking_time_label );
		
					$booking_time_select_option = get_option( 'book.time-select-option' );
					update_option( 'book_time-select-option', $booking_time_select_option );
		
					$booking_fixed_block_label = get_option( 'book.fixed-block-label' );
					update_option( 'book_fixed-block-label', $booking_fixed_block_label );
		
					$booking_price = get_option( 'book.price-label' );
					update_option( 'book_price-label', $booking_price );
		
					$booking_item_meta_date = get_option( 'book.item-meta-date' );
					update_option( 'book_item-meta-date', $booking_item_meta_date );
		
					$booking_item_meta_checkout_date = get_option( 'checkout.item-meta-date' );
					update_option( 'checkout_item-meta-date', $booking_item_meta_checkout_date );
		
					$booking_item_meta_time = get_option( 'book.item-meta-time' );
					update_option( 'book_item-meta-time', $booking_item_meta_time );
		
					$booking_ics_file = get_option( 'book.ics-file-name' );
					update_option( 'book_ics-file-name', $booking_ics_file );
		
					$booking_cart_date = get_option( 'book.item-cart-date' );
					update_option( 'book_item-cart-date', $booking_cart_date );
		
					$booking_cart_checkout_date = get_option( 'checkout.item-cart-date' );
					update_option( 'checkout_item-cart-date', $booking_cart_checkout_date );
		
					$booking_cart_time = get_option( 'book.item-cart-time' );
					update_option( 'book_item-cart-time', $booking_cart_time );
		
					// delete the labels from wp_options.
					delete_option( 'book.date-label' );
					delete_option( 'checkout.date-label' );
					delete_option( 'book.time-label' );
					delete_option( 'book.time-select-option' );
					delete_option( 'book.fixed-block-label' );
					delete_option( 'book.price-label' );
					delete_option( 'book.item-meta-date' );
					delete_option( 'checkout.item-meta-date' );
					delete_option( 'book.item-meta-time' );
					delete_option( 'book.ics-file-name' );
					delete_option( 'book.item-cart-date' );
					delete_option( 'checkout.item-cart-date' );
					delete_option( 'book.item-cart-time' );
		
					update_option( 'bkap_update_booking_labels_settings', 'yes' );
				}
		
				// from 4.0.0, we're going to save the booking settings as individual meta fields. So update the post meta for all bookable products.
				if ( $booking_plugin_version < '5.8.0' ) {
					bkap_400_update_settings( $booking_plugin_version ); // call the function which will individualize settings for all the bookable products.
				}
		
				// from 4.1.0, we're going to save the fixed blocks and price by range as individual meta fields. So update the post meta for tables of fixed booking block and price by range.
				if ( $booking_plugin_version < '5.8.0' ) {
					bkap_410_update_settings( $booking_plugin_version ); // call the function which will individualize settings for all the bookable products.
				}
			}
		}
	}
}
add_action( 'bkap_bookings_update_db_check', 'bkap_bookings_update_db_check', 10, 2 );


/**
 * Adds a notification for the admin to update the DB manually.
 * This notification is added only if the auto update fails
 *
 * @since 4.0.0
 *
 * @globals mixed $wpdb Global wpdb object
 */
function bkap_update_db_notice() {

	global $wpdb;

	$db_status       = get_option( 'bkap_400_update_db_status' );
	$db_status_410   = get_option( 'bkap_410_update_db_status' );
	$db_status_420   = get_option( 'bkap_420_update_db_status' );
	$gcal_status_420 = get_option( 'bkap_420_update_gcal_status' );

	$class = 'notice notice-error';

	// if the version is 4.2.0 and the update has not been run at all.
	$plugin_version = get_option( 'woocommerce_booking_db_version' );

	// This is done to ensure that for fresh installations no notices are displayed.
	$bookings_query = 'SELECT * FROM `' . $wpdb->prefix . 'booking_order_history`';
	$bookings_array = $wpdb->get_results( $bookings_query );

	if ( isset( $bookings_array ) && empty( $bookings_array ) ) {
		update_option( 'bkap_420_update_db_status', 'success' );
		update_option( 'bkap_420_update_gcal_status', 'success' );
	}

	$valid_status = array( 'fail', 'success' );
	// step 1 has not been run.
	if ( isset( $plugin_version ) && '4.1.0' <= $plugin_version &&
		isset( $db_status_420 ) && ! in_array( $db_status_420, $valid_status ) &&
		isset( $gcal_status_420 ) && ! in_array( $gcal_status_420, $valid_status ) &&
		isset( $bookings_array ) && ! empty( $bookings_array ) ) {

		$class  .= ' is-dismissible';
		$message = '
		<table width="100%">
			<tr>
				<td style="text-align:left;">';

		$message .= __( 'We need to run a database update to migrate your bookings and imported Google Calendar events into the new UI screens. Please click on the Update Now button to start the process.', 'woocommerce-booking' );

		$message .= '</td>
				<td style="text-align:right;">
					<button type="submit" class="button-primary" id="bkap_db_420_update"  onClick="bkap_400_db_update()">';

		$message .= __( 'Update Now', 'woocommerce-booking' );

		$message .= '
					</button>
				</td>
			</tr>
		</table>';

		printf( '<div class="%1$s">%2$s</div>', $class, $message );
	}
	if ( ( isset( $db_status ) && 'fail' === strtolower( $db_status ) ) ||
		( isset( $db_status_410 ) && 'fail' === strtolower( $db_status_410 ) ) ||
		( isset( $db_status_420 ) && 'fail' === strtolower( $db_status_420 ) ) ||
		( isset( $gcal_status_420 ) && 'fail' === strtolower( $gcal_status_420 ) ) ) {

		$message = '
		<table width="100%">
			<tr>
				<td style="text-align:left;">';

		$message .= __( 'The automatic database update for Booking & Appointment plugin for WooCommerce has failed. Please click on the Update button to manually update the database.', 'woocommerce-booking' );

		$message .= '</td>
				<td style="text-align:right;">
					<button type="submit" class="button-primary" id="bkap_db_update"  onClick="bkap_400_db_update()">';

		$message .= __( 'Update', 'woocommerce-booking' );

		$message .= '
					</button>
				</td>
			</tr>
		</table>';

		printf( '<div class="%1$s">%2$s</div>', $class, $message );
	}
}
add_action( 'admin_notices', 'bkap_update_db_notice', 10 );

/**
 * This function includes the functions of AJAX calls.
 *
 * @since 1.0.0
 */
function bkapmh_book_load_ajax_admin(){
	add_action( 'wp_ajax_bkap_manual_db_update', 'bkap_manual_db_update' );
	add_action( 'wp_ajax_bkap_manual_db_update_f_p', 'bkap_manual_db_update_f_p' );
	add_action( 'wp_ajax_bkap_manual_db_update_v420', 'bkap_manual_db_update_v420' );
}
add_action( 'admin_init', 'bkapmh_book_load_ajax_admin' );

/**
 * This function loads the JS file.
 *
 * @since 1.0.0
 */
function bkapmh_my_enqueue_scripts_js(){

	$plugin_version_number = get_option( 'woocommerce_booking_db_version' );
	$ajax_url              = get_admin_url() . 'admin-ajax.php';

	wp_register_script( 'bkap-update', plugins_url() . '/bkap-migration-helper/assets/js/bkap-update.js', '', $plugin_version_number, false );

	$settings_url = get_admin_url() . 'edit.php?post_type=bkap_booking&page=woocommerce_booking_page';
	$support_msg  = __( 'The database update has failed. Request you to kindly contact ', 'woocommerce-booking' );
	$support_msg .= '<a href="https://www.tychesoftwares.com/forums/forum/woocommerce-booking-appointment-plugin/">' . __( 'support', 'woocommerce-booking' ) . '</a>';
	$support_msg .= __( ' at Tyche Softwares.', 'woocommerce-booking' );

	$success_msg = __( 'The database update was successful.', 'woocommerce-booking' );

	$progress_msg = "<p>Updating the database for $plugin_version_number. This may take a while. Please do not refresh the page until further notification.</p>";
	$progress_msg = __( $progress_msg, 'woocommerce-booking' );

	$progress_msg_f_p = '<p>Updating the database for v4.1.0. This may take a while. Please do not refresh the page until further notification.</p>';
	$progress_msg_f_p = __( $progress_msg_f_p, 'woocommerce-booking' );

	wp_localize_script(
		'bkap-update',
		'bkap_update_params',
		array(
			'settings_url'    => $settings_url,
			'ajax_url'        => $ajax_url,
			'support_request' => $support_msg,
			'success_msg'     => $success_msg,
			'progress'        => $progress_msg,
			'progress_f_p'    => $progress_msg_f_p,
		)
	);

	wp_enqueue_script( 'bkap-update' );
}
add_action( 'admin_enqueue_scripts', 'bkapmh_my_enqueue_scripts_js' );

/**
 * This function display the update informations.
 *
 * @since 1.0.0
 */
function bkapmh_settings_tab_content( $action ) {
	if ( 'bkap-update' === $action ) {
		bkap_400_update_db_tab();
	}
}
add_action( 'bkap_settings_tab_content', 'bkapmh_settings_tab_content', 10, 1 );

/**
 * This function Add the DB Update tab on the settings page.
 *
 * @since 1.0.0
 */
function bkapmh_add_global_settings_tab() {

	$db_status = get_option( 'bkap_400_update_db_status' );
	if ( isset( $db_status ) && 'fail' === strtolower( $db_status ) ) {
		?>
		<!-- Database Update -->
		<a  href="admin.php?page=woocommerce_booking_page&action=bkap-update" class="nav-tab <?php echo esc_attr( $update_process ); ?>"><?php esc_html_e( 'Database Update', 'woocommerce-booking' ); ?></a>
		<?php
	}
}
add_action( 'bkap_add_global_settings_tab', 'bkapmh_add_global_settings_tab' );

/**
 * Adding the div in the notice to display the message after mapping the event.
 *
 * @globals string $post_type
 * @globals string $pagenoq
 *
 * @since 4.2.0
 *
 * @hook admin_notices
 */
function bkap_bulk_admin_notices() {
	global $post_type, $pagenow;

	if ( 'edit.php' == $pagenow && 'bkap_gcal_event' == $post_type ) {
		// check the DB update status
		if ( strtolower( get_option( 'bkap_420_update_gcal_status' ) ) == '' ) {

			$message = 'This time, we\'ve changed the way events imported from Google Calendar are stored and displayed in the Booking & Appointment Plugin. To ensure you continue to see the old imported events here. Please run the DB Upgrade process.';
			$message = __( $message, 'woocommerce-booking' );
			echo '<div class="updated"><p>' . $message . '</p></div>';

		}
		echo '<div id="bkap_display_notice"></div>';
	}
}
add_action( 'admin_notices', 'bkap_bulk_admin_notices' );
