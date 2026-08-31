<?php
declare(strict_types=1);
/**
 * Canonical module master state for Core Blueprint SEO.
 *
 * @package Core_Blueprint_SEO
 */

namespace CB\SEO;

use CB\Core\Log\AuditLog;

defined( 'ABSPATH' ) || exit;

final class State {

	public static function is_enabled(): bool {
		return '1' === (string) get_option( CB_SEO_ENABLED_OPT, '1' );
	}

	public static function set_enabled( bool $enabled, string $actor = 'unknown' ): void {
		if ( self::is_enabled() === $enabled ) {
			return;
		}

		$value = $enabled ? '1' : '0';
		if ( false === get_option( CB_SEO_ENABLED_OPT, false ) ) {
			add_option( CB_SEO_ENABLED_OPT, $value, '', 'yes' );
		} else {
			update_option( CB_SEO_ENABLED_OPT, $value );
		}

		if ( class_exists( AuditLog::class ) ) {
			AuditLog::log(
				$enabled ? 'seo_subsystem_enabled' : 'seo_subsystem_disabled',
				'notice',
				[
					'actor'   => $actor,
					'version' => CB_SEO_VERSION,
				]
			);
		}
	}
}
