<?php
declare(strict_types=1);

namespace {
	defined( 'ABSPATH' ) || define( 'ABSPATH', __DIR__ . '/' );
	defined( 'CB_SEO_URL' ) || define( 'CB_SEO_URL', 'https://example.test/wp-content/plugins/core-blueprint-seo/' );
	defined( 'CB_SEO_VERSION' ) || define( 'CB_SEO_VERSION', '1.0.0-rc1' );
	defined( 'CB_SEO_ENABLED_OPT' ) || define( 'CB_SEO_ENABLED_OPT', 'cb_seo_enabled' );
	defined( 'CB_SEO_INDEXING_SETTINGS_OPT' ) || define( 'CB_SEO_INDEXING_SETTINGS_OPT', 'cb_seo_indexing_settings' );

	final class WP_Post_Type {
		public object $labels;
		public function __construct( public string $name, string $label, public bool $hierarchical = false ) {
			$this->labels = (object) [ 'name' => $label ];
		}
	}
	final class WP_Taxonomy {
		public object $labels;
		public function __construct( public string $name, string $label, public bool $hierarchical = false ) {
			$this->labels = (object) [ 'name' => $label ];
		}
	}
	final class WP_Post {
		public function __construct(
			public int $ID,
			public string $post_type,
			public int $post_parent,
			public string $post_title
		) {}
	}
	final class WP_Term {
		public function __construct(
			public int $term_id,
			public string $taxonomy,
			public int $parent,
			public string $name
		) {}
	}

	$GLOBALS['cb_test_options'] = [];
	$GLOBALS['cb_test_shortcodes'] = [];
	$GLOBALS['cb_test_post_queries'] = [];
	$GLOBALS['cb_test_term_queries'] = [];
	$GLOBALS['cb_test_styles'] = [];

	function get_option( string $name, mixed $default = false ): mixed {
		return $GLOBALS['cb_test_options'][ $name ] ?? $default;
	}
	function sanitize_key( string $value ): string {
		$value = strtolower( $value );
		return preg_replace( '/[^a-z0-9_\-]/', '', $value ) ?? '';
	}
	function sanitize_text_field( string $value ): string { return trim( $value ); }
	function absint( mixed $value ): int { return abs( (int) $value ); }
	function esc_attr( string $value ): string { return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' ); }
	function esc_html( string $value ): string { return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' ); }
	function esc_url( string $value ): string { return $value; }
	function esc_attr__( string $value, string $domain ): string { return $value; }
	function esc_html__( string $value, string $domain ): string { return $value; }
	function wp_enqueue_style( string $handle, string $src, array $deps = [], string|bool|null $version = false ): void {
		$GLOBALS['cb_test_styles'][ $handle ] = [ $src, $version ];
	}
	function add_action( string $hook, callable $callback, int $priority = 10, int $accepted_args = 1 ): void {}
	function add_shortcode( string $tag, callable $callback ): void { $GLOBALS['cb_test_shortcodes'][ $tag ] = $callback; }
	function shortcode_atts( array $defaults, array $attributes, string $tag = '' ): array { return array_merge( $defaults, $attributes ); }
	function get_post_types( array $args = [], string $output = 'names' ): array {
		return [
			'page'       => new WP_Post_Type( 'page', 'Pages', true ),
			'post'       => new WP_Post_Type( 'post', 'Posts', false ),
			'attachment' => new WP_Post_Type( 'attachment', 'Media', true ),
		];
	}
	function get_taxonomies( array $args = [], string $output = 'names' ): array {
		return [ 'category' => new WP_Taxonomy( 'category', 'Categories', true ) ];
	}
	function get_posts( array $args ): array {
		$GLOBALS['cb_test_post_queries'][] = $args;
		return match ( $args['post_type'] ) {
			'page' => [
				new WP_Post( 1, 'page', 0, 'Home' ),
				new WP_Post( 2, 'page', 1, 'About' ),
			],
			'post' => [ new WP_Post( 3, 'post', 0, 'News' ) ],
			default => [],
		};
	}
	function get_terms( array $args ): array {
		$GLOBALS['cb_test_term_queries'][] = $args;
		return [
			new WP_Term( 10, 'category', 0, 'Guides' ),
			new WP_Term( 11, 'category', 10, 'SEO' ),
		];
	}
	function get_permalink( WP_Post $post ): string { return 'https://example.test/post/' . $post->ID; }
	function get_the_title( WP_Post $post ): string { return $post->post_title; }
	function get_term_link( WP_Term $term ): string { return 'https://example.test/term/' . $term->term_id; }
	function is_wp_error( mixed $value ): bool { return false; }
	function bricks_is_builder(): bool { return false; }
}

namespace CB\SEO {
	final class State {
		public static function is_enabled(): bool { return '1' === (string) \get_option( CB_SEO_ENABLED_OPT, '1' ); }
	}
}

namespace CB\SEO\Indexing {
	final class SettingsRepository {
		public static function post_type_in_sitemap( string $post_type ): bool { return true; }
		public static function taxonomy_in_sitemap( string $taxonomy ): bool { return true; }
	}
}

namespace CB\SEO\Metadata {
	final class Repository {
		public const NOINDEX_KEY = '_cb_seo_noindex';
	}
}

namespace Bricks {
	class Element {
		public array $controls = [];
		public array $settings = [];
		public $icon = '';
		private array $attributes = [];

		public function set_attribute( string $key, string $name, mixed $value ): void { $this->attributes[ $key ][ $name ] = $value; }
		public function render_attributes( string $key ): string {
			$classes = $this->attributes[ $key ]['class'] ?? [];
			return 'class="' . implode( ' ', is_array( $classes ) ? $classes : [ (string) $classes ] ) . '"';
		}
		public function render_element_placeholder( array $args ): void { echo $args['text'] ?? ''; }
	}
	final class Elements {
		public static array $registered = [];
		public static function register_element( string $file, string $name, string $class ): void {
			self::$registered[] = compact( 'file', 'name', 'class' );
		}
	}
}

namespace {
	require_once dirname( __DIR__ ) . '/src/Indexing/SitemapPolicy.php';
	require_once dirname( __DIR__ ) . '/src/Frontend/Assets.php';
	require_once dirname( __DIR__ ) . '/src/Frontend/Sitemap.php';
	require_once dirname( __DIR__ ) . '/src/Frontend/Shortcodes.php';
	require_once dirname( __DIR__ ) . '/src/Integration/Builders/Bricks/Elements/Element.php';
	require_once dirname( __DIR__ ) . '/src/Integration/Builders/Bricks/Elements/Sitemap.php';
	require_once dirname( __DIR__ ) . '/src/Integration/Builders/Bricks/ElementRegistry.php';

	function cb_assert( bool $condition, string $message ): void {
		if ( ! $condition ) {
			fwrite( STDERR, "FAIL: {$message}\n" );
			exit( 1 );
		}
	}

	\CB\SEO\Frontend\Shortcodes::boot();
	cb_assert( isset( $GLOBALS['cb_test_shortcodes']['cb_seo_sitemap'] ), 'Sitemap shortcode was not registered.' );

	$html = \CB\SEO\Frontend\Shortcodes::sitemap();
	cb_assert( str_contains( $html, 'cb-seo-sitemap--columns-2' ), 'Default sitemap columns are missing.' );
	cb_assert( str_contains( $html, '>Pages<' ) && str_contains( $html, '>Posts<' ), 'Default public post type sections are missing.' );
	cb_assert( ! str_contains( $html, '>Categories<' ), 'Taxonomies must remain opt-in.' );
	cb_assert( str_contains( $html, '>About<' ), 'Hierarchical child page is missing.' );
	cb_assert( isset( $GLOBALS['cb_test_styles']['core-blueprint-seo-frontend'] ), 'Frontend sitemap stylesheet was not enqueued.' );
	foreach ( $GLOBALS['cb_test_post_queries'] as $query ) {
		cb_assert( ! empty( $query['meta_query'] ), 'Post sitemap query did not inherit noindex exclusion.' );
	}

	$html = \CB\SEO\Frontend\Shortcodes::sitemap( [
		'post_types'    => 'page',
		'taxonomies'    => 'category',
		'show_headings' => 'false',
		'columns'       => '3',
	] );
	cb_assert( str_contains( $html, 'cb-seo-sitemap--columns-3' ), 'Requested column modifier is missing.' );
	cb_assert( ! str_contains( $html, '<h2' ), 'Section headings were not disabled.' );
	cb_assert( str_contains( $html, '>Guides<' ) && str_contains( $html, '>SEO<' ), 'Requested taxonomy section did not render.' );
	cb_assert( ! empty( $GLOBALS['cb_test_term_queries'][0]['meta_query'] ), 'Term sitemap query did not inherit noindex exclusion.' );

	$GLOBALS['cb_test_options'][ CB_SEO_ENABLED_OPT ] = '0';
	cb_assert( '' === \CB\SEO\Frontend\Shortcodes::sitemap(), 'Disabled SEO state must fail closed.' );
	$GLOBALS['cb_test_options'][ CB_SEO_ENABLED_OPT ] = '1';

	\CB\SEO\Integration\Builders\Bricks\ElementRegistry::register();
	cb_assert( 1 === count( \Bricks\Elements::$registered ), 'Bricks sitemap element was not registered exactly once.' );
	cb_assert( 'cb-seo-sitemap' === \Bricks\Elements::$registered[0]['name'], 'Unexpected Bricks sitemap element name.' );

	$element = new \CB\SEO\Integration\Builders\Bricks\Elements\Sitemap();
	$element->set_controls();
	cb_assert( isset( $element->controls['postTypes'], $element->controls['taxonomies'], $element->controls['columns'] ), 'Bricks sitemap controls are incomplete.' );
	$element->settings = [ 'postTypes' => [ 'page' ], 'columns' => '1' ];
	ob_start();
	$element->render();
	$element_html = (string) ob_get_clean();
	cb_assert( str_contains( $element_html, 'cb-seo-sitemap--columns-1' ), 'Bricks element did not use the shared sitemap renderer.' );

	echo "PASS: sitemap frontend + Bricks regression\n";
}
