<?php
/**
 * Plugin Name: Store Kit
 * Description: Reusable WooCommerce storefront settings, homepage, product videos and safe store setup.
 * Version: 1.0.0
 * Author: Store Kit Contributors
 * License: GPL-2.0-or-later
 * Requires at least: 6.5
 * Requires PHP: 8.1
 * Requires Plugins: woocommerce
 * Text Domain: store-kit
 */
namespace StoreKit;

defined( 'ABSPATH' ) || exit;

define( 'STORE_KIT_VERSION', '1.0.0' );
define( 'STORE_KIT_URL', plugin_dir_url( __FILE__ ) );
define( 'STORE_KIT_PATH', plugin_dir_path( __FILE__ ) );

require __DIR__ . '/includes/settings.php';
require __DIR__ . '/includes/site-content.php';
// Validators are public APIs for CLI import/setup as well as the settings screen.
require __DIR__ . '/includes/admin.php';
require __DIR__ . '/includes/videos.php';
require __DIR__ . '/includes/catalog.php';
require __DIR__ . '/includes/home.php';
require __DIR__ . '/includes/policies.php';
require __DIR__ . '/includes/demo.php';
require __DIR__ . '/includes/blueprints.php';

add_action( 'wp_enqueue_scripts', static function (): void {
	wp_enqueue_style( 'sk-front', STORE_KIT_URL . 'assets/front.css', [], STORE_KIT_VERSION );
} );

add_action( 'admin_notices', static function (): void {
	if ( ! class_exists( 'WooCommerce' ) && current_user_can( 'activate_plugins' ) ) {
		echo '<div class="notice notice-error"><p>Store Kit cần WooCommerce được cài đặt và kích hoạt để hiển thị cửa hàng.</p></div>';
	}
} );
