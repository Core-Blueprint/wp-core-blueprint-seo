<?php
declare(strict_types=1);
/**
 * Registers optional Bricks elements without making Bricks an SEO dependency.
 *
 * @package Core_Blueprint_SEO
 */

namespace CB\SEO\Integration\Builders\Bricks;

defined( 'ABSPATH' ) || exit;

final class ElementRegistry {
	public static function register(): void {
		if ( ! class_exists( '\\Bricks\\Elements' ) || ! class_exists( '\\Bricks\\Element' ) ) {
			return;
		}

		$file = __DIR__ . '/Elements/Sitemap.php';
		if ( is_readable( $file ) ) {
			\Bricks\Elements::register_element( $file, 'cb-seo-sitemap', Elements\Sitemap::class );
		}
	}
}
