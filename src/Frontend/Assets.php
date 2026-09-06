<?php
declare(strict_types=1);
/**
 * Frontend assets for builder-neutral SEO components.
 *
 * @package Core_Blueprint_SEO
 */

namespace CB\SEO\Frontend;

defined( 'ABSPATH' ) || exit;

final class Assets {
	private static bool $enqueued = false;

	public static function enqueue(): void {
		if ( self::$enqueued ) {
			return;
		}

		self::$enqueued = true;
		wp_enqueue_style(
			'core-blueprint-seo-frontend',
			CB_SEO_URL . 'assets/css/seo-frontend.css',
			[],
			CB_SEO_VERSION
		);
	}
}
