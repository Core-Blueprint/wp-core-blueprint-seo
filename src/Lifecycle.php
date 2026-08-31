<?php
declare(strict_types=1);
/**
 * Plugin activation/deactivation lifecycle.
 *
 * @package Core_Blueprint_SEO
 */

namespace CB\SEO;

use CB\Core\Log\AuditLog;

defined( 'ABSPATH' ) || exit;

final class Lifecycle {

	public static function maybe_upgrade(): void {
		$installed = (string) get_option( 'cb_seo_installed_version', '' );
		if ( CB_SEO_VERSION === $installed ) {
			return;
		}

		add_option( CB_SEO_METADATA_SETTINGS_OPT, [], '', 'no' );
		add_option( CB_SEO_SOCIAL_SETTINGS_OPT, [], '', 'no' );
		add_option( CB_SEO_SCHEMA_SETTINGS_OPT, [], '', 'no' );
		add_option( CB_SEO_DISCOVERY_SETTINGS_OPT, [ 'enabled' => false, 'post_types' => [ 'page', 'post' ], 'include_descriptions' => true, 'max_items' => 250 ], '', 'no' );
		add_option( CB_SEO_INDEXING_SETTINGS_OPT, [], '', 'no' );
		update_option( 'cb_seo_installed_version', CB_SEO_VERSION, false );
	}

	public static function activate(): void {
		$errors = Requirements::unmet();

		if ( ! empty( $errors ) ) {
			deactivate_plugins( plugin_basename( CB_SEO_FILE ) );
			wp_die(
				'<strong>Core Blueprint SEO could not be activated:</strong><ul><li>'
				. implode( '</li><li>', array_map( 'esc_html', $errors ) )
				. '</li></ul>',
				'Core Blueprint SEO - Activation Error',
				[ 'back_link' => true ]
			);
		}

		// Fresh installs default to enabled. Reactivation preserves an explicit
		// operator choice because add_option() is intentionally idempotent.
		add_option( CB_SEO_ENABLED_OPT, '1', '', 'yes' );
		add_option( 'cb_seo_installed_version', CB_SEO_VERSION, '', 'no' );
		add_option( CB_SEO_METADATA_SETTINGS_OPT, [], '', 'no' );
		add_option( CB_SEO_SOCIAL_SETTINGS_OPT, [], '', 'no' );
		add_option( CB_SEO_SCHEMA_SETTINGS_OPT, [], '', 'no' );
		add_option( CB_SEO_DISCOVERY_SETTINGS_OPT, [ 'enabled' => false, 'post_types' => [ 'page', 'post' ], 'include_descriptions' => true, 'max_items' => 250 ], '', 'no' );
		add_option( CB_SEO_INDEXING_SETTINGS_OPT, [], '', 'no' );
		update_option( 'cb_seo_installed_version', CB_SEO_VERSION, false );

		self::audit( 'seo_extension_activated' );
	}

	public static function deactivate(): void {
		// Configuration and the module-state option are deliberately preserved.
		// Deactivation removes the runtime by virtue of WordPress unloading the
		// plugin; deletion is handled by uninstall.php.
		self::audit( 'seo_extension_deactivated' );
	}

	private static function audit( string $event ): void {
		if ( ! class_exists( AuditLog::class ) ) {
			return;
		}

		AuditLog::log(
			$event,
			'notice',
			[
				'actor'   => 'user:' . get_current_user_id(),
				'version' => CB_SEO_VERSION,
			]
		);
	}
}
