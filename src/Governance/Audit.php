<?php
declare(strict_types=1);
/**
 * SEO-specific audit helpers.
 *
 * @package Core_Blueprint_SEO
 */

namespace CB\SEO\Governance;

use CB\Core\Governance\Audit as CoreAudit;

defined( 'ABSPATH' ) || exit;

final class Audit {
	/** @param array<string,mixed> $context */
	public static function log( string $event, array $context = [] ): void {
		$context['actor']   = 'user:' . get_current_user_id();
		$context['version'] = CB_SEO_VERSION;
		CoreAudit::record( $event, 'notice', $context );
	}
}
