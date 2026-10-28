<?php
/**
 * Controlled homepage plus reusable section shortcodes, announcement and quick contact navigation.
 */

namespace StoreKit\Home;

use function StoreKit\Settings\get;
use function StoreKit\Settings\phone;

defined( 'ABSPATH' ) || exit;

add_shortcode( 'store_kit_home', __NAMESPACE__ . '\\home' );
add_shortcode( 'store_kit_hero', __NAMESPACE__ . '\\hero' );
add_shortcode( 'store_kit_section', __NAMESPACE__ . '\\section' );
add_shortcode( 'store_kit_finder', __NAMESPACE__ . '\\finder' );
add_shortcode( 'store_kit_posts', __NAMESPACE__ . '\\posts' );
add_shortcode( 'store_kit_steps', __NAMESPACE__ . '\\steps' );
add_action( 'wp_footer', __NAMESPACE__ . '\\action_bar' );
add_action( 'wp_body_open', __NAMESPACE__ . '\\announcement', 5 );

/** Ordered, independently enabled problem rows from the shared settings. */
function problems(): array {
	return (array) get( 'problems' );
}

/** Problems whose category currently has published products. */
function live_problems(): array {
	$out = [];
	foreach ( problems() as $p ) {
		if ( empty( $p['enabled'] ) ) {
			continue;
		}
		$term = get_term( (int) $p['category_id'], 'product_cat' );
		if ( $term instanceof \WP_Term && $term->count > 0 ) {
			$out[] = [ $p, $term ];
		}
	}
	return $out;
}

function hero( $atts = [] ): string {
	$a = shortcode_atts(
		[
			'title' => get( 'hero_title' ),
			'lede'  => get( 'hero_lede' ),
		],
		$atts,
		'store_kit_hero'
	);
	$chips = '';
	foreach ( array_slice( live_problems(), 0, 6 ) as [ $p, $term ] ) {
		if ( '' === trim( (string) $p['chip'] ) ) {
			continue;
		}
		$chips .= sprintf( '<a class="sk-chip" href="%s">%s</a>', esc_url( get_term_link( $term ) ), esc_html( $p['chip'] ) );
	}
	return sprintf(
		'<section class="sk-hero alignfull"><div class="sk-wrap"><div class="sk-hero__panel">
		<div class="sk-hero__text"><span class="sk-pill">%6$s</span><h1 class="sk-hero__title">%1$s</h1><p class="sk-lede">%2$s</p>
		<form class="sk-search" role="search" action="%3$s"><label class="screen-reader-text" for="sk-q">Tìm sản phẩm</label><input id="sk-q" name="s" type="search" placeholder="Tìm theo tên sản phẩm" autocomplete="off"><input type="hidden" name="post_type" value="product"><button type="submit">Tìm</button></form>
		<nav class="sk-chips" aria-label="Nhóm sản phẩm">%4$s</nav></div>%5$s</div></div></section>',
		esc_html( $a['title'] ),
		esc_html( $a['lede'] ),
		esc_url( home_url( '/' ) ),
		$chips,
		\StoreKit\Videos\shortcode( [ 'placement' => 'hero', 'limit' => 0, 'eager' => 1, 'class' => 'sk-reels--shelf' ] ),
		esc_html( get( 'hero_badge' ) )
	);
}

/**
 * [store_kit_section title="" eyebrow="" lede="" align="center|start" tone="light|tile" link_text="" link_url=""]…[/store_kit_section]
 */
function section( $atts, $content = '' ): string {
	return section_markup( $atts, do_shortcode( shortcode_unautop( trim( $content ) ) ) );
}

/** Wrap already-rendered content without interpreting editable text as new shortcodes. */
function section_markup( $atts, string $content ): string {
	$a = shortcode_atts( [ 'id' => '', 'title' => '', 'eyebrow' => '', 'lede' => '', 'align' => 'center', 'link_text' => '', 'link_url' => '', 'tone' => 'light' ], $atts, 'store_kit_section' );
	$id      = $a['id'] ? ' id="' . esc_attr( $a['id'] ) . '"' : '';
	$eyebrow = $a['eyebrow'] ? '<p class="sk-eyebrow">' . esc_html( $a['eyebrow'] ) . '</p>' : '';
	$lede    = $a['lede'] ? '<p class="sk-sec__lede">' . esc_html( $a['lede'] ) . '</p>' : '';
	$more    = $a['link_text'] && $a['link_url'] ? sprintf( '<div class="sk-sec__more"><a class="sk-btn sk-btn--ghost" href="%s">%s</a></div>', esc_url( $a['link_url'] ), esc_html( $a['link_text'] ) ) : '';
	return sprintf(
		'<section class="sk-sec sk-sec--%1$s alignfull"%2$s><div class="sk-wrap"><header class="sk-sec__head sk-sec__head--%3$s">%4$s<h2>%5$s</h2>%6$s</header>%7$s%8$s</div></section>',
		esc_attr( sanitize_key( $a['tone'] ) ),
		$id,
		'start' === $a['align'] ? 'start' : 'center',
		$eyebrow,
		esc_html( $a['title'] ),
		$lede,
		$content,
		$more
	);
}

/** Product group collection rows: name + count, short note, one round thumbnail, arrow. */
function finder(): string {
	$rows = '';
	foreach ( live_problems() as [ $p, $term ] ) {
		$ids   = get_posts( [ 'post_type' => 'product', 'post_status' => 'publish', 'posts_per_page' => 1, 'fields' => 'ids', 'tax_query' => [ [ 'taxonomy' => 'product_cat', 'terms' => $term->term_id ] ] ] );
		$thumb = $ids ? get_the_post_thumbnail( $ids[0], 'thumbnail', [ 'alt' => '', 'loading' => 'lazy' ] ) : '';
		$rows .= sprintf(
			'<li><a class="sk-finder__row" href="%1$s"><span class="sk-finder__text"><span class="sk-finder__name">%2$s<sup>%3$d</sup></span><span class="sk-finder__note">%4$s</span></span><span class="sk-finder__thumb">%5$s</span><span class="sk-finder__arrow" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M9 6l6 6-6 6"/></svg></span></a></li>',
			esc_url( get_term_link( $term ) ),
			esc_html( $p['title'] ),
			(int) $term->count,
			esc_html( $p['description'] ),
			$thumb
		);
	}
	return '<ul class="sk-finder">' . $rows . '</ul>';
}

function posts( $atts = [] ): string {
	$a = shortcode_atts( [ 'count' => get( 'posts_count' ) ], $atts, 'store_kit_posts' );
	if ( (int) $a['count'] <= 0 ) {
		return '';
	}
	$posts = get_posts( [ 'post_type' => 'post', 'post_status' => 'publish', 'posts_per_page' => (int) $a['count'] ] );
	if ( ! $posts ) {
		return '';
	}
	$minutes = static fn( \WP_Post $p ) => max( 1, (int) round( count( preg_split( '/\s+/u', trim( wp_strip_all_tags( $p->post_content ) ) ) ) / 230 ) );
	$first   = array_shift( $posts );
	$feature = sprintf(
		'<article class="sk-feature">%1$s<p class="sk-meta">%2$d phút đọc</p><h3><a href="%3$s">%4$s</a></h3><p class="sk-lede">%5$s</p></article>',
		has_post_thumbnail( $first ) ? '<a class="sk-feature__thumb" href="' . esc_url( get_permalink( $first ) ) . '">' . get_the_post_thumbnail( $first, 'large', [ 'loading' => 'lazy' ] ) . '</a>' : '',
		$minutes( $first ),
		esc_url( get_permalink( $first ) ),
		esc_html( get_the_title( $first ) ),
		esc_html( wp_trim_words( get_the_excerpt( $first ), 34 ) )
	);
	$list = '';
	foreach ( $posts as $p ) {
		$list .= sprintf( '<li><p class="sk-meta">%1$d phút đọc</p><h3><a href="%2$s">%3$s</a></h3></li>', $minutes( $p ), esc_url( get_permalink( $p ) ), esc_html( get_the_title( $p ) ) );
	}
	return '<div class="sk-articles">' . $feature . '<ul class="sk-article-list">' . $list . '</ul></div>';
}

function steps(): string {
	$items = implode( '', array_map( static fn( $s ) => sprintf( '<li><h3>%s</h3><p>%s</p></li>', esc_html( $s['title'] ), esc_html( $s['description'] ) ), (array) get( 'steps' ) ) );
	$links = '';
	if ( '' !== (string) get( 'zalo_url' ) ) {
		$links .= sprintf( '<a class="sk-btn sk-btn--primary" href="%s" target="_blank" rel="noopener">Nhắn Zalo %s</a>', esc_url( get( 'zalo_url' ) ), esc_html( get( 'phone' ) ) );
	}
	if ( '' !== phone() ) {
		$links .= '<a class="sk-btn sk-btn--ghost" href="tel:' . esc_attr( phone() ) . '">Gọi cửa hàng</a>';
	}
	return '<ol class="sk-steps">' . $items . '</ol>' . ( $links ? '<div class="sk-contact">' . $links . '</div>' : '' );
}

function announcement(): void {
	if ( '' === trim( (string) get( 'announce_before' ) . (string) get( 'phone' ) . (string) get( 'announce_after' ) ) ) {
		return;
	}
	$contact = esc_html( get( 'phone' ) );
	if ( '' !== (string) get( 'zalo_url' ) ) {
		$contact = sprintf( '<a href="%s" target="_blank" rel="noopener">%s</a>', esc_url( get( 'zalo_url' ) ), $contact ?: 'Nhắn Zalo' );
	}
	printf(
		'<div class="sk-announce">%1$s %2$s<span class="sk-announce__more">%3$s</span></div>',
		esc_html( get( 'announce_before' ) ),
		$contact,
		esc_html( get( 'announce_after' ) )
	);
}

/** Phone bottom navigation, with Zalo and Call instead of Offer/Account. Desktop/tablet get a one-tap contact stack instead. */
function action_bar(): void {
	if ( ! function_exists( 'wc_get_page_permalink' ) ) {
		return;
	}
	$icon  = static fn( string $d ) => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="' . $d . '"/></svg>';
	$zalo  = 'M4 5h16v11H9l-5 4z';
	$phone = 'M6 3h4l2 5-3 2a11 11 0 0 0 5 5l2-3 5 2v4a2 2 0 0 1-2 2A17 17 0 0 1 4 5a2 2 0 0 1 2-2z';
	$items = [
		[ home_url( '/' ), 'Trang chủ', 'M4 10.5 12 4l8 6.5V20h-5v-6H9v6H4z', is_front_page(), '' ],
		[ wc_get_page_permalink( 'shop' ), 'Sản phẩm', 'M4 4h7v7H4zM13 4h7v7h-7zM4 13h7v7H4zM13 13h7v7h-7z', is_shop() || is_product_taxonomy(), '' ],
		[ wc_get_cart_url(), 'Giỏ hàng', 'M5 7h14l-1.5 12h-11zM9 7a3 3 0 0 1 6 0', is_cart(), '' ],
	];
	if ( '' !== (string) get( 'zalo_url' ) ) {
		$items[] = [ get( 'zalo_url' ), 'Zalo', $zalo, false, ' target="_blank" rel="noopener"' ];
	}
	if ( '' !== phone() ) {
		$items[] = [ 'tel:' . phone(), 'Gọi', $phone, false, '' ];
	}
	$links = '';
	foreach ( $items as [ $url, $label, $path, $current, $extra ] ) {
		$links .= sprintf( '<a href="%s"%s%s>%s<span>%s</span></a>', esc_url( $url ), $current ? ' aria-current="page"' : '', $extra, $icon( $path ), esc_html( $label ) );
	}
	echo '<nav class="sk-action-bar" style="--sk-nav-items:' . count( $items ) . '" aria-label="Điều hướng nhanh">' . $links . '</nav>'; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above.
	$floating = '';
	if ( '' !== (string) get( 'zalo_url' ) ) {
		$floating .= sprintf( '<a class="sk-float__zalo" href="%s" target="_blank" rel="noopener" aria-label="Nhắn Zalo %s">%s<span>Zalo</span></a>', esc_url( get( 'zalo_url' ) ), esc_attr( get( 'phone' ) ), $icon( $zalo ) );
	}
	if ( '' !== phone() ) {
		$floating .= sprintf( '<a class="sk-float__call" href="tel:%s" aria-label="Gọi %s">%s<span>Gọi</span></a>', esc_attr( phone() ), esc_attr( get( 'phone' ) ), $icon( $phone ) );
	}
	if ( $floating ) {
		echo '<div class="sk-float" role="complementary" aria-label="Liên hệ nhanh">' . $floating . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above.
	}
}

/** Render the controlled storefront layout without storing another copy of its editable content in a page. */
function home(): string {
	$output   = hero();
	$sections = (array) get( 'sections' );
	foreach ( [ 'van-de', 'san-pham', 'video', 'kien-thuc', 'dat-hang' ] as $id ) {
		$config = $sections[ $id ] ?? [];
		if ( empty( $config['enabled'] ) ) {
			continue;
		}
		$content = match ( $id ) {
			'van-de'    => finder(),
			'san-pham'  => featured_products(),
			'video'     => \StoreKit\Videos\shortcode( [ 'placement' => 'section', 'limit' => (int) get( 'video_section_limit' ) ] ),
			'kien-thuc' => posts(),
			'dat-hang'  => steps(),
		};
		$output .= section_markup(
			array_merge(
				$config,
				[
					'id'    => $id,
					'align' => in_array( $id, [ 'van-de', 'kien-thuc' ], true ) ? 'start' : 'center',
					'tone'  => 'video' === $id ? 'tile' : 'light',
				]
			),
			$content
		);
	}
	return $output;
}

/** WooCommerce remains responsible for product cards, pricing, stock and purchase controls. */
function featured_products(): string {
	if ( ! function_exists( 'wc_get_product' ) ) {
		return '';
	}
	$ids = [];
	foreach ( (array) get( 'featured_products' ) as $id ) {
		$product = wc_get_product( (int) $id );
		if ( $product && 'publish' === $product->get_status() ) {
			$ids[] = $product->get_id();
		}
	}
	if ( ! $ids ) {
		return '';
	}
	return do_shortcode( '[products ids="' . implode( ',', $ids ) . '" columns="4" orderby="post__in"]' );
}
