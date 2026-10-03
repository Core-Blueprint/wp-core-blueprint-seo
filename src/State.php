<?php
declare(strict_types=1);
/**
 * Canonical module master state for Core Blueprint SEO.
 *
 * @package Core_Blueprint_SEO
 */

namespace CB\SEO;

use CB\SEO\Governance\Audit as GovernanceAudit;
use CoreBlueprint\Core\Modules\ModuleStateInterface;

defined( 'ABSPATH' ) || exit;

final class State implements ModuleStateInterface {

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

		GovernanceAudit::log(
			$enabled ? 'seo.subsystem.enabled' : 'seo.subsystem.disabled',
			[ 'actor' => $actor ]
		);
	}
}
