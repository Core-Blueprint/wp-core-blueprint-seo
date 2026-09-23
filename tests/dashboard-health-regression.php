<?php
declare(strict_types=1);

namespace {
	defined( 'ABSPATH' ) || define( 'ABSPATH', __DIR__ . '/' );
	defined( 'CB_SEO_BASENAME' ) || define( 'CB_SEO_BASENAME', 'core-blueprint-seo/core-blueprint-seo.php' );
	defined( 'CB_SEO_REQUIRED_API' ) || define( 'CB_SEO_REQUIRED_API', '1.1' );
	defined( 'CB_SEO_REQUIRED_BASE' ) || define( 'CB_SEO_REQUIRED_BASE', '1.0.0-rc1' );

	function admin_url( string $path = '' ): string {
		return 'https://example.test/wp-admin/' . ltrim( $path, '/' );
	}

	function __( string $text, string $domain = 'default' ): string {
		return $text;
	}
}

namespace CB\Core\Admin {
	final class SettingsRegistry {
		public const GROUP_CONTENT_PUBLISHING = 'content-publishing';

		/** @var array{id:string,definition:array<string,mixed>}|null */
		public static ?array $registered = null;

		/** @param array<string,mixed> $definition */
		public static function register( string $extension_id, array $definition ): bool {
			self::$registered = [
				'id'         => $extension_id,
				'definition' => $definition,
			];
			return true;
		}

		/** @param array<string,scalar> $query */
		public static function url( string $extension_id, array $query = [] ): string {
			$args = array_merge(
				[
					'page'      => 'core-blueprint-settings',
					'extension' => $extension_id,
				],
				$query
			);
			return \admin_url( 'admin.php?' . http_build_query( $args ) );
		}
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
	use CB\Core\Admin\SettingsRegistry;
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
	cb_seo_health_expect( CB_SEO_REQUIRED_API === ( $definition['requires_api'] ?? '' ), 'SEO ExtensionRegistry definition must use the canonical Core API requirement.' );
	cb_seo_health_expect( CB_SEO_REQUIRED_BASE === ( $definition['requires_base'] ?? '' ), 'SEO ExtensionRegistry definition must use the canonical Base requirement.' );
	cb_seo_health_expect( str_contains( (string) ( $definition['menu_url'] ?? '' ), 'extension=core-blueprint-seo' ), 'SEO extension menu URL must target the Settings Hub provider.' );

	Bootstrap::register_settings();
	$settings_provider = SettingsRegistry::$registered;
	cb_seo_health_expect( is_array( $settings_provider ), 'SEO must register with SettingsRegistry.' );
	cb_seo_health_expect( 'core-blueprint-seo' === ( $settings_provider['id'] ?? '' ), 'SEO Settings Hub provider must use the ExtensionRegistry identity.' );
	cb_seo_health_expect( SettingsRegistry::GROUP_CONTENT_PUBLISHING === ( $settings_provider['definition']['group'] ?? '' ), 'SEO Settings Hub provider must use Content & Publishing.' );
	cb_seo_health_expect( 'manage_options' === ( $settings_provider['definition']['capability'] ?? '' ), 'SEO Settings Hub provider must preserve manage_options.' );

	$status_definitions = Bootstrap::register_status_definition( [] );
	cb_seo_health_expect( isset( $status_definitions['seo'] ), 'SEO must register a matching status definition.' );
	cb_seo_health_expect( [ Bootstrap::class, 'extension_status' ] === $status_definitions['seo']['provider'], 'SEO status definition must use the canonical provider.' );
	cb_seo_health_expect( str_contains( (string) ( $status_definitions['seo']['url'] ?? '' ), 'extension=core-blueprint-seo' ), 'SEO status URL must target the Settings Hub provider.' );

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
	$runtime_gate = strpos( $source, 'if ( ! RuntimeGate::frontend_allowed() )' );
	cb_seo_health_expect( false !== $status_hook && false !== $runtime_gate && $status_hook < $runtime_gate, 'Status provider must remain registered before the governed frontend runtime gate.' );
	cb_seo_health_expect( false === strpos( $source, 'cb_core_register_pages' ) && false === strpos( $source, 'PageRegistry::register' ), 'Retired PageRegistry settings registration must be absent.' );

	echo "SEO dashboard health: PASS\n";
}
