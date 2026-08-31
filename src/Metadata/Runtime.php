<?php
declare(strict_types=1);
/**
 * Frontend metadata output for RC2.
 *
 * @package Core_Blueprint_SEO
 */

namespace CB\SEO\Metadata;

defined( 'ABSPATH' ) || exit;

final class Runtime {
	public static function boot(): void {
		add_filter( 'pre_get_document_title', [ self::class, 'filter_document_title' ], 20 );
		add_action( 'wp_head', [ self::class, 'render_description' ], 1 );
	}

	public static function filter_document_title( string $title ): string {
		$resolved = Resolver::current_title();
		return null === $resolved ? $title : $resolved;
	}

	public static function render_description(): void {
		$description = Resolver::current_description();
		if ( null === $description || '' === trim( $description ) ) {
			return;
		}
		printf( "\n<meta name=\"description\" content=\"%s\" />\n", esc_attr( wp_strip_all_tags( $description, true ) ) );
	}
}
