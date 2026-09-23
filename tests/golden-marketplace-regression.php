<?php
declare(strict_types=1);

/**
 * Static Golden Marketplace contracts that do not require WordPress.
 *
 * @return string[]
 */
function cb_seo_golden_marketplace_failures( string $root ): array {
	$failures = [];

	$read = static function ( string $path ) use ( $root, &$failures ): string {
		$full = $root . '/' . $path;
		if ( ! is_file( $full ) ) {
			$failures[] = 'Missing Golden contract file: ' . $path;
			return '';
		}
		return (string) file_get_contents( $full );
	};

	$require = static function ( string $content, string $needle, string $message ) use ( &$failures ): void {
		if ( ! str_contains( $content, $needle ) ) {
			$failures[] = $message;
		}
	};

	$bootstrap = $read( 'core-blueprint-seo.php' );
	$require( $bootstrap, 'Requires Plugins: core-blueprint', 'Native Core Blueprint Base dependency is missing.' );
	$require( $bootstrap, "CB_SEO_REQUIRED_API', '1.1'", 'Core API 1.1 requirement is missing.' );
	$require( $bootstrap, "CB_SEO_REQUIRED_BASE', '1.0.0-rc1'", 'Minimum Base version requirement is missing.' );

	$requirements = $read( 'src/Requirements.php' );
	$require( $requirements, "defined( 'CB_CORE_VERSION' )", 'Runtime requirements do not verify the Base version contract.' );
	$require( $requirements, 'version_compare( (string) CB_CORE_VERSION, CB_SEO_REQUIRED_BASE', 'Minimum Base version comparison is missing.' );

	$integration = $read( 'src/Bootstrap.php' );
	$require( $integration, "'requires_api'  => CB_SEO_REQUIRED_API", 'Extension Registry does not use the canonical Core API constant.' );
	$require( $integration, "'requires_base' => CB_SEO_REQUIRED_BASE", 'Extension Registry does not declare the Base version requirement.' );
	$gate = strpos( $integration, 'if ( ! RuntimeGate::frontend_allowed() )' );
	$metadata = false === $gate ? false : strpos( $integration, "\n\t\tRuntime::boot();", $gate );
	if ( false === $gate || false === $metadata || $gate >= $metadata ) {
		$failures[] = 'SEO frontend runtimes are not gated before metadata output boots.';
	}

	$runtime_gate = $read( 'src/Compatibility/RuntimeGate.php' );
	$require( $runtime_gate, '[] === SeoPluginConflictDetector::active_conflicts()', 'Frontend conflict gate is not fail closed.' );

	$discovery = $read( 'src/Discovery/Runtime.php' );
	if ( substr_count( $discovery, 'RuntimeGate::frontend_allowed()' ) < 2 ) {
		$failures[] = 'AI discovery output is not gated for both endpoint and discovery-link output.';
	}

	$schema = $read( 'src/Schema/Runtime.php' );
	foreach ( [ 'JSON_HEX_TAG', 'JSON_HEX_AMP', 'JSON_HEX_APOS', 'JSON_HEX_QUOT' ] as $flag ) {
		$require( $schema, $flag, 'Inline JSON-LD is missing ' . $flag . ' escaping.' );
	}
	if ( str_contains( $schema, 'wp_json_encode( $payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )' ) {
		$failures[] = 'Unsafe legacy inline JSON-LD encoding remains.';
	}

	foreach ( [ 'src/Governance/Audit.php', 'src/State.php', 'src/Lifecycle.php' ] as $path ) {
		$source = $read( $path );
		if ( str_contains( $source, 'class_exists( AuditLog::class )' ) ) {
			$failures[] = $path . ' still treats required Base audit logging as optional.';
		}
	}

	$config = json_decode( $read( 'tools/i18n/config.json' ), true );
	if ( ! is_array( $config ) || true !== array_key_exists( 'commit_mo', $config ) || false !== $config['commit_mo'] ) {
		$failures[] = 'Localization must keep MO files release-only (commit_mo=false).';
	}
	$committed_mo = glob( $root . '/languages/*.mo' );
	if ( is_array( $committed_mo ) && [] !== $committed_mo ) {
		$failures[] = 'Committed MO artifacts remain in source control.';
	}

	$builder = $read( 'tools/build-release' );
	foreach ( [
		'CB_SEO_REQUIRED_API must remain 1.1' => 'Release builder does not enforce Core API 1.1.',
		'CB_SEO_REQUIRED_BASE must remain 1.0.0-rc1' => 'Release builder does not enforce the minimum Base version.',
		'Requires Plugins must declare core-blueprint' => 'Release builder does not verify the native Base dependency.',
		'msgfmt --check-format --check-header -o "$mo" "$po"' => 'Release builder does not compile staged MO catalogs from reviewed PO sources.',
		'working tree contains uncommitted release-source changes' => 'Release builder does not require a clean release source tree.',
	] as $needle => $message ) {
		$require( $builder, $needle, $message );
	}

	return $failures;
}

if ( isset( $_SERVER['SCRIPT_FILENAME'] ) && realpath( (string) $_SERVER['SCRIPT_FILENAME'] ) === __FILE__ ) {
	$failures = cb_seo_golden_marketplace_failures( dirname( __DIR__ ) );
	if ( $failures ) {
		fwrite( STDERR, "Core Blueprint SEO Golden Marketplace regression: FAIL\n" );
		foreach ( $failures as $failure ) {
			fwrite( STDERR, '- ' . $failure . "\n" );
		}
		exit( 1 );
	}
	fwrite( STDOUT, "Core Blueprint SEO Golden Marketplace regression: PASS\n" );
}
