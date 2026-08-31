<?php
declare(strict_types=1);
/**
 * Fail-closed bridge for Core Blueprint Access managed content.
 *
 * SEO intentionally does not import private Access classes or read Access-owned
 * options. Until Access exposes a stable anonymous-public resolver contract,
 * content enrolled in the canonical cb-access taxonomy is excluded from AI
 * discovery unless an explicit public bridge grants it.
 *
 * @package Core_Blueprint_SEO
 */

namespace CB\SEO\Discovery;

use WP_Post;

defined( 'ABSPATH' ) || exit;

final class AccessGuard {
	public static function allows_public_discovery( WP_Post $post ): bool {
		if ( ! defined( 'CB_ACCESS_VERSION' ) || ! taxonomy_exists( 'cb-access' ) ) {
			return true;
		}

		if ( ! is_object_in_taxonomy( $post->post_type, 'cb-access' ) ) {
			return true;
		}

		/**
		 * Explicit bridge for the owning Access layer to prove that a managed post
		 * is anonymously public. Default false is intentional: discovery must not
		 * infer Access rules from private implementation details.
		 *
		 * @param bool    $public Whether anonymous public discovery is allowed.
		 * @param WP_Post $post   Candidate post managed by Core Blueprint Access.
		 */
		return (bool) apply_filters( 'cb_seo_access_managed_post_is_public', false, $post );
	}

	/** @param string[] $post_types */
	public static function managed_types( array $post_types ): array {
		if ( ! defined( 'CB_ACCESS_VERSION' ) || ! taxonomy_exists( 'cb-access' ) ) {
			return [];
		}

		return array_values(
			array_filter(
				$post_types,
				static fn ( string $post_type ): bool => is_object_in_taxonomy( $post_type, 'cb-access' )
			)
		);
	}
}
