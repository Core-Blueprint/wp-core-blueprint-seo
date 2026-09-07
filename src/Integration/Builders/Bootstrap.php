<?php
declare(strict_types=1);
/**
 * Optional builder-adapter bootstrap. Core SEO never depends on a builder.
 *
 * @package Core_Blueprint_SEO
 */

namespace CB\SEO\Integration\Builders;

defined( 'ABSPATH' ) || exit;

final class Bootstrap {
	private static bool $booted = false;

	public static function init(): void {
		if ( self::$booted ) {
			return;
		}
		self::$booted = true;

		add_action( 'init', [ \CB\SEO\Integration\Builders\Bricks\ElementRegistry::class, 'register' ], 11 );
	}
}
