<?php
declare(strict_types=1);
/**
 * Admin assets for Core Blueprint SEO settings screens.
 *
 * @package Core_Blueprint_SEO
 */

namespace CB\SEO\Admin;

use CB\Core\Admin\SettingsRegistry;

defined( 'ABSPATH' ) || exit;

final class Assets {
	public static function enqueue( string $hook ): void {
		$native_editor = in_array( $hook, [ 'post.php', 'post-new.php', 'edit-tags.php', 'term.php' ], true );
		$seo_settings  = self::is_settings_screen();

		if ( $native_editor ) {
			wp_enqueue_style(
				'core-blueprint-seo-editor',
				CB_SEO_URL . 'assets/css/seo-editor.css',
				[],
				CB_SEO_VERSION
			);
		}

		if ( in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) {
			wp_enqueue_script(
				'core-blueprint-seo-analysis',
				CB_SEO_URL . 'assets/js/seo-analysis.js',
				[],
				CB_SEO_VERSION,
				true
			);
		}

		if ( $native_editor || $seo_settings ) {
			wp_enqueue_media();
			wp_enqueue_script(
				'core-blueprint-seo-media',
				CB_SEO_URL . 'assets/js/seo-media.js',
				[ 'media-editor' ],
				CB_SEO_VERSION,
				true
			);
		}

		if ( ! $seo_settings ) {
			return;
		}

		// Base owns shared Core Admin presentation through SettingsRegistry.
		// SEO contributes only feature-specific settings composition and behavior.
		wp_enqueue_style(
			'core-blueprint-seo-admin',
			CB_SEO_URL . 'assets/css/seo-admin.css',
			[],
			CB_SEO_VERSION
		);

		wp_enqueue_script(
			'core-blueprint-seo-admin',
			CB_SEO_URL . 'assets/js/seo-admin.js',
			[],
			CB_SEO_VERSION,
			true
		);
	}

	private static function is_settings_screen(): bool {
		$canonical_url = SettingsRegistry::url( 'core-blueprint-seo' );
		$query         = wp_parse_url( $canonical_url, PHP_URL_QUERY );
		$args          = [];

		if ( is_string( $query ) && '' !== $query ) {
			parse_str( $query, $args );
		}

		$settings_page = isset( $args['page'] ) ? sanitize_key( (string) $args['page'] ) : '';
		$current_page  = isset( $_GET['page'] ) ? sanitize_key( (string) wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- admin routing only.
		$extension_id  = isset( $_GET['extension'] ) ? sanitize_key( (string) wp_unslash( $_GET['extension'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- admin routing only.

		return '' !== $settings_page
			&& $settings_page === $current_page
			&& 'core-blueprint-seo' === $extension_id;
	}
}
