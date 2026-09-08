<?php
declare(strict_types=1);

namespace {
	defined( 'ABSPATH' ) || define( 'ABSPATH', __DIR__ . '/' );

	final class WP_Post_Type {
		public function __construct(
			public string $name,
			public bool $publicly_queryable,
			public bool $exclude_from_search = false,
			public bool $_builtin = false,
			public bool $public = true
		) {}
	}

	$GLOBALS['cb_test_post_types'] = [
		'page'            => new WP_Post_Type( 'page', true, false, true ),
		'internal_layout' => new WP_Post_Type( 'internal_layout', false ),
		'hidden_content'  => new WP_Post_Type( 'hidden_content', true, true ),
		'attachment'      => new WP_Post_Type( 'attachment', true, false, true ),
		'disabled'        => new WP_Post_Type( 'disabled', true ),
	];

	function sanitize_key( string $value ): string {
		$value = strtolower( $value );
		return preg_replace( '/[^a-z0-9_\-]/', '', $value ) ?? '';
	}

	function get_post_type_object( string $post_type ): ?WP_Post_Type {
		return $GLOBALS['cb_test_post_types'][ $post_type ] ?? null;
	}

	function is_post_type_viewable( WP_Post_Type $post_type ): bool {
		return $post_type->publicly_queryable || ( $post_type->_builtin && $post_type->public );
	}
}

namespace CB\SEO\Indexing {
	final class SettingsRepository {
		public static function post_type_in_sitemap( string $post_type ): bool {
			return 'disabled' !== $post_type;
		}

		public static function taxonomy_in_sitemap( string $taxonomy ): bool {
			return true;
		}
	}
}

namespace {
	require_once dirname( __DIR__ ) . '/src/Indexing/SitemapPolicy.php';

	function cb_assert( bool $condition, string $message ): void {
		if ( ! $condition ) {
			fwrite( STDERR, "FAIL: {$message}\n" );
			exit( 1 );
		}
	}

	cb_assert( \CB\SEO\Indexing\SitemapPolicy::post_type_allowed( 'page' ), 'Front-facing page must remain sitemap eligible.' );
	cb_assert( ! \CB\SEO\Indexing\SitemapPolicy::post_type_allowed( 'internal_layout' ), 'Non-viewable internal/template type must be excluded.' );
	cb_assert( ! \CB\SEO\Indexing\SitemapPolicy::post_type_allowed( 'hidden_content' ), 'Search-excluded content type must be excluded.' );
	cb_assert( ! \CB\SEO\Indexing\SitemapPolicy::post_type_allowed( 'attachment' ), 'Attachments must remain excluded.' );
	cb_assert( ! \CB\SEO\Indexing\SitemapPolicy::post_type_allowed( 'disabled' ), 'Explicit sitemap policy must still win.' );
	cb_assert( ! \CB\SEO\Indexing\SitemapPolicy::post_type_allowed( 'missing' ), 'Unknown post types must fail closed.' );

	echo "PASS: sitemap discoverability regression\n";
}
