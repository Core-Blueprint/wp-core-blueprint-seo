<?php
declare(strict_types=1);
/**
 * Frontend assets for builder-neutral SEO components.
 *
 * @package Core_Blueprint_SEO
 */

namespace CB\SEO\Frontend;

use CB\SEO\State;

defined( 'ABSPATH' ) || exit;

final class Assets {
	private static bool $booted   = false;
	private static bool $enqueued = false;

	public static function boot(): void {
		if ( self::$booted ) {
			return;
		}
		self::$booted = true;
		add_action( 'wp_enqueue_scripts', [ self::class, 'enqueue' ] );
	}

	public static function enqueue(): void {
		if ( self::$enqueued || ! State::is_enabled() ) {
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
