<?php
declare(strict_types=1);
/**
 * Admin assets for Core Blueprint SEO settings screens.
 *
 * @package Core_Blueprint_SEO
 */

namespace CB\SEO\Admin;

defined( 'ABSPATH' ) || exit;

final class Assets {
	public static function enqueue( string $hook ): void {
		$native_editor = in_array( $hook, [ 'post.php', 'post-new.php', 'edit-tags.php', 'term.php' ], true );
		$seo_settings  = 'core-blueprint_page_core-blueprint-seo' === $hook;

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

		// PageRegistry owns shared Core Admin presentation. SEO contributes only
		// feature-specific settings composition and behavior.
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
}
