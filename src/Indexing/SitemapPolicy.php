<?php
declare(strict_types=1);
/**
 * Shared sitemap visibility policy for XML and HTML sitemap surfaces.
 *
 * @package Core_Blueprint_SEO
 */

namespace CB\SEO\Indexing;

use CB\SEO\Metadata\Repository;

defined( 'ABSPATH' ) || exit;

final class SitemapPolicy {
	public static function post_type_allowed( string $post_type ): bool {
		return SettingsRepository::post_type_in_sitemap( sanitize_key( $post_type ) );
	}

	public static function taxonomy_allowed( string $taxonomy ): bool {
		return SettingsRepository::taxonomy_in_sitemap( sanitize_key( $taxonomy ) );
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
