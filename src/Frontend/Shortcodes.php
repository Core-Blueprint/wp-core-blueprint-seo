<?php
declare(strict_types=1);
/**
 * Public shortcode surfaces for SEO frontend components.
 *
 * @package Core_Blueprint_SEO
 */

namespace CB\SEO\Frontend;

use CB\SEO\State;

defined( 'ABSPATH' ) || exit;

final class Shortcodes {
	private static bool $booted = false;

	public static function boot(): void {
		if ( self::$booted ) {
			return;
		}
		self::$booted = true;
		Assets::boot();
		add_shortcode( 'cb_seo_sitemap', [ self::class, 'sitemap' ] );
	}

	/** @param array<string,mixed>|string $attributes */
	public static function sitemap( array|string $attributes = [] ): string {
		if ( ! State::is_enabled() ) {
			return '';
		}

		$attributes = is_array( $attributes ) ? $attributes : [];
		$attributes = shortcode_atts(
			[
				'post_types'    => '',
				'taxonomies'    => '',
				'show_headings' => 'true',
				'hierarchy'     => 'true',
				'orderby'       => 'title',
				'order'         => 'asc',
				'columns'       => '2',
			],
			$attributes,
			'cb_seo_sitemap'
		);

		return Sitemap::render( $attributes );
	}
}
