<?php
/** Neutral storefront defaults; merchant identity and resource selections are intentionally empty. */
defined( 'ABSPATH' ) || exit;

return [
	'brand' => 'Cửa hàng',
	'legal_name' => '', 'tax_id' => '', 'address' => '', 'returns_address' => '',
	'email' => '', 'hours' => '', 'phone' => '', 'phone2' => '',
	'zalo_url' => '', 'facebook_url' => '', 'tiktok_url' => '', 'youtube_url' => '',
	'logo_id' => 0, 'footer_logo_id' => 0,
	'footer_description' => 'Khám phá sản phẩm và thông tin hữu ích từ cửa hàng.',
	'hero_title' => 'Tìm sản phẩm phù hợp với bạn.',
	'hero_lede' => 'Khám phá bộ sưu tập, xem hướng dẫn và lựa chọn sản phẩm theo nhu cầu.',
	'hero_badge' => 'Sản phẩm & hướng dẫn',
	'announce_before' => '', 'announce_after' => '',
	'bct_url' => '', 'bct_confirmed' => false,
	'video_section_limit' => 8, 'posts_count' => 5,
	'featured_products' => [], 'problems' => [], 'steps' => [],
	'design' => [],
	'sections' => [
		'van-de' => [ 'enabled' => true, 'title' => 'Bạn đang tìm gì?', 'lede' => '', 'eyebrow' => 'Khám phá danh mục', 'link_text' => '', 'link_url' => '' ],
		'san-pham' => [ 'enabled' => true, 'title' => 'Sản phẩm nổi bật', 'lede' => 'Khám phá các lựa chọn của cửa hàng.', 'eyebrow' => '', 'link_text' => '', 'link_url' => '' ],
		'video' => [ 'enabled' => true, 'title' => 'Xem hướng dẫn sản phẩm', 'lede' => 'Video ngắn giúp bạn tìm hiểu trước khi chọn.', 'eyebrow' => '', 'link_text' => '', 'link_url' => '' ],
		'kien-thuc' => [ 'enabled' => true, 'title' => 'Thông tin hữu ích', 'lede' => '', 'eyebrow' => 'Góc chia sẻ', 'link_text' => '', 'link_url' => '' ],
		'dat-hang' => [ 'enabled' => true, 'title' => 'Cách đặt hàng', 'lede' => '', 'eyebrow' => '', 'link_text' => '', 'link_url' => '' ],
	],
	'policy_pages' => [ 'privacy' => 0, 'shipping' => 0, 'returns' => 0, 'payment' => 0, 'warranty' => 0, 'terms' => 0, 'rules' => 0, 'complaints' => 0 ],
];
