<?php
declare(strict_types=1);
/**
 * Stores global SEO metadata templates.
 *
 * @package Core_Blueprint_SEO
 */

namespace CB\SEO\Metadata;

defined( 'ABSPATH' ) || exit;

final class SettingsRepository {

	/** @return array{post_types:array<string,array{title:string,description:string}>,taxonomies:array<string,array{title:string,description:string}>} */
	public static function all(): array {
		$value = get_option( CB_SEO_METADATA_SETTINGS_OPT, [] );
		return self::sanitize( is_array( $value ) ? $value : [] );
	}

	/** @return array{title:string,description:string} */
	public static function post_type( string $post_type ): array {
		$all = self::all();
		return $all['post_types'][ $post_type ] ?? [ 'title' => '', 'description' => '' ];
	}

	/** @return array{title:string,description:string} */
	public static function taxonomy( string $taxonomy ): array {
		$all = self::all();
		return $all['taxonomies'][ $taxonomy ] ?? [ 'title' => '', 'description' => '' ];
	}

	/** @param array<string,mixed> $input
	 *  @return array{post_types:array<string,array{title:string,description:string}>,taxonomies:array<string,array{title:string,description:string}>}
	 */
	public static function sanitize( array $input ): array {
		$output = [
			'post_types' => [],
			'taxonomies' => [],
		];

		foreach ( [ 'post_types', 'taxonomies' ] as $group ) {
			$raw_group = isset( $input[ $group ] ) && is_array( $input[ $group ] ) ? $input[ $group ] : [];
			foreach ( $raw_group as $key => $values ) {
				$key = sanitize_key( (string) $key );
				if ( '' === $key || ! is_array( $values ) ) {
					continue;
				}

				$title       = isset( $values['title'] ) ? sanitize_text_field( wp_unslash( (string) $values['title'] ) ) : '';
				$description = isset( $values['description'] ) ? sanitize_textarea_field( wp_unslash( (string) $values['description'] ) ) : '';

				if ( '' === $title && '' === $description ) {
					continue;
				}

				$output[ $group ][ $key ] = [
					'title'       => $title,
					'description' => $description,
				];
			}
		}

		return $output;
	}

	/** @param array<string,mixed> $input */
	public static function save( array $input ): bool {
		$sanitized = self::sanitize( $input );
		$previous  = self::all();
		if ( $sanitized === $previous ) {
			return false;
		}

		update_option( CB_SEO_METADATA_SETTINGS_OPT, $sanitized, false );
		return true;
	}
}
