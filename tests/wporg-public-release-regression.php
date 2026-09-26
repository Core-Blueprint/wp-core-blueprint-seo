<?php
declare(strict_types=1);

$root = dirname( __DIR__ );
$bootstrap = (string) file_get_contents( $root . '/core-blueprint-seo.php' );
$readme    = (string) file_get_contents( $root . '/readme.txt' );
$audit     = (string) file_get_contents( $root . '/src/Governance/Audit.php' );
$lifecycle = (string) file_get_contents( $root . '/src/Lifecycle.php' );
$fetcher   = (string) file_get_contents( $root . '/src/Analysis/DocumentFetcher.php' );

$assert = static function ( bool $ok, string $message ): void {
    if ( $ok ) {
        return;
    }
    fwrite( STDERR, "SEO WordPress.org regression failed: {$message}\n" );
    exit( 1 );
};

$assert(
    preg_match( '/^ \\* Requires Plugins:\\s+core-blueprint\\s*$/m', $bootstrap ) === 1,
    'native Core Blueprint Base dependency header is missing'
);
$assert(
    str_starts_with( $readme, '=== Core Blueprint SEO ===' ),
    'WordPress.org readme.txt is missing or invalid'
);
$assert(
    str_contains( $audit, 'CB\\Core\\Governance\\Audit' )
        && ! str_contains( $audit, 'CB\\Core\\Log\\AuditLog' )
        && ! str_contains( $lifecycle, 'CB\\Core\\Log\\AuditLog' ),
    'SEO audit events must use the public Base Governance boundary'
);
$assert(
    str_contains( $fetcher, "home_url( '/' )" )
        && str_contains( $fetcher, "site_url( '/' )" )
        && str_contains( $fetcher, "'cookies'     => []" )
        && str_contains( $fetcher, "'reject_unsafe_urls' => true" ),
    'rendered-page analyzer must retain its same-site anonymous HTTP safety defaults'
);

fwrite( STDOUT, "SEO WordPress.org public-release regression: PASS\n" );
