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
			self::fail_activation( implode( ' ', $errors ) );
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

	private static function fail_activation( string $message ): void {
		if ( ! function_exists( 'deactivate_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		deactivate_plugins( CB_SEO_BASENAME );
		wp_die(
			esc_html( $message ),
			esc_html( 'Core Blueprint dependency required' ),
			[
				'link_url'  => admin_url( 'plugins.php' ),
				'link_text' => __( 'Plugins' ),
			]
		);
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
