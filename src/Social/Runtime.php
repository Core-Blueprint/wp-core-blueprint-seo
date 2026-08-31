<?php
declare(strict_types=1);
/**
 * Open Graph and X/Twitter metadata output.
 *
 * @package Core_Blueprint_SEO
 */

namespace CB\SEO\Social;

defined( 'ABSPATH' ) || exit;

final class Runtime {
	public static function boot(): void {
		if ( ! SettingsRepository::all()['enabled'] ) {
			return;
		}
		add_action( 'wp_head', [ self::class, 'render' ], 5 );
	}

	public static function render(): void {
		if ( ! Resolver::supports_current() ) {
			return;
		}

		$title       = trim( wp_strip_all_tags( Resolver::current_title(), true ) );
		$description = trim( wp_strip_all_tags( Resolver::current_description(), true ) );
		$url         = Resolver::current_url();
		$image       = Resolver::current_image_url();
		$type        = is_singular( 'post' ) ? 'article' : 'website';

		if ( '' === $title || '' === $url ) {
			return;
		}

		echo "\n<!-- Core Blueprint SEO social metadata -->\n";
		self::meta_property( 'og:locale', get_locale() );
		self::meta_property( 'og:type', $type );
		self::meta_property( 'og:title', $title );
		self::meta_property( 'og:url', $url );
		self::meta_property( 'og:site_name', (string) get_bloginfo( 'name' ) );
		if ( '' !== $description ) {
			self::meta_property( 'og:description', $description );
		}
		if ( '' !== $image ) {
			self::meta_property( 'og:image', $image );
		}

		self::meta_name( 'twitter:card', '' !== $image ? 'summary_large_image' : 'summary' );
		self::meta_name( 'twitter:title', $title );
		if ( '' !== $description ) {
			self::meta_name( 'twitter:description', $description );
		}
		if ( '' !== $image ) {
			self::meta_name( 'twitter:image', $image );
		}
	}

	private static function meta_property( string $property, string $content ): void {
		printf( '<meta property="%s" content="%s">' . "\n", esc_attr( $property ), esc_attr( $content ) );
	}

	private static function meta_name( string $name, string $content ): void {
		printf( '<meta name="%s" content="%s">' . "\n", esc_attr( $name ), esc_attr( $content ) );
	}
}
