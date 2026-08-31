<?php
declare(strict_types=1);
/**
 * Resolves small, explicit template variables for SEO metadata.
 *
 * @package Core_Blueprint_SEO
 */

namespace CB\SEO\Metadata;

use WP_Post;
use WP_Term;

defined( 'ABSPATH' ) || exit;

final class TemplateResolver {

	public static function for_post( string $template, WP_Post $post ): string {
		$post_type_object = get_post_type_object( $post->post_type );
		$excerpt = trim( (string) $post->post_excerpt );
		$values = [
			'%title%'     => get_the_title( $post ),
			'%site_name%' => get_bloginfo( 'name' ),
			'%excerpt%'   => $excerpt,
			'%post_type%' => $post_type_object && isset( $post_type_object->labels->singular_name ) ? (string) $post_type_object->labels->singular_name : $post->post_type,
			'%separator%' => '–',
		];
		return self::render( $template, $values );
	}

	public static function for_term( string $template, WP_Term $term ): string {
		$taxonomy = get_taxonomy( $term->taxonomy );
		$values = [
			'%title%'       => $term->name,
			'%term%'        => $term->name,
			'%description%' => wp_strip_all_tags( term_description( (int) $term->term_id, $term->taxonomy ), true ),
			'%site_name%'   => get_bloginfo( 'name' ),
			'%taxonomy%'    => $taxonomy && isset( $taxonomy->labels->singular_name ) ? (string) $taxonomy->labels->singular_name : $term->taxonomy,
			'%separator%'   => '–',
		];
		return self::render( $template, $values );
	}

	/** @param array<string,string> $values */
	private static function render( string $template, array $values ): string {
		$value = strtr( $template, $values );
		$value = preg_replace( '/\s+/u', ' ', $value );
		return trim( is_string( $value ) ? $value : '' );
	}
}
