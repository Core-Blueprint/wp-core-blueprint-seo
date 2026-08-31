<?php
declare(strict_types=1);
/**
 * Resolves explicit per-object canonical URL overrides.
 *
 * Empty results mean "leave WordPress/site canonical behaviour untouched".
 *
 * @package Core_Blueprint_SEO
 */

namespace CB\SEO\Indexing;

use CB\SEO\Metadata\Repository;
use WP_Post;
use WP_Term;

defined( 'ABSPATH' ) || exit;

final class CanonicalPolicy {
	public static function for_post( WP_Post $post ): ?string {
		$value = trim( Repository::post( (int) $post->ID )['canonical'] );
		return '' === $value ? null : $value;
	}

	public static function for_term( WP_Term $term ): ?string {
		$value = trim( Repository::term( (int) $term->term_id )['canonical'] );
		return '' === $value ? null : $value;
	}
}
