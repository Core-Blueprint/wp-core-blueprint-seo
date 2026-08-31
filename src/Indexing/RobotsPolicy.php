<?php
declare(strict_types=1);
/**
 * Resolves restrictive global and per-object robots directives without replacing WordPress defaults.
 *
 * @package Core_Blueprint_SEO
 */

namespace CB\SEO\Indexing;

use CB\SEO\Metadata\Repository;
use WP_Post;
use WP_Post_Type;
use WP_Term;

defined( 'ABSPATH' ) || exit;

final class RobotsPolicy {
	/** @return array<string,bool> */
	public static function current(): array {
		if ( is_search() ) {
			return [ 'noindex' => true ];
		}
		if ( is_404() ) {
			return [ 'noindex' => true ];
		}
		if ( is_author() && ! SettingsRepository::context_indexable( 'author' ) ) {
			return [ 'noindex' => true ];
		}
		if ( is_date() && ! SettingsRepository::context_indexable( 'date' ) ) {
			return [ 'noindex' => true ];
		}

		$object = get_queried_object();
		if ( $object instanceof WP_Post ) {
			return self::for_post( $object );
		}
		if ( $object instanceof WP_Term ) {
			return self::for_term( $object );
		}
		if ( $object instanceof WP_Post_Type ) {
			return self::for_post_type_archive( $object );
		}
		return [];
	}

	/** @return array<string,bool> */
	public static function for_post( WP_Post $post ): array {
		$directives = self::active_directives( Repository::post( (int) $post->ID ) );
		if ( ! SettingsRepository::post_type_indexable( (string) $post->post_type ) ) {
			$directives['noindex'] = true;
		}
		return $directives;
	}

	/** @return array<string,bool> */
	public static function for_term( WP_Term $term ): array {
		$directives = self::active_directives( Repository::term( (int) $term->term_id ) );
		if ( ! SettingsRepository::taxonomy_indexable( (string) $term->taxonomy ) ) {
			$directives['noindex'] = true;
		}
		return $directives;
	}

	/** @return array<string,bool> */
	public static function for_post_type_archive( WP_Post_Type $post_type ): array {
		return SettingsRepository::post_type_indexable( (string) $post_type->name ) ? [] : [ 'noindex' => true ];
	}

	/** @param array<string,mixed> $values
	 *  @return array<string,bool>
	 */
	private static function active_directives( array $values ): array {
		$resolved = [];
		foreach ( Repository::robots_from_values( $values ) as $directive => $enabled ) {
			if ( $enabled ) {
				$resolved[ $directive ] = true;
			}
		}
		return $resolved;
	}
}
