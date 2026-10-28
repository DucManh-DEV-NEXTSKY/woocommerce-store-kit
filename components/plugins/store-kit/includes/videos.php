<?php
/**
 * TikTok how-to videos: on product pages and as a channel list for the home page.
 *
 * Data:
 * - Product meta `_store_kit_videos`: ordered rows with string id, full url and title caption.
 * - Option `store_kit_channel_videos`: ordered channel rows with full URL, caption, tie,
 *   enabled state and independent hero/section placements.
 * - Option `store_kit_video_covers`: [ video id => attachment id ] for poster images
 *   (TikTok renders covers client-side only, so posters are uploaded to the media library).
 *
 * Output: poster + play button; the TikTok player iframe loads only on click.
 */

namespace StoreKit\Videos;

defined( 'ABSPATH' ) || exit;

const META         = '_store_kit_videos';
const COVERS       = 'store_kit_video_covers';
const CHANNEL      = 'store_kit_channel_videos';
const NO_EMBED     = 'store_kit_video_noembed';

add_action( 'init', __NAMESPACE__ . '\\register_meta' );
add_action( 'add_meta_boxes_product', __NAMESPACE__ . '\\add_box' );
add_action( 'save_post_product', __NAMESPACE__ . '\\save_box', 10, 2 );
add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\\register_assets' );
add_action( 'woocommerce_after_single_product_summary', __NAMESPACE__ . '\\render_product_videos', 5 );
add_shortcode( 'store_kit_videos', __NAMESPACE__ . '\\shortcode' );

function register_meta(): void {
	register_post_meta(
		'product',
		META,
		[
			'type'          => 'array',
			'single'        => true,
			'default'       => [],
			'auth_callback' => static fn() => current_user_can( 'edit_products' ),
			'show_in_rest'  => [
				'schema' => [
					'type'  => 'array',
					'items' => [
						'type'       => 'object',
						'properties' => [
							'id'    => [ 'type' => 'string', 'pattern' => '^\d{15,20}$' ],
							'title' => [ 'type' => 'string' ],
							'url'   => [ 'type' => 'string' ],
						],
					],
				],
			],
		]
	);
	register_setting(
		'store_kit',
		COVERS,
		[
			'type'         => 'object',
			'default'      => [],
			'show_in_rest' => [ 'schema' => [ 'type' => 'object', 'additionalProperties' => [ 'type' => 'integer' ] ] ],
		]
	);
}

/**
 * "https://www.tiktok.com/@x/video/123 | Caption" lines -> list of videos. Bare ids are accepted too.
 */
function parse_lines( string $text ): array {
	$videos = [];
	foreach ( preg_split( '/\R/', $text ) as $line ) {
		[ $ref, $title ] = array_pad( array_map( 'trim', explode( '|', $line, 2 ) ), 2, '' );
		if ( preg_match( '~^(?:https?://)?(?:(?:www|m)\.)?tiktok\.com/(?:@[a-z0-9._]+/video|player/v1)/(\d{15,20})(?:[/?#].*)?$~i', $ref, $m ) ) {
			$url = preg_match( '~^https?://~i', $ref ) ? $ref : 'https://' . $ref;
			$videos[ $m[1] ] = [ 'id' => $m[1], 'url' => esc_url_raw( $url ), 'title' => sanitize_text_field( $title ) ];
		} elseif ( preg_match( '~^(\d{15,20})$~', $ref, $m ) ) {
			$videos[ $m[1] ] = [ 'id' => $m[1], 'url' => video_url( $m[1] ), 'title' => sanitize_text_field( $title ) ];
		}
	}
	return array_values( $videos );
}

function get_videos( int $product_id ): array {
	$videos = get_post_meta( $product_id, META, true );
	return is_array( $videos ) ? $videos : [];
}

function video_url( string $id ): string {
	$user = \StoreKit\Settings\tiktok_user();
	return '' !== $user ? 'https://www.tiktok.com/@' . $user . '/video/' . $id : 'https://www.tiktok.com/player/v1/' . $id;
}

function add_box(): void {
	add_meta_box( 'sk-videos', 'Video hướng dẫn (TikTok)', __NAMESPACE__ . '\\render_box', 'product', 'normal', 'default' );
}

function render_box( \WP_Post $post ): void {
	wp_nonce_field( 'store_kit_videos', 'store_kit_videos_nonce' );
	$lines = array_map(
		static fn( $v ) => ( ! empty( $v['url'] ) ? $v['url'] : video_url( (string) $v['id'] ) ) . ( '' !== $v['title'] ? ' | ' . $v['title'] : '' ),
		get_videos( $post->ID )
	);
	printf(
		'<p>Mỗi dòng một video: <code>link TikTok | tiêu đề hiển thị</code>. Video hiện ở trang sản phẩm, phía trên mô tả. Có thể nhập ID video; khi chưa đặt tài khoản TikTok, liên kết dùng trình phát <code>https://www.tiktok.com/player/v1/ID</code>.</p><textarea name="store_kit_videos" rows="4" style="width:100%%" placeholder="%s | Hướng dẫn sử dụng sản phẩm">%s</textarea>',
		esc_attr( 'https://www.tiktok.com/@tenkenh/video/1234567890123456789' ),
		esc_textarea( implode( "\n", $lines ) )
	);
}

function save_box( int $post_id, \WP_Post $post ): void {
	if ( ! isset( $_POST['store_kit_videos_nonce'] ) || ! is_string( $_POST['store_kit_videos_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['store_kit_videos_nonce'] ) ), 'store_kit_videos' ) ) {
		return;
	}
	if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	$input = $_POST['store_kit_videos'] ?? '';
	if ( ! is_string( $input ) ) {
		return;
	}
	$videos = parse_lines( wp_unslash( $input ) );
	$videos ? update_post_meta( $post_id, META, $videos ) : delete_post_meta( $post_id, META );
}

function register_assets(): void {
	$dir = dirname( __DIR__ ) . '/assets/';
	wp_register_style( 'sk-videos', STORE_KIT_URL . 'assets/videos.css', [], (string) filemtime( $dir . 'videos.css' ) );
	wp_register_script( 'sk-videos', STORE_KIT_URL . 'assets/videos.js', [], (string) filemtime( $dir . 'videos.js' ), [ 'strategy' => 'defer', 'in_footer' => true ] );
	$post = get_post();
	if (
		( function_exists( 'is_product' ) && is_product() && get_videos( get_queried_object_id() ) )
		|| ( is_singular() && $post && ( has_shortcode( $post->post_content, 'store_kit_videos' ) || has_shortcode( $post->post_content, 'store_kit_hero' ) || has_shortcode( $post->post_content, 'store_kit_home' ) ) )
	) {
		enqueue();
	}
}

/**
 * Poster (play button only), then title + source below it, then the optional "tie" row
 * (product with price, category with count, or a Zalo prompt). Store Kit video presentation.
 */
function card( array $video, string $tie_html = '', bool $eager = false ): string {
	$covers = (array) get_option( COVERS, [] );
	$title  = '' !== ( $video['title'] ?? '' ) ? $video['title'] : 'Video hướng dẫn';
	$attrs  = [ 'alt' => '', 'class' => 'sk-reel__img' ] + ( $eager ? [ 'fetchpriority' => 'high', 'loading' => 'eager' ] : [ 'loading' => 'lazy' ] );
	$poster = isset( $covers[ $video['id'] ] ) ? wp_get_attachment_image( (int) $covers[ $video['id'] ], 'medium_large', false, $attrs ) : '';
	$source = 'TikTok' . ( ! empty( $video['views'] ) ? ', ' . $video['views'] . ' lượt xem' : '' );
	// Videos observed not to start inside TikTok's embed player open on TikTok directly.
	$inline = ! in_array( (string) $video['id'], (array) get_option( NO_EMBED, [] ), true );
	$badge  = $inline
		? '<span class="sk-reel__play" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M8 5.5v13l10.5-6.5z"/></svg></span>'
		: '<span class="sk-reel__play sk-reel__play--ext" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M8 5.5v13l10.5-6.5z"/></svg></span><span class="sk-reel__ext">Xem trên TikTok</span>';

	return sprintf(
		'<figure class="sk-reel"><a class="sk-reel__media" href="%1$s"%2$s target="_blank" rel="noopener" aria-label="%3$s">%4$s%8$s</a><figcaption class="sk-reel__caption"><span class="sk-reel__title">%6$s</span><span class="sk-reel__source">%5$s</span></figcaption>%7$s</figure>',
		esc_url( ! empty( $video['url'] ) ? $video['url'] : video_url( (string) $video['id'] ) ),
		$inline ? ' data-sk-video="' . esc_attr( $video['id'] ) . '"' : '',
		esc_attr( ( $inline ? 'Phát video: ' : 'Xem trên TikTok: ' ) . $title ),
		$poster ?: '<span class="sk-reel__fallback" aria-hidden="true"></span>',
		esc_html( $source ),
		esc_html( $title ),
		$tie_html,
		$badge
	);
}

function tie_product( int $product_id ): string {
	if ( ! function_exists( 'wc_get_product' ) ) {
		return '';
	}
	$product = wc_get_product( $product_id );
	if ( ! $product || 'publish' !== $product->get_status() ) {
		return '';
	}
	return sprintf(
		'<a class="sk-tie" href="%s">%s<span><b>%s</b><span class="sk-tie__price">%s</span></span></a>',
		esc_url( $product->get_permalink() ),
		$product->get_image( 'thumbnail', [ 'alt' => '', 'loading' => 'lazy' ] ),
		esc_html( $product->get_name() ),
		wp_kses_post( $product->get_price_html() )
	);
}

function tie_category( int $term_id ): string {
	$term = get_term( $term_id, 'product_cat' );
	if ( ! $term instanceof \WP_Term ) {
		return '';
	}
	return sprintf(
		'<a class="sk-tie" href="%s"><span class="sk-tie__icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 4h7v7H4zM13 4h7v7h-7zM4 13h7v7H4zM13 13h7v7h-7z"/></svg></span><span><b>%s</b><span class="sk-tie__note">%d sản phẩm</span></span></a>',
		esc_url( get_term_link( $term ) ),
		esc_html( $term->name ),
		(int) $term->count
	);
}

function tie_zalo(): string {
	if ( '' === (string) \StoreKit\Settings\get( 'zalo_url' ) ) {
		return '';
	}
	return sprintf(
		'<a class="sk-tie sk-tie--zalo" href="%s" target="_blank" rel="noopener"><span class="sk-tie__icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 5h16v11H9l-5 4z"/></svg></span><span><b>Hỏi sản phẩm phù hợp</b><span class="sk-tie__note">Nhắn Zalo cửa hàng</span></span></a>',
		esc_url( \StoreKit\Settings\get( 'zalo_url' ) )
	);
}

/** "product:ID" | "cat:ID" | "zalo" -> label HTML. */
function tie_html( string $tie ): string {
	[ $type, $id ] = array_pad( explode( ':', $tie, 2 ), 2, '0' );
	return match ( $type ) {
		'product' => tie_product( (int) $id ),
		'cat'     => tie_category( (int) $id ),
		'zalo'    => tie_zalo(),
		default   => '',
	};
}

function render_product_videos(): void {
	$videos = get_videos( get_the_ID() );
	if ( ! $videos ) {
		return;
	}
	echo '<section class="sk-videos" aria-labelledby="sk-videos-title"><h2 id="sk-videos-title">Video hướng dẫn</h2><div class="sk-videos__grid">';
	foreach ( $videos as $video ) {
		echo card( $video ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in card().
	}
	echo '</div></section>';
}

/** Enabled channel rows, optionally selected for an independent homepage placement. */
function channel_videos( string $placement = 'all' ): array {
	$videos = get_option( CHANNEL, [] );
	if ( ! is_array( $videos ) ) {
		return [];
	}
	$out = [];
	foreach ( array_values( $videos ) as $index => $video ) {
		if ( ! is_array( $video ) || empty( $video['id'] ) || ( array_key_exists( 'enabled', $video ) && ! $video['enabled'] ) ) {
			continue;
		}
		// Legacy rows remain usable until the explicit settings migration adds flags.
		$video += [ 'hero' => $index < 2, 'section' => $index >= 2 ];
		if ( in_array( $placement, [ 'hero', 'section' ], true ) && ! $video[ $placement ] ) {
			continue;
		}
		$out[] = $video;
	}
	return $out;
}

function enqueue(): void {
	wp_enqueue_style( 'sk-videos' );
	wp_enqueue_script( 'sk-videos' );
}

/**
 * [store_kit_videos limit="8" offset="0" eager="0" placement="all"] — limit="0" shows every enabled row.
 */
function shortcode( $atts ): string {
	$atts   = shortcode_atts( [ 'limit' => 8, 'offset' => 0, 'eager' => 0, 'class' => '', 'placement' => 'all' ], $atts, 'store_kit_videos' );
	$limit  = max( 0, (int) $atts['limit'] );
	$videos = array_slice( channel_videos( (string) $atts['placement'] ), max( 0, (int) $atts['offset'] ), $limit ?: null );
	if ( ! $videos ) {
		return '';
	}
	enqueue();
	$cards = array_map( static fn( $v ) => card( $v, tie_html( (string) ( $v['tie'] ?? '' ) ), (bool) $atts['eager'] ), $videos );
	return '<div class="sk-reels ' . esc_attr( $atts['class'] ) . '">' . implode( '', $cards ) . '</div>';
}
