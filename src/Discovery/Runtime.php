<?php
declare(strict_types=1);
/**
 * Public llms.txt endpoint and discovery link.
 *
 * @package Core_Blueprint_SEO
 */

namespace CB\SEO\Discovery;

use CB\SEO\State;

defined( 'ABSPATH' ) || exit;

final class Runtime {
	private const QUERY_VAR = 'cb_seo_llms';
	private const REWRITE_VERSION = '1';

	public static function boot(): void {
		// Route ownership remains active while the extension is loaded so a stale
		// rewrite can never fall through to an unrelated WordPress document.
		add_action( 'init', [ self::class, 'register_rewrite' ], 20 );
		add_filter( 'query_vars', [ self::class, 'query_vars' ] );
		add_action( 'template_redirect', [ self::class, 'serve' ], 0 );
		add_action( 'wp_head', [ self::class, 'render_discovery_link' ], 2 );
	}

	public static function register_rewrite(): void {
		add_rewrite_rule( '^llms\\.txt$', 'index.php?' . self::QUERY_VAR . '=1', 'top' );
		if ( self::REWRITE_VERSION !== (string) get_option( 'cb_seo_llms_rewrite_version', '' ) ) {
			flush_rewrite_rules( false );
			update_option( 'cb_seo_llms_rewrite_version', self::REWRITE_VERSION, false );
		}
	}

	/** @param string[] $vars
	 *  @return string[]
	 */
	public static function query_vars( array $vars ): array {
		$vars[] = self::QUERY_VAR;
		return $vars;
	}

	public static function serve(): void {
		if ( '1' !== (string) get_query_var( self::QUERY_VAR ) ) {
			return;
		}

		if ( ! State::is_enabled() || ! SettingsRepository::all()['enabled'] ) {
			status_header( 404 );
			nocache_headers();
			exit;
		}

		status_header( 200 );
		header( 'Content-Type: text/plain; charset=' . get_option( 'blog_charset', 'UTF-8' ) );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Cache-Control: public, max-age=300' );
		echo Document::render(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- plain-text document is sanitized by the generator.
		exit;
	}

	public static function render_discovery_link(): void {
		if ( ! State::is_enabled() || ! SettingsRepository::all()['enabled'] || is_admin() || is_feed() ) {
			return;
		}
		echo '<link rel="describedby" href="' . esc_url( home_url( '/llms.txt' ) ) . '">' . "\n";
	}
}
