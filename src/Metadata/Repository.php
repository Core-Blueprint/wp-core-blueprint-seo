<?php
declare(strict_types=1);
/**
 * Per-object SEO metadata storage.
 *
 * @package Core_Blueprint_SEO
 */

namespace CB\SEO\Metadata;

defined( 'ABSPATH' ) || exit;

final class Repository {
	public const TITLE_KEY         = '_cb_seo_title';
	public const DESCRIPTION_KEY   = '_cb_seo_description';
	public const CANONICAL_KEY     = '_cb_seo_canonical';
	public const NOINDEX_KEY       = '_cb_seo_noindex';
	public const NOFOLLOW_KEY      = '_cb_seo_nofollow';
	public const NOIMAGEINDEX_KEY  = '_cb_seo_noimageindex';
	public const NOARCHIVE_KEY     = '_cb_seo_noarchive';
	public const NOSNIPPET_KEY     = '_cb_seo_nosnippet';
	public const SOCIAL_TITLE_KEY       = '_cb_seo_social_title';
	public const SOCIAL_DESCRIPTION_KEY = '_cb_seo_social_description';
	public const SOCIAL_IMAGE_ID_KEY    = '_cb_seo_social_image_id';

	/** @return array{title:string,description:string,canonical:string,noindex:bool,nofollow:bool,noimageindex:bool,noarchive:bool,nosnippet:bool,social_title:string,social_description:string,social_image_id:int} */
	public static function post( int $post_id ): array {
		return self::read(
			static fn( string $key ): string => (string) get_post_meta( $post_id, $key, true )
		);
	}

	/** @return array{title:string,description:string,canonical:string,noindex:bool,nofollow:bool,noimageindex:bool,noarchive:bool,nosnippet:bool,social_title:string,social_description:string,social_image_id:int} */
	public static function term( int $term_id ): array {
		return self::read(
			static fn( string $key ): string => (string) get_term_meta( $term_id, $key, true )
		);
	}

	/** @param array<string,mixed> $values */
	public static function save_post( int $post_id, array $values ): bool {
		return self::save_meta(
			static fn( string $key ): string => (string) get_post_meta( $post_id, $key, true ),
			static function ( string $key, string $value ) use ( $post_id ): void {
				if ( '' === $value ) {
					delete_post_meta( $post_id, $key );
				} else {
					update_post_meta( $post_id, $key, $value );
				}
			},
			$values
		);
	}

	/** @param array<string,mixed> $values */
	public static function save_term( int $term_id, array $values ): bool {
		return self::save_meta(
			static fn( string $key ): string => (string) get_term_meta( $term_id, $key, true ),
			static function ( string $key, string $value ) use ( $term_id ): void {
				if ( '' === $value ) {
					delete_term_meta( $term_id, $key );
				} else {
					update_term_meta( $term_id, $key, $value );
				}
			},
			$values
		);
	}

	/** @return array<string,bool> */
	public static function robots_from_values( array $values ): array {
		return [
			'noindex'      => ! empty( $values['noindex'] ),
			'nofollow'     => ! empty( $values['nofollow'] ),
			'noimageindex' => ! empty( $values['noimageindex'] ),
			'noarchive'    => ! empty( $values['noarchive'] ),
			'nosnippet'    => ! empty( $values['nosnippet'] ),
		];
	}

	/** @param callable(string):string $reader
	 *  @return array{title:string,description:string,canonical:string,noindex:bool,nofollow:bool,noimageindex:bool,noarchive:bool,nosnippet:bool,social_title:string,social_description:string,social_image_id:int}
	 */
	private static function read( callable $reader ): array {
		return [
			'title'        => $reader( self::TITLE_KEY ),
			'description'  => $reader( self::DESCRIPTION_KEY ),
			'canonical'    => $reader( self::CANONICAL_KEY ),
			'noindex'      => '1' === $reader( self::NOINDEX_KEY ),
			'nofollow'     => '1' === $reader( self::NOFOLLOW_KEY ),
			'noimageindex' => '1' === $reader( self::NOIMAGEINDEX_KEY ),
			'noarchive'    => '1' === $reader( self::NOARCHIVE_KEY ),
			'nosnippet'          => '1' === $reader( self::NOSNIPPET_KEY ),
			'social_title'        => $reader( self::SOCIAL_TITLE_KEY ),
			'social_description'  => $reader( self::SOCIAL_DESCRIPTION_KEY ),
			'social_image_id'     => absint( $reader( self::SOCIAL_IMAGE_ID_KEY ) ),
		];
	}

	/**
	 * @param callable(string):string $reader
	 * @param callable(string,string):void $writer
	 * @param array<string,mixed> $values
	 */
	private static function save_meta( callable $reader, callable $writer, array $values ): bool {
		$next = [];

		if ( array_key_exists( 'title', $values ) ) {
			$next[ self::TITLE_KEY ] = sanitize_text_field( wp_unslash( (string) $values['title'] ) );
		}
		if ( array_key_exists( 'description', $values ) ) {
			$next[ self::DESCRIPTION_KEY ] = sanitize_textarea_field( wp_unslash( (string) $values['description'] ) );
		}
		if ( array_key_exists( 'canonical', $values ) ) {
			$next[ self::CANONICAL_KEY ] = self::sanitize_canonical( (string) $values['canonical'] );
		}
		if ( array_key_exists( 'social_title', $values ) ) {
			$next[ self::SOCIAL_TITLE_KEY ] = sanitize_text_field( wp_unslash( (string) $values['social_title'] ) );
		}
		if ( array_key_exists( 'social_description', $values ) ) {
			$next[ self::SOCIAL_DESCRIPTION_KEY ] = sanitize_textarea_field( wp_unslash( (string) $values['social_description'] ) );
		}
		if ( array_key_exists( 'social_image_id', $values ) ) {
			$next[ self::SOCIAL_IMAGE_ID_KEY ] = (string) absint( $values['social_image_id'] );
			if ( '0' === $next[ self::SOCIAL_IMAGE_ID_KEY ] ) {
				$next[ self::SOCIAL_IMAGE_ID_KEY ] = '';
			}
		}

		$boolean_keys = [
			'noindex'      => self::NOINDEX_KEY,
			'nofollow'     => self::NOFOLLOW_KEY,
			'noimageindex' => self::NOIMAGEINDEX_KEY,
			'noarchive'    => self::NOARCHIVE_KEY,
			'nosnippet'    => self::NOSNIPPET_KEY,
		];
		foreach ( $boolean_keys as $input_key => $meta_key ) {
			if ( array_key_exists( $input_key, $values ) ) {
				$next[ $meta_key ] = ! empty( $values[ $input_key ] ) ? '1' : '';
			}
		}

		$changed = false;
		foreach ( $next as $key => $value ) {
			if ( $reader( $key ) === $value ) {
				continue;
			}
			$writer( $key, $value );
			$changed = true;
		}
		return $changed;
	}

	private static function sanitize_canonical( string $value ): string {
		$value = trim( wp_unslash( $value ) );
		if ( '' === $value ) {
			return '';
		}

		$url = esc_url_raw( $value, [ 'http', 'https' ] );
		$parts = wp_parse_url( $url );
		if ( '' === $url || ! is_array( $parts ) || empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
			return '';
		}

		return $url;
	}
}
