<?php
declare(strict_types=1);

namespace {
	defined( 'ABSPATH' ) || define( 'ABSPATH', __DIR__ . '/' );
	defined( 'CB_SEO_BASENAME' ) || define( 'CB_SEO_BASENAME', 'core-blueprint-seo/core-blueprint-seo.php' );

	function admin_url( string $path = '' ): string {
		return 'https://example.test/wp-admin/' . ltrim( $path, '/' );
	}

	function __( string $text, string $domain = 'default' ): string {
		return $text;
	}
}

namespace CB\Core {
	final class ExtensionRegistry {
		/** @var array<string,mixed>|null */
		public static ?array $registered = null;

		/** @param array<string,mixed> $definition */
		public static function register( array $definition ): bool {
			self::$registered = $definition;
			return true;
		}
	}
}

namespace CB\SEO\Compatibility {
	final class SeoPluginConflictDetector {
		/** @var array<string,string> */
		public static array $conflicts = [];

		/** @return array<string,string> */
		public static function active_conflicts(): array {
			return self::$conflicts;
		}
	}
}

namespace CB\SEO {
	use CB\Core\ExtensionRegistry;
	use CB\SEO\Compatibility\SeoPluginConflictDetector;

	final class State {
		public static bool $enabled = true;

		public static function is_enabled(): bool {
			return self::$enabled;
		}
	}

	require_once __DIR__ . '/../src/Bootstrap.php';

	function cb_seo_health_expect( bool $condition, string $message ): void {
		if ( $condition ) {
			return;
		}
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}

	Bootstrap::register_extension();
	$definition = ExtensionRegistry::$registered;
	cb_seo_health_expect( is_array( $definition ), 'SEO must register with ExtensionRegistry.' );
	cb_seo_health_expect( 'seo' === ( $definition['status_id'] ?? '' ), 'SEO extension registration must reference status_id seo.' );

	$status_definitions = Bootstrap::register_status_definition( [] );
	cb_seo_health_expect( isset( $status_definitions['seo'] ), 'SEO must register a matching status definition.' );
	cb_seo_health_expect( [ Bootstrap::class, 'extension_status' ] === $status_definitions['seo']['provider'], 'SEO status definition must use the canonical provider.' );

	State::$enabled = false;
	$status = Bootstrap::extension_status();
	cb_seo_health_expect( 'off' === $status['state'] && 'SEO disabled' === $status['detail'], 'Disabled SEO must report an intentional off state.' );

	State::$enabled = true;
	SeoPluginConflictDetector::$conflicts = [];
	$status = Bootstrap::extension_status();
	cb_seo_health_expect( 'ok' === $status['state'] && 'SEO enabled' === $status['detail'], 'Enabled conflict-free SEO must report healthy.' );

	SeoPluginConflictDetector::$conflicts = [ 'example/seo.php' => 'Example SEO' ];
	$status = Bootstrap::extension_status();
	cb_seo_health_expect( 'warn' === $status['state'] && 'Another SEO plugin is active' === $status['detail'], 'Overlapping SEO providers must surface an operator warning.' );

	$source = file_get_contents( __DIR__ . '/../src/Bootstrap.php' );
	cb_seo_health_expect( false !== $source, 'Could not read SEO Bootstrap source.' );
	$status_hook = strpos( $source, "add_filter( 'cb_core_module_status_definitions'" );
	$state_gate  = strpos( $source, 'if ( ! State::is_enabled() )' );
	cb_seo_health_expect( false !== $status_hook && false !== $state_gate && $status_hook < $state_gate, 'Status provider must remain registered while SEO is deliberately disabled.' );

	echo "SEO dashboard health: PASS\n";
}
