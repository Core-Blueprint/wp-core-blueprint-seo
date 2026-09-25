<?php
declare(strict_types=1);
/**
 * Builder-neutral HTML sitemap resolver and renderer.
 *
 * @package Core_Blueprint_SEO
 */

namespace CB\SEO\Frontend;

use CB\SEO\Indexing\SitemapPolicy;
use WP_Post;
use WP_Post_Type;
use WP_Term;
use WP_Taxonomy;

defined( 'ABSPATH' ) || exit;

final class Sitemap {
	/** @return array<string,string> */
	public static function post_type_options(): array {
		$options = [];
		$objects = get_post_types( [ 'public' => true ], 'objects' );
		if ( ! is_array( $objects ) ) {
			return $options;
		}

		foreach ( $objects as $object ) {
			if ( ! $object instanceof WP_Post_Type || 'attachment' === $object->name || ! SitemapPolicy::post_type_allowed( $object->name ) ) {
				continue;
			}
			$options[ $object->name ] = isset( $object->labels->name ) ? (string) $object->labels->name : $object->name;
		}

		return $options;
	}

	/** @return array<string,string> */
	public static function taxonomy_options(): array {
		$options = [];
		$objects = get_taxonomies( [ 'public' => true ], 'objects' );
		if ( ! is_array( $objects ) ) {
			return $options;
		}

		foreach ( $objects as $object ) {
			if ( ! $object instanceof WP_Taxonomy || ! SitemapPolicy::taxonomy_allowed( $object->name ) ) {
				continue;
			}
			$options[ $object->name ] = isset( $object->labels->name ) ? (string) $object->labels->name : $object->name;
		}

		return $options;
	}

	/** @param array<string,mixed> $args */
	public static function render( array $args = [] ): string {
		$args = self::normalize_args( $args );
		Assets::enqueue();

		$sections = [];
		foreach ( self::resolve_post_types( $args['post_types'] ) as $post_type ) {
			$items = self::posts_for_type( $post_type->name, $args );
			if ( [] === $items ) {
				continue;
			}
			$sections[] = self::render_post_section( $post_type, $items, $args );
		}

		foreach ( self::resolve_taxonomies( $args['taxonomies'] ) as $taxonomy ) {
			$items = self::terms_for_taxonomy( $taxonomy->name, $args );
			if ( [] === $items ) {
				continue;
			}
			$sections[] = self::render_term_section( $taxonomy, $items, $args );
		}

		if ( [] === $sections ) {
			return '';
		}

		$classes = [
			'cb-seo-sitemap',
			'cb-seo-sitemap--columns-' . $args['columns'],
		];

		return '<nav class="' . esc_attr( implode( ' ', $classes ) ) . '" aria-label="' . esc_attr__( 'Sitemap', 'core-blueprint-seo' ) . '">'
			. implode( '', $sections )
			. '</nav>';
	}

	/**
	 * @param array<string,mixed> $args
	 * @return array{post_types:array<int,string>,taxonomies:array<int,string>,show_headings:bool,hierarchy:bool,orderby:string,order:string,columns:int}
	 */
	public static function normalize_args( array $args ): array {
		$orderby = isset( $args['orderby'] ) ? sanitize_key( (string) $args['orderby'] ) : 'title';
		if ( ! in_array( $orderby, [ 'title', 'date', 'menu_order' ], true ) ) {
			$orderby = 'title';
		}

		$order = isset( $args['order'] ) ? strtoupper( sanitize_text_field( (string) $args['order'] ) ) : 'ASC';
		if ( ! in_array( $order, [ 'ASC', 'DESC' ], true ) ) {
			$order = 'ASC';
		}

		$columns = isset( $args['columns'] ) ? absint( $args['columns'] ) : 2;
		$columns = max( 1, min( 4, $columns ) );

		return [
			'post_types'    => self::normalize_name_list( $args['post_types'] ?? [] ),
			'taxonomies'    => self::normalize_name_list( $args['taxonomies'] ?? [] ),
			'show_headings' => self::to_bool( $args['show_headings'] ?? true, true ),
			'hierarchy'     => self::to_bool( $args['hierarchy'] ?? true, true ),
			'orderby'       => $orderby,
			'order'         => $order,
			'columns'       => $columns,
		];
	}

	/** @param array<int,string> $requested
	 *  @return array<int,WP_Post_Type>
	 */
	private static function resolve_post_types( array $requested ): array {
		$objects = get_post_types( [ 'public' => true ], 'objects' );
		if ( ! is_array( $objects ) ) {
			return [];
		}

		$resolved = [];
		foreach ( $objects as $object ) {
			if ( ! $object instanceof WP_Post_Type ) {
				continue;
			}
			if ( 'attachment' === $object->name || ! SitemapPolicy::post_type_allowed( $object->name ) ) {
				continue;
			}
			if ( [] !== $requested && ! in_array( $object->name, $requested, true ) ) {
				continue;
			}
			$resolved[] = $object;
		}
		return $resolved;
	}

	/** @param array<int,string> $requested
	 *  @return array<int,WP_Taxonomy>
	 */
	private static function resolve_taxonomies( array $requested ): array {
		if ( [] === $requested ) {
			return [];
		}

		$objects = get_taxonomies( [ 'public' => true ], 'objects' );
		if ( ! is_array( $objects ) ) {
			return [];
		}

		$resolved = [];
		foreach ( $objects as $object ) {
			if ( ! $object instanceof WP_Taxonomy ) {
				continue;
			}
			if ( ! in_array( $object->name, $requested, true ) || ! SitemapPolicy::taxonomy_allowed( $object->name ) ) {
				continue;
			}
			$resolved[] = $object;
		}
		return $resolved;
	}

	/** @param array<string,mixed> $args
	 *  @return array<int,WP_Post>
	 */
	private static function posts_for_type( string $post_type, array $args ): array {
		$query_args = [
			'post_type'           => $post_type,
			'post_status'         => 'publish',
			'posts_per_page'      => -1,
			'no_found_rows'       => true,
			'ignore_sticky_posts' => true,
			'suppress_filters'    => false,
			'orderby'             => self::post_orderby( $args['orderby'] ),
			'order'               => $args['order'],
		];
		$query_args = SitemapPolicy::exclude_noindex_posts( $query_args );
		$posts      = get_posts( $query_args );
		return is_array( $posts ) ? array_values( array_filter( $posts, static fn ( mixed $post ): bool => $post instanceof WP_Post ) ) : [];
	}

	/** @param array<string,mixed> $args
	 *  @return array<int,WP_Term>
	 */
	private static function terms_for_taxonomy( string $taxonomy, array $args ): array {
		$query_args = [
			'taxonomy'   => $taxonomy,
			'hide_empty' => true,
			'orderby'    => 'name',
			'order'      => $args['order'],
		];
		$query_args = SitemapPolicy::exclude_noindex_terms( $query_args );
		$terms      = get_terms( $query_args );
		if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
			return [];
		}
		return array_values( array_filter( $terms, static fn ( mixed $term ): bool => $term instanceof WP_Term ) );
	}

	/** @param array<int,WP_Post> $posts
	 *  @param array<string,mixed> $args
	 */
	private static function render_post_section( WP_Post_Type $post_type, array $posts, array $args ): string {
		$label = isset( $post_type->labels->name ) ? (string) $post_type->labels->name : $post_type->name;
		$list  = self::render_post_list( $posts, $args['hierarchy'] && (bool) $post_type->hierarchical );
		return self::render_section( $label, $list, $args['show_headings'] );
	}

	/** @param array<int,WP_Term> $terms
	 *  @param array<string,mixed> $args
	 */
	private static function render_term_section( WP_Taxonomy $taxonomy, array $terms, array $args ): string {
		$label = isset( $taxonomy->labels->name ) ? (string) $taxonomy->labels->name : $taxonomy->name;
		$list  = self::render_term_list( $terms, $args['hierarchy'] && (bool) $taxonomy->hierarchical );
		return self::render_section( $label, $list, $args['show_headings'] );
	}

	private static function render_section( string $label, string $list, bool $show_heading ): string {
		$heading = $show_heading
			? '<h2 class="cb-seo-sitemap__heading">' . esc_html( $label ) . '</h2>'
			: '';
		return '<section class="cb-seo-sitemap__section">' . $heading . $list . '</section>';
	}

	/** @param array<int,WP_Post> $posts */
	private static function render_post_list( array $posts, bool $hierarchy ): string {
		if ( ! $hierarchy ) {
			$items = array_map( [ self::class, 'render_post_item' ], $posts );
			return '<ul class="cb-seo-sitemap__list">' . implode( '', $items ) . '</ul>';
		}

		$by_id = [];
		foreach ( $posts as $post ) {
			$by_id[ (int) $post->ID ] = $post;
		}

		$children = [];
		foreach ( $posts as $post ) {
			$parent = isset( $by_id[ (int) $post->post_parent ] ) ? (int) $post->post_parent : 0;
			$children[ $parent ][] = $post;
		}

		return self::render_post_branch( $children, 0 );
	}

	/** @param array<int,array<int,WP_Post>> $children */
	private static function render_post_branch( array $children, int $parent ): string {
		if ( empty( $children[ $parent ] ) ) {
			return '';
		}
		$html = '<ul class="cb-seo-sitemap__list">';
		foreach ( $children[ $parent ] as $post ) {
			$html .= self::render_post_item( $post, self::render_post_branch( $children, (int) $post->ID ) );
		}
		return $html . '</ul>';
	}

	private static function render_post_item( WP_Post $post, string $children = '' ): string {
		$url = get_permalink( $post );
		if ( ! is_string( $url ) || '' === $url ) {
			return '';
		}
		return '<li class="cb-seo-sitemap__item"><a class="cb-seo-sitemap__link" href="' . esc_url( $url ) . '">'
			. esc_html( get_the_title( $post ) )
			. '</a>' . $children . '</li>';
	}

	/** @param array<int,WP_Term> $terms */
	private static function render_term_list( array $terms, bool $hierarchy ): string {
		if ( ! $hierarchy ) {
			$items = array_map( [ self::class, 'render_term_item' ], $terms );
			return '<ul class="cb-seo-sitemap__list">' . implode( '', $items ) . '</ul>';
		}

		$by_id = [];
		foreach ( $terms as $term ) {
			$by_id[ (int) $term->term_id ] = $term;
		}

		$children = [];
		foreach ( $terms as $term ) {
			$parent = isset( $by_id[ (int) $term->parent ] ) ? (int) $term->parent : 0;
			$children[ $parent ][] = $term;
		}

		return self::render_term_branch( $children, 0 );
	}

	/** @param array<int,array<int,WP_Term>> $children */
	private static function render_term_branch( array $children, int $parent ): string {
		if ( empty( $children[ $parent ] ) ) {
			return '';
		}
		$html = '<ul class="cb-seo-sitemap__list">';
		foreach ( $children[ $parent ] as $term ) {
			$html .= self::render_term_item( $term, self::render_term_branch( $children, (int) $term->term_id ) );
		}
		return $html . '</ul>';
	}

	private static function render_term_item( WP_Term $term, string $children = '' ): string {
		$url = get_term_link( $term );
		if ( is_wp_error( $url ) || ! is_string( $url ) || '' === $url ) {
			return '';
		}
		return '<li class="cb-seo-sitemap__item"><a class="cb-seo-sitemap__link" href="' . esc_url( $url ) . '">'
			. esc_html( $term->name )
			. '</a>' . $children . '</li>';
	}

	private static function post_orderby( string $orderby ): string {
		return $orderby;
	}

	/** @return array<int,string> */
	private static function normalize_name_list( mixed $value ): array {
		if ( is_string( $value ) ) {
			$value = preg_split( '/\s*,\s*/', trim( $value ) ) ?: [];
		}
		if ( ! is_array( $value ) ) {
			return [];
		}

		$names = [];
		foreach ( $value as $name ) {
			$name = sanitize_key( (string) $name );
			if ( '' !== $name ) {
				$names[] = $name;
			}
		}
		return array_values( array_unique( $names ) );
	}

	private static function to_bool( mixed $value, bool $default ): bool {
		if ( is_bool( $value ) ) {
			return $value;
		}
		if ( is_int( $value ) ) {
			return 0 !== $value;
		}
		if ( is_string( $value ) ) {
			$value = strtolower( trim( $value ) );
			if ( in_array( $value, [ '1', 'true', 'yes', 'on' ], true ) ) {
				return true;
			}
			if ( in_array( $value, [ '0', 'false', 'no', 'off' ], true ) ) {
				return false;
			}
		}
		return $default;
	}
}
