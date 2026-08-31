<?php
declare(strict_types=1);
/**
 * Resolves social metadata from explicit overrides and WordPress fallbacks.
 *
 * @package Core_Blueprint_SEO
 */

namespace CB\SEO\Social;

use CB\SEO\Indexing\CanonicalPolicy;
use CB\SEO\Metadata\Repository;
use CB\SEO\Metadata\Resolver as MetadataResolver;
use WP_Post;
use WP_Term;

defined( 'ABSPATH' ) || exit;

final class Resolver {
	public static function supports_current(): bool {
		return is_front_page() || is_home() || is_singular() || is_category() || is_tag() || is_tax();
	}

	public static function current_title(): string {
		if ( is_singular() ) {
			$post = get_queried_object();
			if ( $post instanceof WP_Post ) {
				$meta = Repository::post( (int) $post->ID );
				if ( '' !== trim( $meta['social_title'] ) ) {
					return trim( $meta['social_title'] );
				}
				return MetadataResolver::post_title( $post ) ?? get_the_title( $post );
			}
		}
		if ( is_category() || is_tag() || is_tax() ) {
			$term = get_queried_object();
			if ( $term instanceof WP_Term ) {
				$meta = Repository::term( (int) $term->term_id );
				if ( '' !== trim( $meta['social_title'] ) ) {
					return trim( $meta['social_title'] );
				}
				return MetadataResolver::term_title( $term ) ?? $term->name;
			}
		}
		return wp_get_document_title();
	}

	public static function current_description(): string {
		if ( is_singular() ) {
			$post = get_queried_object();
			if ( $post instanceof WP_Post ) {
				$meta = Repository::post( (int) $post->ID );
				if ( '' !== trim( $meta['social_description'] ) ) {
					return trim( $meta['social_description'] );
				}
				$resolved = MetadataResolver::post_description( $post );
				if ( null !== $resolved && '' !== trim( $resolved ) ) {
					return trim( $resolved );
				}
				return trim( wp_strip_all_tags( (string) get_the_excerpt( $post ), true ) );
			}
		}
		if ( is_category() || is_tag() || is_tax() ) {
			$term = get_queried_object();
			if ( $term instanceof WP_Term ) {
				$meta = Repository::term( (int) $term->term_id );
				if ( '' !== trim( $meta['social_description'] ) ) {
					return trim( $meta['social_description'] );
				}
				$resolved = MetadataResolver::term_description( $term );
				return null !== $resolved ? trim( $resolved ) : trim( wp_strip_all_tags( $term->description, true ) );
			}
		}
		return trim( (string) get_bloginfo( 'description' ) );
	}

	public static function current_url(): string {
		if ( is_singular() ) {
			$post = get_queried_object();
			if ( $post instanceof WP_Post ) {
				return CanonicalPolicy::for_post( $post ) ?? (string) get_permalink( $post );
			}
		}
		if ( is_category() || is_tag() || is_tax() ) {
			$term = get_queried_object();
			if ( $term instanceof WP_Term ) {
				$canonical = CanonicalPolicy::for_term( $term );
				if ( null !== $canonical ) {
					return $canonical;
				}
				$url = get_term_link( $term );
				return is_wp_error( $url ) ? '' : (string) $url;
			}
		}
		return home_url( '/' );
	}

	public static function current_image_id(): int {
		if ( is_singular() ) {
			$post = get_queried_object();
			if ( $post instanceof WP_Post ) {
				$meta = Repository::post( (int) $post->ID );
				if ( $meta['social_image_id'] > 0 ) {
					return $meta['social_image_id'];
				}
				$featured = get_post_thumbnail_id( $post );
				if ( $featured ) {
					return (int) $featured;
				}
			}
		} elseif ( is_category() || is_tag() || is_tax() ) {
			$term = get_queried_object();
			if ( $term instanceof WP_Term ) {
				$meta = Repository::term( (int) $term->term_id );
				if ( $meta['social_image_id'] > 0 ) {
					return $meta['social_image_id'];
				}
			}
		}
		return SettingsRepository::all()['default_image_id'];
	}

	public static function current_image_url(): string {
		$id = self::current_image_id();
		return $id > 0 ? (string) wp_get_attachment_image_url( $id, 'full' ) : '';
	}
}
