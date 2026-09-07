<?php
declare(strict_types=1);

namespace {
	defined( 'ABSPATH' ) || define( 'ABSPATH', __DIR__ . '/' );
	defined( 'CB_SEO_URL' ) || define( 'CB_SEO_URL', 'https://example.test/wp-content/plugins/core-blueprint-seo/' );
	defined( 'CB_SEO_VERSION' ) || define( 'CB_SEO_VERSION', '1.0.0-rc1' );

	$GLOBALS['cb_test_styles']  = [];
	$GLOBALS['cb_test_scripts'] = [];
	$GLOBALS['cb_test_media']   = 0;

	function wp_parse_url( string $url, int $component = -1 ): mixed { return parse_url( $url, $component ); }
	function sanitize_key( string $value ): string { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $value ) ) ?? ''; }
	function wp_unslash( mixed $value ): mixed { return $value; }
	function wp_enqueue_style( string $handle, string $src, array $deps = [], string|bool|null $version = false ): void { $GLOBALS['cb_test_styles'][ $handle ] = $src; }
	function wp_enqueue_script( string $handle, string $src, array $deps = [], string|bool|null $version = false, bool $footer = false ): void { $GLOBALS['cb_test_scripts'][ $handle ] = $src; }
	function wp_enqueue_media(): void { ++$GLOBALS['cb_test_media']; }
}

namespace CB\Core\Admin {
	final class SettingsRegistry {
		public static function url( string $extension_id ): string {
			return 'https://example.test/wp-admin/admin.php?page=core-blueprint-settings&extension=' . $extension_id;
		}
	}
}

namespace CB\Core\UI {
	final class AdminTheme {
		/** @var string[] */
		public static array $registered = [];
		public static function register_screen( string $hook_suffix ): void { self::$registered[] = $hook_suffix; }
	}
}

namespace {
	require_once dirname( __DIR__ ) . '/src/Admin/Assets.php';

	function cb_assert( bool $condition, string $message ): void {
		if ( ! $condition ) {
			fwrite( STDERR, "FAIL: {$message}\n" );
			exit( 1 );
		}
	}

	$_GET = [ 'page' => 'core-blueprint-settings', 'extension' => 'core-blueprint-seo' ];
	\CB\SEO\Admin\Assets::enqueue( 'core-blueprint_page_core-blueprint-settings' );
	cb_assert(
		[ 'core-blueprint_page_core-blueprint-settings' ] === \CB\Core\UI\AdminTheme::$registered,
		'SEO Settings Hub did not declare Admin Theme compatibility.'
	);
	cb_assert( isset( $GLOBALS['cb_test_styles']['core-blueprint-seo-admin'] ), 'SEO admin stylesheet was not enqueued on its settings provider.' );
	cb_assert( isset( $GLOBALS['cb_test_scripts']['core-blueprint-seo-admin'] ), 'SEO admin script was not enqueued on its settings provider.' );

	$_GET = [];
	\CB\SEO\Admin\Assets::enqueue( 'post.php' );
	cb_assert(
		1 === count( \CB\Core\UI\AdminTheme::$registered ),
		'Native WordPress editor must not be registered as an SEO-owned Admin Theme screen.'
	);
	cb_assert( isset( $GLOBALS['cb_test_styles']['core-blueprint-seo-editor'] ), 'Native editor composition stylesheet was not enqueued.' );

	$admin_css  = (string) file_get_contents( dirname( __DIR__ ) . '/assets/css/seo-admin.css' );
	$editor_css = (string) file_get_contents( dirname( __DIR__ ) . '/assets/css/seo-editor.css' );
	$css        = strtolower( $admin_css . "\n" . $editor_css );

	foreach ( [ '#fff', '#ffffff', '#f6f7f7', '#f0f0f1', '#dcdcde', '#c3c4c7', '#8c8f94', '#646970', '#50575e', '#3c434a', '#1d2327' ] as $color ) {
		cb_assert( ! str_contains( $css, $color ), 'Light-only admin presentation color remains: ' . $color );
	}

	foreach ( [ 'body.wp-admin', '#wpadminbar', '#adminmenu', '.wp-list-table' ] as $selector ) {
		cb_assert( ! str_contains( $css, $selector ), 'SEO admin CSS must not skin global WordPress UI: ' . $selector );
	}

	cb_assert( str_contains( $editor_css, 'var(--cb-border)' ), 'Native editor grouping does not consume the Base semantic border token.' );

	echo "PASS: SEO Admin Theme compatibility regression\n";
}
