<?php
/** Shared storefront identity, footer and about-page contact section. */

namespace StoreKit\SiteContent;

use function StoreKit\Settings\get;
use function StoreKit\Settings\phone;

defined( 'ABSPATH' ) || exit;

add_shortcode( 'store_kit_footer', __NAMESPACE__ . '\\footer' );
add_shortcode( 'store_kit_contact', __NAMESPACE__ . '\\contact' );
add_filter( 'wpseo_schema_organization', __NAMESPACE__ . '\\organization' );

/** Every selected policy must still be a published page when rendered. */
function policy_links(): string {
	$links = '';
	foreach ( (array) get( 'policy_pages' ) as $id ) {
		if ( (int) $id <= 0 ) {
			continue;
		}
		$page = get_post( (int) $id );
		if ( ! public_policy( (int) $id ) ) {
			continue;
		}
		$links .= sprintf( '<li><a href="%s">%s</a></li>', esc_url( get_permalink( $page ) ), esc_html( get_the_title( $page ) ) );
	}
	return $links;
}

/** Keep all four live social channels, using the same existing icon treatment. */
function social_links(): string {
	$channels = [
		'youtube_url'  => [ 'YouTube', 'M21.6 7.2a3 3 0 0 0-2.1-2.1C17.6 4.6 12 4.6 12 4.6s-5.6 0-7.5.5a3 3 0 0 0-2.1 2.1C2 9.1 2 12 2 12s0 2.9.4 4.8a3 3 0 0 0 2.1 2.1c1.9.5 7.5.5 7.5.5s5.6 0 7.5-.5a3 3 0 0 0 2.1-2.1C22 14.9 22 12 22 12s0-2.9-.4-4.8ZM10 15.2V8.8l5.5 3.2z' ],
		'tiktok_url'   => [ 'TikTok', 'M16.6 3c.4 2.3 1.9 3.8 4.2 4v3.1a7.4 7.4 0 0 1-4.2-1.3v6.4a6 6 0 1 1-6-6c.2 0 .4 0 .6.03v3.2a2.8 2.8 0 1 0 2.2 2.77V3z' ],
		'facebook_url' => [ 'Facebook', 'M13.5 21v-7.5H16l.4-3h-2.9V8.6c0-.9.3-1.5 1.5-1.5h1.5V4.4c-.3 0-1.2-.1-2.2-.1-2.2 0-3.7 1.3-3.7 3.8v2.4H8v3h2.6V21z' ],
		'zalo_url'     => [ 'Zalo', 'M12 3C6.5 3 2 6.8 2 11.5c0 2.6 1.4 4.9 3.6 6.5L5 21l3.7-1.8c1 .3 2.2.5 3.3.5 5.5 0 10-3.8 10-8.5S17.5 3 12 3z' ],
	];
	$links = '';
	foreach ( $channels as $key => [ $label, $path ] ) {
		$url = (string) get( $key );
		if ( '' !== $url ) {
			$links .= sprintf( '<a href="%s" target="_blank" rel="noopener"><svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="%s"/></svg>%s</a>', esc_url( $url ), esc_attr( $path ), esc_html( $label ) );
		}
	}
	return $links ? '<div class="sk-footer__social">' . $links . '</div>' : '';
}

/** Escape plain multiline contact fields without accepting editable HTML. */
function text( string $key ): string {
	return nl2br( esc_html( (string) get( $key ) ) );
}

function legal_details(): string {
	$items = '';
	if ( '' !== (string) get( 'legal_name' ) ) {
		$items .= '<li>' . esc_html( get( 'legal_name' ) ) . '</li>';
	}
	if ( '' !== (string) get( 'tax_id' ) ) {
		$items .= '<li>Mã số thuế: ' . esc_html( get( 'tax_id' ) ) . '</li>';
	}
	if ( '' !== (string) get( 'returns_address' ) ) {
		$items .= '<li>Địa chỉ nhận đổi trả: ' . text( 'returns_address' ) . '</li>';
	}
	return $items;
}

/** Never imply notification/approval from an unchecked or nonofficial URL. */
function bct_link(): string {
	$url   = (string) get( 'bct_url' );
	$parts = wp_parse_url( $url );
	if ( ! get( 'bct_confirmed' ) || ! is_array( $parts ) || 'https' !== ( $parts['scheme'] ?? '' ) || ! in_array( strtolower( $parts['host'] ?? '' ), [ 'online.gov.vn', 'www.online.gov.vn' ], true ) || ! preg_match( '~^/(?:Home/WebDetails/\d+|nen-tang/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})/?$~i', $parts['path'] ?? '' ) || isset( $parts['user'] ) || isset( $parts['pass'] ) || isset( $parts['port'] ) ) {
		return '';
	}
	return '<li><a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">Tra cứu thông tin website tại Bộ Công Thương</a></li>';
}

function footer(): string {
	$contact = '';
	foreach ( [ 'address', 'hours' ] as $key ) {
		if ( '' !== (string) get( $key ) ) {
			$contact .= '<li>' . text( $key ) . '</li>';
		}
	}
	foreach ( [ 'phone' => false, 'phone2' => true ] as $key => $secondary ) {
		if ( '' !== (string) get( $key ) ) {
			$contact .= sprintf( '<li><a href="tel:%s">%s</a></li>', esc_attr( phone( $secondary ) ), esc_html( get( $key ) ) );
		}
	}
	if ( '' !== (string) get( 'zalo_url' ) ) {
		$contact .= '<li><a href="' . esc_url( get( 'zalo_url' ) ) . '" target="_blank" rel="noopener">Nhắn Zalo</a></li>';
	}
	if ( '' !== (string) get( 'email' ) ) {
		$contact .= '<li><a href="' . esc_url( 'mailto:' . get( 'email' ) ) . '">' . esc_html( get( 'email' ) ) . '</a></li>';
	}
	$logo_id = (int) get( 'footer_logo_id' );
	$brand = (string) get( 'brand' ) ?: get_bloginfo( 'name' );
	$logo = $logo_id ? wp_get_attachment_image( $logo_id, 'medium', false, [ 'alt' => $brand, 'class' => 'sk-footer__logo' ] ) : '';
	if ( ! $logo ) {
		$logo = '<span class="sk-footer__brand">' . esc_html( $brand ) . '</span>';
	}
	$notice = (string) apply_filters( 'store_kit_footer_notice', '' );
	return sprintf(
		'<div class="sk-footer alignfull"><div class="sk-wrap sk-footer__grid"><div>%1$s<p>%3$s</p>%4$s%10$s</div><div><h2>Hỗ trợ khách hàng</h2><ul>%5$s</ul></div><div><h2>Liên hệ</h2><ul>%6$s</ul></div><div><h2>Thông tin pháp lý</h2><ul>%7$s</ul></div></div><div class="sk-wrap sk-footer__bottom">© %8$s %2$s%9$s</div></div>',
		$logo,
		esc_html( $brand ),
		text( 'footer_description' ),
		social_links(),
		policy_links(),
		$contact,
		legal_details() . bct_link(),
		esc_html( wp_date( 'Y' ) ),
		'' !== (string) get( 'legal_name' ) ? ' – ' . esc_html( get( 'legal_name' ) ) : '',
		$notice ? '<p class="sk-footer__notice">' . wp_kses_post( $notice ) . '</p>' : ''
	);
}

/** Replace only the existing contact section on the about page. */
function contact(): string {
	$details = [];
	foreach ( [ 'address', 'hours' ] as $key ) {
		if ( '' !== (string) get( $key ) ) {
			$details[] = text( $key );
		}
	}
	$buttons = '';
	if ( '' !== (string) get( 'zalo_url' ) ) {
		$buttons .= '<a class="sk-btn sk-btn--primary" href="' . esc_url( get( 'zalo_url' ) ) . '" target="_blank" rel="noopener">Nhắn Zalo</a>';
	}
	if ( '' !== (string) get( 'phone' ) ) {
		$buttons .= sprintf( '<a class="sk-btn sk-btn--ghost" href="tel:%s">Gọi %s</a>', esc_attr( phone() ), esc_html( get( 'phone' ) ) );
	}
	$extra = '';
	if ( '' !== (string) get( 'phone2' ) ) {
		$extra .= sprintf( '<p>Điện thoại khác: <a href="tel:%s">%s</a></p>', esc_attr( phone( true ) ), esc_html( get( 'phone2' ) ) );
	}
	if ( '' !== (string) get( 'email' ) ) {
		$extra .= '<p>Email: <a href="' . esc_url( 'mailto:' . get( 'email' ) ) . '">' . esc_html( get( 'email' ) ) . '</a></p>';
	}
	$legal = legal_details();
	return '<section class="sk-about-contact" id="lien-he" aria-labelledby="sk-about-contact-title"><div><h2 id="sk-about-contact-title">Liên hệ cửa hàng</h2><p>Liên hệ cửa hàng để được hỗ trợ về sản phẩm và đơn hàng.</p><p>' . implode( '<br>', $details ) . '</p>' . ( $legal ? '<ul>' . $legal . '</ul>' : '' ) . '</div><div><div class="sk-about__links">' . $buttons . '</div>' . $extra . '</div></section>';
}

/** Keep Yoast's node identity and logo, but use the same business facts as visible content. */
function organization( array $data ): array {
	$data['@type']      = [ 'Organization', 'Store' ];
	$data['name']       = (string) get( 'brand' );
	foreach ( [ 'legalName' => 'legal_name', 'taxID' => 'tax_id', 'email' => 'email' ] as $property => $key ) {
		$value = (string) get( $key );
		if ( '' !== $value ) {
			$data[ $property ] = $value;
		} else {
			unset( $data[ $property ] );
		}
	}
	$number = phone();
	if ( '' !== $number ) {
		$data['telephone'] = $number;
	} else {
		unset( $data['telephone'] );
	}
	if ( '' !== (string) get( 'address' ) ) {
		$data['address'] = [
			'@type'          => 'PostalAddress',
			'streetAddress'  => (string) get( 'address' ),
		];
	} else {
		unset( $data['address'] );
	}
	$social = [];
	foreach ( [ 'facebook_url', 'tiktok_url', 'youtube_url', 'zalo_url' ] as $key ) {
		$url = (string) get( $key );
		if ( '' !== $url ) {
			$social[] = $url;
		}
	}
	$data['sameAs'] = $social;
	// Display hours are editable prose, not a trustworthy structured timetable.
	unset( $data['openingHours'], $data['openingHoursSpecification'] );
	return $data;
}

/** A selected policy remains safe even if it is made private or password-protected later. */
function public_policy( int $id ): bool {
	$page = get_post( $id );
	return $page instanceof \WP_Post && 'page' === $page->post_type && 'publish' === $page->post_status
		&& is_post_publicly_viewable( $page ) && '' === $page->post_password
		&& '' !== trim( wp_strip_all_tags( strip_shortcodes( $page->post_content ) ) );
}
