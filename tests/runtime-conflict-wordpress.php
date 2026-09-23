<?php

use CB\SEO\Bootstrap;
use CB\SEO\Compatibility\RuntimeGate;
use CB\SEO\Compatibility\SeoPluginConflictDetector;
use CB\SEO\Discovery\Runtime as DiscoveryRuntime;
use CB\SEO\Indexing\Runtime as IndexingRuntime;
use CB\SEO\Metadata\Runtime as MetadataRuntime;
use CB\SEO\Schema\Runtime as SchemaRuntime;
use CB\SEO\Social\Runtime as SocialRuntime;

defined( 'ABSPATH' ) || exit;

$failures = [];
$assert = static function ( bool $condition, string $message ) use ( &$failures ): void {
	if ( ! $condition ) {
		$failures[] = $message;
	}
};

wp_set_current_user( 1 );

$assert( defined( 'CB_SEO_REQUIRED_API' ) && '1.1' === CB_SEO_REQUIRED_API, 'SEO does not require Core API 1.1.' );
$assert( defined( 'CB_SEO_REQUIRED_BASE' ) && '1.0.0-rc1' === CB_SEO_REQUIRED_BASE, 'SEO does not require Base 1.0.0-rc1+.' );

$definition = \CB\Core\ExtensionRegistry::definition( 'core-blueprint-seo' );
$assert( is_array( $definition ), 'SEO is not registered with the Base Extension Registry.' );
if ( is_array( $definition ) ) {
	$assert( CB_SEO_REQUIRED_API === ( $definition['requires_api'] ?? null ), 'Extension Registry API contract differs.' );
	$assert( CB_SEO_REQUIRED_BASE === ( $definition['requires_base'] ?? null ), 'Extension Registry Base contract differs.' );
}

$conflicts = SeoPluginConflictDetector::active_conflicts();
$assert( isset( $conflicts['wordpress-seo/wp-seo.php'] ), 'The active conflict fixture was not detected.' );
$assert( ! RuntimeGate::frontend_allowed(), 'Frontend runtime gate did not fail closed for an active SEO conflict.' );

$assert( false === has_filter( 'pre_get_document_title', [ MetadataRuntime::class, 'filter_document_title' ] ), 'Metadata frontend hook booted despite an SEO conflict.' );
$assert( false === has_filter( 'wp_robots', [ IndexingRuntime::class, 'filter_robots' ] ), 'Indexing frontend hook booted despite an SEO conflict.' );
$assert( false === has_action( 'wp_head', [ SocialRuntime::class, 'render' ] ), 'Social frontend hook booted despite an SEO conflict.' );
$assert( false === has_action( 'wp_head', [ SchemaRuntime::class, 'render' ] ), 'Schema frontend hook booted despite an SEO conflict.' );

update_option(
	CB_SEO_DISCOVERY_SETTINGS_OPT,
	[
		'enabled'              => true,
		'post_types'           => [ 'post' ],
		'include_descriptions' => true,
		'max_items'            => 25,
	],
	false
);
ob_start();
DiscoveryRuntime::render_discovery_link();
$link = (string) ob_get_clean();
$assert( '' === $link, 'AI discovery link emitted despite an active SEO conflict.' );

$status = Bootstrap::extension_status();
$assert( 'warn' === ( $status['state'] ?? '' ), 'SEO conflict does not surface warning health state.' );

if ( $failures ) {
	fwrite( STDERR, "Core Blueprint SEO conflict runtime: FAIL\n" );
	foreach ( $failures as $failure ) {
		fwrite( STDERR, '- ' . $failure . "\n" );
	}
	exit( 1 );
}

fwrite( STDOUT, "Core Blueprint SEO conflict runtime: PASS\n" );
