<?php
declare(strict_types=1);
/**
 * Stores global indexing and sitemap policy.
 *
 * @package Core_Blueprint_SEO
 */

namespace CB\SEO\Indexing;

defined( 'ABSPATH' ) || exit;

final class SettingsRepository {
	/** @return array{post_types:array<string,array{index:bool,sitemap:bool}>,taxonomies:array<string,array{index:bool,sitemap:bool}>,contexts:array<string,array{index:bool}>} */
	public static function all(): array {
		$value = get_option( CB_SEO_INDEXING_SETTINGS_OPT, [] );
		return self::sanitize( is_array( $value ) ? $value : [] );
	}

	/** @return array{index:bool,sitemap:bool} */
	public static function post_type( string $post_type ): array {
		$all = self::all();
		return $all['post_types'][ $post_type ] ?? self::defaults();
	}

	/** @return array{index:bool,sitemap:bool} */
	public static function taxonomy( string $taxonomy ): array {
		$all = self::all();
		return $all['taxonomies'][ $taxonomy ] ?? self::defaults();
	}

	public static function post_type_indexable( string $post_type ): bool {
		return self::post_type( $post_type )['index'];
	}

	public static function taxonomy_indexable( string $taxonomy ): bool {
		return self::taxonomy( $taxonomy )['index'];
	}

	public static function post_type_in_sitemap( string $post_type ): bool {
		$policy = self::post_type( $post_type );
		return $policy['index'] && $policy['sitemap'];
	}

	public static function taxonomy_in_sitemap( string $taxonomy ): bool {
		$policy = self::taxonomy( $taxonomy );
		return $policy['index'] && $policy['sitemap'];
	}

	public static function context_indexable( string $context ): bool {
		$context = sanitize_key( $context );
		$all = self::all();

		if ( isset( $all['contexts'][ $context ] ) ) {
			return $all['contexts'][ $context ]['index'];
		}

		// WordPress already treats search and 404 views as non-indexable. Keep
		// that safe default explicit while author/date archives remain opt-out.
		return ! in_array( $context, [ 'search', '404' ], true );
	}

	/** @param array<string,mixed> $input
	 *  @return array{post_types:array<string,array{index:bool,sitemap:bool}>,taxonomies:array<string,array{index:bool,sitemap:bool}>,contexts:array<string,array{index:bool}>}
	 */
	public static function sanitize( array $input ): array {
		$output = [
			'post_types' => [],
			'taxonomies' => [],
			'contexts'   => [],
		];

		foreach ( [ 'post_types', 'taxonomies' ] as $group ) {
			$raw_group = isset( $input[ $group ] ) && is_array( $input[ $group ] ) ? $input[ $group ] : [];
			foreach ( $raw_group as $key => $values ) {
				$key = sanitize_key( (string) $key );
				if ( '' === $key || ! is_array( $values ) ) {
					continue;
				}

				$index   = ! empty( $values['index'] );
				$sitemap = $index && ! empty( $values['sitemap'] );

				// Default policy is intentionally not stored. This keeps the option
				// compact and lets newly registered public types inherit safe defaults.
				if ( $index && $sitemap ) {
					continue;
				}

				$output[ $group ][ $key ] = [
					'index'   => $index,
					'sitemap' => $sitemap,
				];
			}
		}

		$raw_contexts = isset( $input['contexts'] ) && is_array( $input['contexts'] ) ? $input['contexts'] : [];
		foreach ( [ 'author', 'date' ] as $context ) {
			$index = ! isset( $raw_contexts[ $context ] ) || ! empty( $raw_contexts[ $context ]['index'] );
			if ( ! $index ) {
				$output['contexts'][ $context ] = [ 'index' => false ];
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

		update_option( CB_SEO_INDEXING_SETTINGS_OPT, $sanitized, false );
		return true;
	}

	/** @return array{index:bool,sitemap:bool} */
	private static function defaults(): array {
		return [
			'index'   => true,
			'sitemap' => true,
		];
	}
}
