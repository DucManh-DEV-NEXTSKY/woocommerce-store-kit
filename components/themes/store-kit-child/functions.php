<?php
/** Store Kit design integration. Commerce and content remain in the Store Kit plugin. */
namespace StoreKitTheme;

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/inc/design.php';

// A fresh Blocksy Free header omits its native cart. Add it to defaults only:
// saved Customizer placements remain untouched, including deliberate cart removal.
add_filter( 'blocksy:header:default_value', static function ( array $value ): array {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return $value;
	}
	foreach ( $value['sections'] as &$section ) {
		foreach ( [ 'desktop', 'mobile' ] as $device ) {
			foreach ( $section[ $device ] as &$row ) {
				if ( 'middle-row' !== $row['id'] ) {
					continue;
				}
				foreach ( $row['placements'] as &$placement ) {
					if ( 'end' === $placement['id'] && ! in_array( 'cart', $placement['items'], true ) ) {
						$placement['items'][] = 'cart';
					}
				}
				unset( $placement );
			}
			unset( $row );
		}
	}
	unset( $section );
	return $value;
} );

add_action( 'wp_enqueue_scripts', static function (): void {
	$dependencies = [ 'ct-main-styles' ];
	if ( wp_style_is( 'sk-front', 'registered' ) ) {
		$dependencies[] = 'sk-front';
	}
	$file = get_stylesheet_directory() . '/assets/site.css';
	wp_enqueue_style( 'store-kit-child', get_stylesheet_directory_uri() . '/assets/site.css', $dependencies, (string) filemtime( $file ) );
	wp_add_inline_style( 'store-kit-child', design_css( current_design() ) );
	// Both selected font families are local. Do not also request Google font stylesheets.
	wp_dequeue_style( 'blocksy-fonts-font-source-google' );
}, 100 );

function brand(): string {
	$value = function_exists( '\StoreKit\Settings\get' ) ? (string) \StoreKit\Settings\get( 'brand' ) : '';
	return '' !== trim( $value ) ? $value : get_bloginfo( 'name' );
}

/** Zero deliberately selects the text brand, not a stale Customizer image. */
function logo_id(): int {
	if ( ! function_exists( '\StoreKit\Settings\get' ) ) {
		return (int) get_theme_mod( 'custom_logo', 0 );
	}
	$id = (int) \StoreKit\Settings\get( 'logo_id' );
	return $id > 0 && wp_attachment_is_image( $id ) ? $id : 0;
}

// Use native logo rendering in every header/device state; retain all menu/cart/search behaviour.
add_filter( 'blocksy:header:item-template-args', static function ( array $args ): array {
	if ( 'logo' !== explode( '~', (string) ( $args['item_id'] ?? '' ) )[0] || ! function_exists( '\StoreKit\Settings\get' ) ) {
		return $args;
	}
	$id = logo_id();
	$args['atts'] = array_replace( $args['atts'], [
		'custom_logo' => $id, 'transparent_logo' => $id, 'sticky_logo' => $id,
		'blogname' => brand(), 'has_site_title' => $id ? 'no' : 'yes',
		'blogname_visibility' => [ 'desktop' => true, 'tablet' => true, 'mobile' => true ],
		'has_logo_image_link' => 'yes', 'has_site_title_link' => 'yes',
		'header_logo_aria_label' => brand(),
	] );
	return $args;
} );
add_filter( 'blocksy:header:logo:image-id', static function ( $id ) {
	return function_exists( '\StoreKit\Settings\get' ) ? logo_id() : $id;
} );
add_filter( 'blocksy:panel-builder:logo:additional-logos', static function ( array $logos, array $atts, string $device, string $panel_type ): array {
	if ( 'header' !== $panel_type || ! function_exists( '\StoreKit\Settings\get' ) ) {
		return $logos;
	}
	$id = logo_id();
	foreach ( $logos as &$logo ) {
		$logo['id'] = $id;
	}
	unset( $logo );
	return $id ? $logos : [];
}, 10, 4 );

// Keep native stock/sale and Store Kit extension badges in one wrapping group.
add_action( 'after_setup_theme', static function (): void {
	if ( ! function_exists( '\StoreKit\Catalog\badges_html' ) ) {
		return;
	}
	remove_action( 'woocommerce_before_shop_loop_item_title', 'StoreKit\\Catalog\\card_badges', 20 );
	add_filter( 'blocksy:woocommerce:product-card:badges', static function ( array $badges ): array {
		$html = implode( '', $badges ) . \StoreKit\Catalog\badges_html( get_the_ID() );
		return $html ? [ '<span class="sk-card-badges">' . $html . '</span>' ] : [];
	} );
} );

// Contain text-bearing article covers rather than cropping their headings on native archives.
add_filter( 'blocksy:posts-listing:archive-order', static function ( array $layers ): array {
	if ( 'post' !== get_post_type() ) {
		return $layers;
	}
	foreach ( $layers as $index => $layer ) {
		if ( 'featured_image' === $layer['id'] ) {
			$layers[ $index ]['thumb_ratio'] = '1/1';
		}
	}
	return $layers;
} );
