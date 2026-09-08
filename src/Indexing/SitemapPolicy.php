<?php
declare(strict_types=1);
/**
 * Shared sitemap visibility policy for XML and HTML sitemap surfaces.
 *
 * @package Core_Blueprint_SEO
 */

namespace CB\SEO\Indexing;

use CB\SEO\Metadata\Repository;
use WP_Post_Type;

defined( 'ABSPATH' ) || exit;

final class SitemapPolicy {
	public static function post_type_allowed( string $post_type ): bool {
		$post_type = sanitize_key( $post_type );
		if ( '' === $post_type || ! SettingsRepository::post_type_in_sitemap( $post_type ) ) {
			return false;
		}

		// WordPress always provides this API in plugin runtime. Keeping the
		// policy permissive when it is absent lets isolated/static consumers use
		// the stored policy without pretending they have a complete WP registry.
		if ( ! function_exists( 'get_post_type_object' ) ) {
			return true;
		}

		$object = get_post_type_object( $post_type );
		if ( ! $object instanceof WP_Post_Type ) {
			return false;
		}

		return self::post_type_discoverable( $object );
	}

	public static function taxonomy_allowed( string $taxonomy ): bool {
		return SettingsRepository::taxonomy_in_sitemap( sanitize_key( $taxonomy ) );
	}

	/**
	 * A sitemap advertises front-facing content to crawlers. Public registration
	 * alone is not sufficient: internal/template post types may be public for
	 * admin or builder workflows while remaining intentionally non-viewable or
	 * excluded from search.
	 */
	public static function post_type_discoverable( WP_Post_Type $post_type ): bool {
		if ( 'attachment' === $post_type->name ) {
			return false;
		}

		$viewable = function_exists( 'is_post_type_viewable' )
			? is_post_type_viewable( $post_type )
			: (bool) $post_type->publicly_queryable;

		return $viewable && ! (bool) $post_type->exclude_from_search;
	}

	/** @param array<string,mixed> $args
	 *  @return array<string,mixed>
	 */
	public static function exclude_noindex_posts( array $args ): array {
		return self::exclude_noindex( $args, Repository::NOINDEX_KEY );
	}

	/** @param array<string,mixed> $args
	 *  @return array<string,mixed>
	 */
	public static function exclude_noindex_terms( array $args ): array {
		return self::exclude_noindex( $args, Repository::NOINDEX_KEY );
	}

	/** @param array<string,mixed> $args
	 *  @return array<string,mixed>
	 */
	private static function exclude_noindex( array $args, string $meta_key ): array {
		$meta_query = isset( $args['meta_query'] ) && is_array( $args['meta_query'] ) ? $args['meta_query'] : [];
		$meta_query[] = [
			'relation' => 'OR',
			[
				'key'     => $meta_key,
				'compare' => 'NOT EXISTS',
			],
			[
				'key'     => $meta_key,
				'value'   => '1',
				'compare' => '!=',
			],
		];
		$args['meta_query'] = $meta_query;
		return $args;
	}
}
