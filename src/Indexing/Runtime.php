<?php
declare(strict_types=1);
/**
 * WordPress-first robots, canonical and sitemap integration.
 *
 * @package Core_Blueprint_SEO
 */

namespace CB\SEO\Indexing;

use WP_Post;
use WP_Term;

defined( 'ABSPATH' ) || exit;

final class Runtime {
	public static function boot(): void {
		add_filter( 'wp_robots', [ self::class, 'filter_robots' ], 20 );
		add_filter( 'get_canonical_url', [ self::class, 'filter_post_canonical' ], 20, 2 );
		add_action( 'wp_head', [ self::class, 'render_term_canonical' ], 10 );
		add_filter( 'wp_sitemaps_post_types', [ self::class, 'filter_sitemap_post_types' ], 20 );
		add_filter( 'wp_sitemaps_taxonomies', [ self::class, 'filter_sitemap_taxonomies' ], 20 );
		add_filter( 'wp_sitemaps_posts_query_args', [ self::class, 'filter_post_sitemap_query' ], 10, 2 );
		add_filter( 'wp_sitemaps_taxonomies_query_args', [ self::class, 'filter_term_sitemap_query' ], 10, 2 );
	}

	public static function filter_post_canonical( string $canonical_url, WP_Post $post ): string {
		$override = CanonicalPolicy::for_post( $post );
		return null === $override ? $canonical_url : $override;
	}

	public static function render_term_canonical(): void {
		if ( ! is_category() && ! is_tag() && ! is_tax() ) {
			return;
		}

		$term = get_queried_object();
		if ( ! $term instanceof WP_Term ) {
			return;
		}

		$canonical = CanonicalPolicy::for_term( $term );
		if ( null === $canonical ) {
			return;
		}

		echo '<link rel="canonical" href="' . esc_url( $canonical ) . '">' . "\n";
	}

	/** @param array<string,mixed> $robots
	 *  @return array<string,mixed>
	 */
	public static function filter_robots( array $robots ): array {
		foreach ( RobotsPolicy::current() as $directive => $enabled ) {
			if ( $enabled ) {
				$robots[ $directive ] = true;
			}
		}
		return $robots;
	}

	/** @param array<string,mixed> $post_types
	 *  @return array<string,mixed>
	 */
	public static function filter_sitemap_post_types( array $post_types ): array {
		foreach ( array_keys( $post_types ) as $post_type ) {
			if ( ! SitemapPolicy::post_type_allowed( (string) $post_type ) ) {
				unset( $post_types[ $post_type ] );
			}
		}
		return $post_types;
	}

	/** @param array<string,mixed> $taxonomies
	 *  @return array<string,mixed>
	 */
	public static function filter_sitemap_taxonomies( array $taxonomies ): array {
		foreach ( array_keys( $taxonomies ) as $taxonomy ) {
			if ( ! SitemapPolicy::taxonomy_allowed( (string) $taxonomy ) ) {
				unset( $taxonomies[ $taxonomy ] );
			}
		}
		return $taxonomies;
	}

	/** @param array<string,mixed> $args
	 *  @return array<string,mixed>
	 */
	public static function filter_post_sitemap_query( array $args, string $post_type ): array {
		if ( ! SitemapPolicy::post_type_allowed( $post_type ) ) {
			$args['post__in'] = [ 0 ];
			return $args;
		}
		return SitemapPolicy::exclude_noindex_posts( $args );
	}

	/** @param array<string,mixed> $args
	 *  @return array<string,mixed>
	 */
	public static function filter_term_sitemap_query( array $args, string $taxonomy ): array {
		if ( ! SitemapPolicy::taxonomy_allowed( $taxonomy ) ) {
			$args['include'] = [ 0 ];
			return $args;
		}
		return SitemapPolicy::exclude_noindex_terms( $args );
	}
}
