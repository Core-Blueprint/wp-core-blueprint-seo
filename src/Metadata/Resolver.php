<?php
declare(strict_types=1);
/**
 * Central metadata resolver. Empty results mean "leave WordPress untouched".
 *
 * @package Core_Blueprint_SEO
 */

namespace CB\SEO\Metadata;

use WP_Post;
use WP_Term;

defined( 'ABSPATH' ) || exit;

final class Resolver {

	public static function current_title(): ?string {
		if ( is_singular() ) {
			$post = get_queried_object();
			return $post instanceof WP_Post ? self::post_title( $post ) : null;
		}
		if ( is_category() || is_tag() || is_tax() ) {
			$term = get_queried_object();
			return $term instanceof WP_Term ? self::term_title( $term ) : null;
		}
		return null;
	}

	public static function current_description(): ?string {
		if ( is_singular() ) {
			$post = get_queried_object();
			return $post instanceof WP_Post ? self::post_description( $post ) : null;
		}
		if ( is_category() || is_tag() || is_tax() ) {
			$term = get_queried_object();
			return $term instanceof WP_Term ? self::term_description( $term ) : null;
		}
		return null;
	}

	public static function post_title( WP_Post $post ): ?string {
		$meta = Repository::post( (int) $post->ID );
		if ( '' !== trim( $meta['title'] ) ) {
			return trim( $meta['title'] );
		}
		$template = SettingsRepository::post_type( $post->post_type )['title'];
		return self::resolve_post_template( $template, $post );
	}

	public static function post_description( WP_Post $post ): ?string {
		$meta = Repository::post( (int) $post->ID );
		if ( '' !== trim( $meta['description'] ) ) {
			return trim( $meta['description'] );
		}
		$template = SettingsRepository::post_type( $post->post_type )['description'];
		return self::resolve_post_template( $template, $post );
	}

	public static function term_title( WP_Term $term ): ?string {
		$meta = Repository::term( (int) $term->term_id );
		if ( '' !== trim( $meta['title'] ) ) {
			return trim( $meta['title'] );
		}
		$template = SettingsRepository::taxonomy( $term->taxonomy )['title'];
		return self::resolve_term_template( $template, $term );
	}

	public static function term_description( WP_Term $term ): ?string {
		$meta = Repository::term( (int) $term->term_id );
		if ( '' !== trim( $meta['description'] ) ) {
			return trim( $meta['description'] );
		}
		$template = SettingsRepository::taxonomy( $term->taxonomy )['description'];
		return self::resolve_term_template( $template, $term );
	}

	private static function resolve_post_template( string $template, WP_Post $post ): ?string {
		if ( '' === trim( $template ) ) {
			return null;
		}
		$value = TemplateResolver::for_post( $template, $post );
		return '' === $value ? null : $value;
	}

	private static function resolve_term_template( string $template, WP_Term $term ): ?string {
		if ( '' === trim( $template ) ) {
			return null;
		}
		$value = TemplateResolver::for_term( $template, $term );
		return '' === $value ? null : $value;
	}
}
