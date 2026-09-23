<?php
declare(strict_types=1);

/**
 * Central frontend-output gate for Core Blueprint SEO.
 *
 * @package Core_Blueprint_SEO
 */

namespace CB\SEO\Compatibility;

use CB\SEO\State;

defined( 'ABSPATH' ) || exit;

final class RuntimeGate {
	public static function frontend_allowed(): bool {
		return State::is_enabled() && [] === SeoPluginConflictDetector::active_conflicts();
	}
}
