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
	public const CATEGORY = 'core-blueprint-seo';

	public static function register(): void {
		if ( ! class_exists( '\\Bricks\\Elements' ) || ! class_exists( '\\Bricks\\Element' ) ) {
			return;
		}

		add_filter( 'bricks/builder/i18n', [ self::class, 'builder_i18n' ] );

		$file = __DIR__ . '/Elements/Sitemap.php';
		if ( is_readable( $file ) ) {
			\Bricks\Elements::register_element( $file, 'cb-seo-sitemap', Elements\Sitemap::class );
		}
	}

	/** @param array<string,string> $i18n @return array<string,string> */
	public static function builder_i18n( array $i18n ): array {
		$i18n[ self::CATEGORY ] = esc_html__( 'Core Blueprint SEO', 'core-blueprint-seo' );
		return $i18n;
	}
}
