<?php
declare(strict_types=1);
/**
 * Vendor-neutral import of existing SEO metadata stored in WordPress meta.
 *
 * Discovery is based on semantic meta-key names rather than plugin identity.
 * Source metadata is read-only and existing Core Blueprint SEO values always win.
 * Global plugin options/templates are intentionally not guessed because WordPress
 * has no canonical SEO option schema for those values.
 *
 * @package Core_Blueprint_SEO
 */

namespace CB\SEO\Migration;

use CB\SEO\Metadata\Repository;
use wpdb;

defined( 'ABSPATH' ) || exit;

final class WordPressMetadataImporter {
	private const BATCH_SIZE = 250;
	private const MAX_META_KEYS = 5000;
	private const MAX_CANDIDATES = 120;

	/** @var string[] */
	private const TARGETS = [
		'title',
		'description',
		'canonical',
		'noindex',
		'nofollow',
		'noimageindex',
		'noarchive',
		'nosnippet',
		'social_title',
		'social_description',
		'social_image_id',
	];

	/**
	 * @return array{
	 *     detected:bool,
	 *     candidates:array<int,array{key:string,post_objects:int,term_objects:int,suggested_target:string,default_target:string,confidence:string}>,
	 *     automatic_mappings:int,
	 *     review_mappings:int
	 * }
	 */
	public static function preview(): array {
		$candidates = self::discover_candidates();
		$suggestion_counts = [];

		foreach ( $candidates as $candidate ) {
			if ( 'automatic' !== $candidate['confidence'] || '' === $candidate['suggested_target'] ) {
				continue;
			}
			$suggestion_counts[ $candidate['suggested_target'] ] = ( $suggestion_counts[ $candidate['suggested_target'] ] ?? 0 ) + 1;
		}

		$automatic = 0;
		$review = 0;
		foreach ( $candidates as &$candidate ) {
			$candidate['default_target'] = '';
			if (
				'automatic' === $candidate['confidence']
				&& '' !== $candidate['suggested_target']
				&& 1 === ( $suggestion_counts[ $candidate['suggested_target'] ] ?? 0 )
			) {
				$candidate['default_target'] = $candidate['suggested_target'];
				++$automatic;
			} else {
				++$review;
			}
		}
		unset( $candidate );

		return [
			'detected'            => ! empty( $candidates ),
			'candidates'          => $candidates,
			'automatic_mappings'  => $automatic,
			'review_mappings'     => $review,
		];
	}

	/** @return string[] */
	public static function target_ids(): array {
		return self::TARGETS;
	}

	/**
	 * @param mixed $raw_rows Form rows shaped as [ [ key => string, target => string ], ... ].
	 * @return array{posts_changed:int,terms_changed:int,fields_imported:int,skipped_existing:int,skipped_invalid:int,skipped_conflicts:int,mappings_used:int}
	 */
	public static function import( $raw_rows ): array {
		$report = [
			'posts_changed'     => 0,
			'terms_changed'     => 0,
			'fields_imported'   => 0,
			'skipped_existing'  => 0,
			'skipped_invalid'   => 0,
			'skipped_conflicts' => 0,
			'mappings_used'     => 0,
		];

		$mapping = self::validated_mapping( $raw_rows );
		$report['mappings_used'] = count( $mapping );
		if ( empty( $mapping ) ) {
			return $report;
		}

		self::import_objects( 'post', $mapping, $report );
		self::import_objects( 'term', $mapping, $report );
		return $report;
	}

	/**
	 * @return array<int,array{key:string,post_objects:int,term_objects:int,suggested_target:string,default_target:string,confidence:string}>
	 */
	private static function discover_candidates(): array {
		$post = self::discover_for_kind( 'post' );
		$term = self::discover_for_kind( 'term' );
		$keys = array_unique( array_merge( array_keys( $post ), array_keys( $term ) ) );
		$candidates = [];

		foreach ( $keys as $key ) {
			$key = (string) $key;
			if ( self::is_core_blueprint_key( $key ) ) {
				continue;
			}

			$inference = self::infer_target( $key );
			if ( null === $inference ) {
				continue;
			}

			$candidates[] = [
				'key'              => $key,
				'post_objects'     => (int) ( $post[ $key ] ?? 0 ),
				'term_objects'     => (int) ( $term[ $key ] ?? 0 ),
				'suggested_target' => $inference['target'],
				'default_target'   => '',
				'confidence'       => $inference['confidence'],
			];
		}

		usort(
			$candidates,
			static function ( array $a, array $b ): int {
				$confidence = [ 'automatic' => 0, 'review' => 1 ];
				$left  = $confidence[ $a['confidence'] ] ?? 2;
				$right = $confidence[ $b['confidence'] ] ?? 2;
				if ( $left !== $right ) {
					return $left <=> $right;
				}
				$left_count = $a['post_objects'] + $a['term_objects'];
				$right_count = $b['post_objects'] + $b['term_objects'];
				if ( $left_count !== $right_count ) {
					return $right_count <=> $left_count;
				}
				return strcasecmp( $a['key'], $b['key'] );
			}
		);

		return array_slice( $candidates, 0, self::MAX_CANDIDATES );
	}

	/** @return array<string,int> */
	private static function discover_for_kind( string $kind ): array {
		global $wpdb;
		if ( ! $wpdb instanceof wpdb ) {
			return [];
		}

		$table = 'post' === $kind ? $wpdb->postmeta : $wpdb->termmeta;
		$id_column = 'post' === $kind ? 'post_id' : 'term_id';
		$excluded_prefix = $wpdb->esc_like( '_cb_seo_' ) . '%';

		// First inspect only distinct metadata keys. This lets MySQL use the
		// meta_key index instead of applying a large set of leading-wildcard LIKE
		// predicates across every metadata row. Values are never selected here.
		$sql = "SELECT DISTINCT meta_key FROM {$table} WHERE meta_key NOT LIKE %s ORDER BY meta_key ASC LIMIT %d";
		$keys = $wpdb->get_col( $wpdb->prepare( $sql, $excluded_prefix, self::MAX_META_KEYS ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table name is a trusted WordPress property.
		if ( ! is_array( $keys ) ) {
			return [];
		}

		$candidate_keys = [];
		foreach ( $keys as $key ) {
			$key = (string) $key;
			if ( '' !== $key && null !== self::infer_target( $key ) ) {
				$candidate_keys[] = $key;
			}
		}
		if ( empty( $candidate_keys ) ) {
			return [];
		}

		$placeholders = implode( ', ', array_fill( 0, count( $candidate_keys ), '%s' ) );
		$count_sql = "SELECT meta_key, COUNT(DISTINCT {$id_column}) AS object_count FROM {$table} WHERE meta_key IN ({$placeholders}) GROUP BY meta_key";
		$rows = $wpdb->get_results( $wpdb->prepare( $count_sql, ...$candidate_keys ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table/column names are trusted WordPress properties.
		if ( ! is_array( $rows ) ) {
			return [];
		}

		$out = [];
		foreach ( $rows as $row ) {
			$key = isset( $row['meta_key'] ) ? (string) $row['meta_key'] : '';
			if ( '' === $key ) {
				continue;
			}
			$out[ $key ] = isset( $row['object_count'] ) ? absint( $row['object_count'] ) : 0;
		}
		return $out;
	}

	/** @return array{target:string,confidence:string}|null */
	private static function infer_target( string $key ): ?array {
		$normalized = strtolower( preg_replace( '/[^a-zA-Z0-9]+/', '_', $key ) ?? '' );
		$normalized = trim( $normalized, '_' );
		if ( '' === $normalized ) {
			return null;
		}

		$tokens = array_values( array_filter( explode( '_', $normalized ) ) );
		$has = static fn( string $token ): bool => in_array( $token, $tokens, true );
		$contains = static fn( string $needle ): bool => false !== strpos( $normalized, $needle );
		$seo_context = $has( 'seo' ) || $has( 'meta' ) || $contains( 'seo' ) || $contains( 'metadata' );
		$social_context = $has( 'social' ) || $has( 'twitter' ) || $has( 'facebook' ) || $has( 'fb' ) || $contains( 'open_graph' ) || $contains( 'opengraph' ) || 1 === preg_match( '/(^|_)og(_|$)/', $normalized );

		if ( $contains( 'noimageindex' ) || ( $has( 'no' ) && $has( 'image' ) && $has( 'index' ) ) ) {
			return [ 'target' => 'noimageindex', 'confidence' => 'automatic' ];
		}
		if ( $contains( 'nosnippet' ) || ( $has( 'no' ) && $has( 'snippet' ) ) ) {
			return [ 'target' => 'nosnippet', 'confidence' => 'automatic' ];
		}
		if ( $contains( 'noarchive' ) || ( $has( 'no' ) && $has( 'archive' ) ) ) {
			return [ 'target' => 'noarchive', 'confidence' => 'automatic' ];
		}
		if ( $contains( 'nofollow' ) || ( $has( 'no' ) && $has( 'follow' ) ) ) {
			return [ 'target' => 'nofollow', 'confidence' => 'automatic' ];
		}
		if ( $contains( 'noindex' ) || ( $has( 'no' ) && $has( 'index' ) ) ) {
			return [ 'target' => 'noindex', 'confidence' => 'automatic' ];
		}
		if ( $contains( 'canonical' ) ) {
			return [ 'target' => 'canonical', 'confidence' => 'automatic' ];
		}

		if ( $social_context && ( $contains( 'image_id' ) || $contains( 'img_id' ) || $contains( 'image_attachment_id' ) || $contains( 'img_attachment_id' ) || ( ( $has( 'image' ) || $has( 'img' ) ) && ( $has( 'id' ) || $has( 'attachment' ) ) ) ) ) {
			return [ 'target' => 'social_image_id', 'confidence' => 'automatic' ];
		}
		if ( $social_context && $has( 'title' ) ) {
			return [ 'target' => 'social_title', 'confidence' => 'automatic' ];
		}
		if ( $social_context && ( $has( 'description' ) || $has( 'desc' ) ) ) {
			return [ 'target' => 'social_description', 'confidence' => 'automatic' ];
		}

		if ( $has( 'title' ) ) {
			return [ 'target' => 'title', 'confidence' => $seo_context ? 'automatic' : 'review' ];
		}
		if ( $has( 'description' ) || $has( 'desc' ) ) {
			return [ 'target' => 'description', 'confidence' => $seo_context ? 'automatic' : 'review' ];
		}

		return null;
	}

	private static function is_core_blueprint_key( string $key ): bool {
		return 0 === strpos( $key, '_cb_seo_' );
	}

	/**
	 * @param mixed $raw_rows
	 * @return array<string,string> source meta key => Core Blueprint target
	 */
	private static function validated_mapping( $raw_rows ): array {
		if ( ! is_array( $raw_rows ) ) {
			return [];
		}

		$available = [];
		foreach ( self::discover_candidates() as $candidate ) {
			$available[ $candidate['key'] ] = true;
		}

		$mapping = [];
		foreach ( $raw_rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$key = isset( $row['key'] ) ? trim( wp_unslash( (string) $row['key'] ) ) : '';
			$target = isset( $row['target'] ) ? sanitize_key( wp_unslash( (string) $row['target'] ) ) : '';
			if ( '' === $key || '' === $target || ! isset( $available[ $key ] ) || ! in_array( $target, self::TARGETS, true ) ) {
				continue;
			}
			$mapping[ $key ] = $target;
		}
		return $mapping;
	}

	/** @param array<string,string> $mapping @param array<string,int> $report */
	private static function import_objects( string $kind, array $mapping, array &$report ): void {
		$last_id = 0;
		do {
			$ids = self::object_ids_after( $kind, array_keys( $mapping ), $last_id );
			foreach ( $ids as $id ) {
				$last_id = $id;
				$target = 'post' === $kind ? Repository::post( $id ) : Repository::term( $id );
				$changes = [];

				foreach ( $mapping as $source_key => $target_key ) {
					$raw = 'post' === $kind ? get_post_meta( $id, $source_key, true ) : get_term_meta( $id, $source_key, true );
					if ( self::is_empty_source( $raw ) ) {
						continue;
					}
					if ( self::target_has_value( $target, $target_key ) ) {
						++$report['skipped_existing'];
						continue;
					}
					if ( array_key_exists( $target_key, $changes ) ) {
						++$report['skipped_conflicts'];
						continue;
					}

					$value = self::convert_value( $target_key, $raw );
					if ( null === $value ) {
						++$report['skipped_invalid'];
						continue;
					}
					$changes[ $target_key ] = $value;
				}

				if ( empty( $changes ) ) {
					continue;
				}
				$changed = 'post' === $kind ? Repository::save_post( $id, $changes ) : Repository::save_term( $id, $changes );
				if ( $changed ) {
					++$report[ 'post' === $kind ? 'posts_changed' : 'terms_changed' ];
					$report['fields_imported'] += count( $changes );
				}
			}
		} while ( count( $ids ) === self::BATCH_SIZE );
	}

	/** @param string[] $source_keys @return int[] */
	private static function object_ids_after( string $kind, array $source_keys, int $last_id ): array {
		global $wpdb;
		if ( ! $wpdb instanceof wpdb || empty( $source_keys ) ) {
			return [];
		}
		$table = 'post' === $kind ? $wpdb->postmeta : $wpdb->termmeta;
		$id_column = 'post' === $kind ? 'post_id' : 'term_id';
		$placeholders = implode( ', ', array_fill( 0, count( $source_keys ), '%s' ) );
		$sql = "SELECT DISTINCT {$id_column} FROM {$table} WHERE meta_key IN ({$placeholders}) AND {$id_column} > %d ORDER BY {$id_column} ASC LIMIT %d";
		$params = array_merge( $source_keys, [ $last_id, self::BATCH_SIZE ] );
		$ids = $wpdb->get_col( $wpdb->prepare( $sql, ...$params ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table/column names are trusted WordPress properties.
		return array_values( array_filter( array_map( 'absint', is_array( $ids ) ? $ids : [] ) ) );
	}

	/** @param mixed $raw */
	private static function is_empty_source( $raw ): bool {
		if ( null === $raw || false === $raw ) {
			return true;
		}
		if ( is_string( $raw ) ) {
			return '' === trim( $raw );
		}
		return false;
	}

	/** @param array<string,mixed> $target */
	private static function target_has_value( array $target, string $target_key ): bool {
		if ( 'social_image_id' === $target_key ) {
			return (int) ( $target[ $target_key ] ?? 0 ) > 0;
		}
		if ( in_array( $target_key, [ 'noindex', 'nofollow', 'noimageindex', 'noarchive', 'nosnippet' ], true ) ) {
			return ! empty( $target[ $target_key ] );
		}
		return '' !== trim( (string) ( $target[ $target_key ] ?? '' ) );
	}

	/** @param mixed $raw @return string|int|bool|null */
	private static function convert_value( string $target_key, $raw ) {
		if ( in_array( $target_key, [ 'noindex', 'nofollow', 'noimageindex', 'noarchive', 'nosnippet' ], true ) ) {
			return self::truthy( $raw ) ? true : null;
		}

		if ( 'social_image_id' === $target_key ) {
			if ( ! is_scalar( $raw ) ) {
				return null;
			}
			$image_id = absint( $raw );
			return $image_id > 0 && 'attachment' === get_post_type( $image_id ) ? $image_id : null;
		}

		if ( ! is_scalar( $raw ) ) {
			return null;
		}
		$value = trim( (string) $raw );
		return '' === $value ? null : $value;
	}

	/** @param mixed $value */
	private static function truthy( $value ): bool {
		if ( true === $value || 1 === $value ) {
			return true;
		}
		if ( ! is_scalar( $value ) ) {
			return false;
		}
		return in_array( strtolower( trim( (string) $value ) ), [ '1', 'yes', 'true', 'on', 'noindex', 'nofollow', 'noimageindex', 'noarchive', 'nosnippet' ], true );
	}
}
