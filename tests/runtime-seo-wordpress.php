<?php

use CB\SEO\Compatibility\RuntimeGate;
use CB\SEO\Discovery\Runtime as DiscoveryRuntime;
use CB\SEO\Indexing\Runtime as IndexingRuntime;
use CB\SEO\Metadata\Runtime as MetadataRuntime;
use CB\SEO\Schema\Runtime as SchemaRuntime;

defined( 'ABSPATH' ) || exit;

$failures = [];
$assert = static function ( bool $condition, string $message ) use ( &$failures ): void {
	if ( ! $condition ) {
		$failures[] = $message;
	}
};

wp_set_current_user( 1 );

$assert( RuntimeGate::frontend_allowed(), 'Conflict-free SEO frontend runtime is not allowed.' );
$assert( false !== has_filter( 'pre_get_document_title', [ MetadataRuntime::class, 'filter_document_title' ] ), 'Metadata frontend hook is missing.' );
$assert( false !== has_filter( 'wp_robots', [ IndexingRuntime::class, 'filter_robots' ] ), 'Indexing frontend hook is missing.' );

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
$assert( str_contains( $link, '/llms.txt' ), 'Conflict-free AI discovery link did not render.' );

$post_id = wp_insert_post(
	[
		'post_type'    => 'post',
		'post_status'  => 'publish',
		'post_title'   => 'Golden runtime schema',
		'post_content' => 'Runtime schema body.',
	],
	true
);
$assert( ! is_wp_error( $post_id ), 'Could not create runtime schema fixture post.' );
$post_id = is_wp_error( $post_id ) ? 0 : (int) $post_id;

if ( $post_id > 0 ) {
	global $wp_query, $wp_the_query;
	$wp_query = new WP_Query( [ 'p' => $post_id, 'post_type' => 'post' ] );
	$wp_the_query = $wp_query;

	$inject = static function ( array $graph ): array {
		$graph[] = [
			'@type' => 'Thing',
			'name'  => '</script><script>cb-seo-runtime-breakout</script>',
		];
		return $graph;
	};
	add_filter( 'cb_seo_schema_graph', $inject, 10, 1 );

	ob_start();
	SchemaRuntime::render();
	$json_ld = (string) ob_get_clean();

	remove_filter( 'cb_seo_schema_graph', $inject, 10 );

	$assert( str_contains( $json_ld, 'cb-seo-schema' ), 'Schema renderer produced no JSON-LD output.' );
	$assert( ! str_contains( $json_ld, '</script><script>cb-seo-runtime-breakout</script>' ), 'JSON-LD allowed a raw script breakout sequence.' );
	$assert(
		str_contains( $json_ld, '\\u003C/script\\u003E\\u003Cscript\\u003Ecb-seo-runtime-breakout\\u003C/script\\u003E' ),
		'JSON-LD did not hex-escape the script breakout sequence.'
	);

	wp_delete_post( $post_id, true );
}

if ( $failures ) {
	fwrite( STDERR, "Core Blueprint SEO healthy runtime: FAIL\n" );
	foreach ( $failures as $failure ) {
		fwrite( STDERR, '- ' . $failure . "\n" );
	}
	exit( 1 );
}

fwrite( STDOUT, "Core Blueprint SEO healthy runtime: PASS\n" );
