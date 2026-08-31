<?php
declare(strict_types=1);
/**
 * Non-destructive one-time migration from SEOPress metadata.
 *
 * The importer reads only documented/stable SEOPress option/meta keys that
 * were verified against the supplied SEOPress Free/Pro packages. Existing
 * Core Blueprint SEO values always win and SEOPress data is never deleted.
 *
 * @package Core_Blueprint_SEO
 */

namespace CB\SEO\Migration;

use CB\SEO\Indexing\SettingsRepository as IndexingSettingsRepository;
use CB\SEO\Metadata\Repository;
use CB\SEO\Metadata\SettingsRepository;

use wpdb;

defined( 'ABSPATH' ) || exit;

final class SeoPressImporter {
	private const TITLES_OPTION = 'seopress_titles_option_name';
	private const BATCH_SIZE    = 250;

	/** @var string[] */
	private const SOURCE_META_KEYS = [
		'_seopress_titles_title',
		'_seopress_titles_desc',
		'_seopress_robots_index',
		'_seopress_robots_follow',
		'_seopress_robots_imageindex',
		'_seopress_robots_snippet',
		'_seopress_robots_canonical',
		'_seopress_social_fb_title',
		'_seopress_social_fb_desc',
		'_seopress_social_fb_img',
		'_seopress_social_fb_img_attachment_id',
		'_seopress_social_twitter_title',
		'_seopress_social_twitter_desc',
		'_seopress_social_twitter_img',
		'_seopress_social_twitter_img_attachment_id',
	];

	/** @return array{detected:bool,post_objects:int,term_objects:int,templates:int,unsupported_templates:int} */
	public static function preview(): array {
		$templates = self::preview_templates();
		$post_objects = self::count_objects( 'post' );
		$term_objects = self::count_objects( 'term' );

		return [
			'detected'              => $post_objects > 0 || $term_objects > 0 || self::has_titles_option(),
			'post_objects'          => $post_objects,
			'term_objects'          => $term_objects,
			'templates'             => $templates['supported'],
			'unsupported_templates' => $templates['unsupported'],
		];
	}

	/** @return array{posts_changed:int,terms_changed:int,fields_imported:int,templates_imported:int,indexing_rules_imported:int,skipped_existing:int,unsupported_templates:int} */
	public static function import(): array {
		$report = [
			'posts_changed'          => 0,
			'terms_changed'          => 0,
			'fields_imported'        => 0,
			'templates_imported'     => 0,
			'indexing_rules_imported'=> 0,
			'skipped_existing'       => 0,
			'unsupported_templates'  => 0,
		];

		self::import_objects( 'post', $report );
		self::import_objects( 'term', $report );
		self::import_global_settings( $report );

		return $report;
	}

	/**
	 * Convert a SEOPress template into the deliberately small CB template
	 * vocabulary. Unknown SEOPress variables fail closed instead of leaking an
	 * unsupported token into frontend output.
	 */
	public static function convert_template( string $template ): ?string {
		$template = html_entity_decode( trim( $template ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		if ( '' === $template ) {
			return '';
		}

		$converted = strtr(
			$template,
			[
				'%%post_title%%'       => '%title%',
				'%%term_title%%'       => '%term%',
				'%%sitetitle%%'        => '%site_name%',
				'%%sep%%'              => '%separator%',
				'%%post_excerpt%%'     => '%excerpt%',
				'%%term_description%%' => '%description%',
			]
		);

		if ( preg_match( '/%%[^%]+%%/', $converted ) ) {
			return null;
		}

		return $converted;
	}

	/** @param array<string,int> $report */
	private static function import_objects( string $kind, array &$report ): void {
		$last_id = 0;
		do {
			$ids = self::object_ids_after( $kind, $last_id );
			foreach ( $ids as $id ) {
				$last_id = $id;
				$target = 'post' === $kind ? Repository::post( $id ) : Repository::term( $id );
				$source = self::source_values( $kind, $id );
				$mapped = self::mapped_values( $source, $target, $report );
				if ( empty( $mapped ) ) {
					continue;
				}

				$changed = 'post' === $kind ? Repository::save_post( $id, $mapped ) : Repository::save_term( $id, $mapped );
				if ( $changed ) {
					++$report[ 'post' === $kind ? 'posts_changed' : 'terms_changed' ];
					$report['fields_imported'] += count( $mapped );
				}
			}
		} while ( count( $ids ) === self::BATCH_SIZE );
	}

	/**
	 * @param array<string,string> $source
	 * @param array<string,mixed>  $target
	 * @param array<string,int>    $report
	 * @return array<string,mixed>
	 */
	private static function mapped_values( array $source, array $target, array &$report ): array {
		$mapped = [];

		self::map_text( $mapped, 'title', $source['_seopress_titles_title'] ?? '', (string) $target['title'], $report );
		self::map_text( $mapped, 'description', $source['_seopress_titles_desc'] ?? '', (string) $target['description'], $report );
		self::map_text( $mapped, 'canonical', $source['_seopress_robots_canonical'] ?? '', (string) $target['canonical'], $report );

		$social_title = self::first_non_empty( $source['_seopress_social_fb_title'] ?? '', $source['_seopress_social_twitter_title'] ?? '' );
		$social_desc  = self::first_non_empty( $source['_seopress_social_fb_desc'] ?? '', $source['_seopress_social_twitter_desc'] ?? '' );
		self::map_text( $mapped, 'social_title', $social_title, (string) $target['social_title'], $report );
		self::map_text( $mapped, 'social_description', $social_desc, (string) $target['social_description'], $report );

		$image_id = absint( self::first_non_empty( $source['_seopress_social_fb_img_attachment_id'] ?? '', $source['_seopress_social_twitter_img_attachment_id'] ?? '' ) );
		if ( $image_id > 0 ) {
			if ( (int) $target['social_image_id'] > 0 ) {
				++$report['skipped_existing'];
			} elseif ( 'attachment' === get_post_type( $image_id ) ) {
				$mapped['social_image_id'] = $image_id;
			}
		}

		$boolean_map = [
			'_seopress_robots_index'      => 'noindex',
			'_seopress_robots_follow'     => 'nofollow',
			'_seopress_robots_imageindex' => 'noimageindex',
			'_seopress_robots_snippet'    => 'nosnippet',
		];
		foreach ( $boolean_map as $source_key => $target_key ) {
			if ( ! self::truthy( $source[ $source_key ] ?? '' ) ) {
				continue;
			}
			if ( ! empty( $target[ $target_key ] ) ) {
				++$report['skipped_existing'];
				continue;
			}
			$mapped[ $target_key ] = true;
		}

		return $mapped;
	}

	/** @param array<string,mixed> $mapped @param array<string,int> $report */
	private static function map_text( array &$mapped, string $key, string $source, string $target, array &$report ): void {
		$source = trim( $source );
		if ( '' === $source ) {
			return;
		}
		if ( '' !== trim( $target ) ) {
			++$report['skipped_existing'];
			return;
		}
		$mapped[ $key ] = $source;
	}

	/** @param array<string,int> $report */
	private static function import_global_settings( array &$report ): void {
		$source = get_option( self::TITLES_OPTION, [] );
		if ( ! is_array( $source ) ) {
			return;
		}

		$metadata = SettingsRepository::all();
		$metadata_changed = false;
		foreach ( [ 'post_types' => 'seopress_titles_single_titles', 'taxonomies' => 'seopress_titles_tax_titles' ] as $target_group => $source_key ) {
			$source_group = isset( $source[ $source_key ] ) && is_array( $source[ $source_key ] ) ? $source[ $source_key ] : [];
			foreach ( $source_group as $slug => $values ) {
				$slug = sanitize_key( (string) $slug );
				if ( '' === $slug || ! is_array( $values ) ) {
					continue;
				}
				$current = $metadata[ $target_group ][ $slug ] ?? [ 'title' => '', 'description' => '' ];
				foreach ( [ 'title', 'description' ] as $field ) {
					$raw = isset( $values[ $field ] ) ? trim( (string) $values[ $field ] ) : '';
					if ( '' === $raw ) {
						continue;
					}
					if ( '' !== (string) $current[ $field ] ) {
						++$report['skipped_existing'];
						continue;
					}
					$converted = self::convert_template( $raw );
					if ( null === $converted ) {
						++$report['unsupported_templates'];
						continue;
					}
					$metadata[ $target_group ][ $slug ][ $field ] = $converted;
					$current[ $field ] = $converted;
					++$report['templates_imported'];
					$metadata_changed = true;
				}
			}
		}
		if ( $metadata_changed ) {
			SettingsRepository::save( $metadata );
		}

		$indexing = IndexingSettingsRepository::all();
		$indexing_changed = false;
		foreach ( [ 'post_types' => 'seopress_titles_single_titles', 'taxonomies' => 'seopress_titles_tax_titles' ] as $target_group => $source_key ) {
			$source_group = isset( $source[ $source_key ] ) && is_array( $source[ $source_key ] ) ? $source[ $source_key ] : [];
			foreach ( $source_group as $slug => $values ) {
				$slug = sanitize_key( (string) $slug );
				if ( '' === $slug || ! is_array( $values ) || ! self::truthy( $values['noindex'] ?? '' ) ) {
					continue;
				}
				if ( isset( $indexing[ $target_group ][ $slug ] ) ) {
					++$report['skipped_existing'];
					continue;
				}
				$indexing[ $target_group ][ $slug ] = [ 'index' => false, 'sitemap' => false ];
				++$report['indexing_rules_imported'];
				$indexing_changed = true;
			}
		}

		foreach ( [ 'author' => 'seopress_titles_archives_author_noindex', 'date' => 'seopress_titles_archives_date_noindex' ] as $context => $source_key ) {
			if ( ! self::truthy( $source[ $source_key ] ?? '' ) ) {
				continue;
			}
			if ( isset( $indexing['contexts'][ $context ] ) ) {
				++$report['skipped_existing'];
				continue;
			}
			$indexing['contexts'][ $context ] = [ 'index' => false ];
			++$report['indexing_rules_imported'];
			$indexing_changed = true;
		}

		if ( $indexing_changed ) {
			IndexingSettingsRepository::save( $indexing );
		}
	}

	/** @return array{supported:int,unsupported:int} */
	private static function preview_templates(): array {
		$source = get_option( self::TITLES_OPTION, [] );
		if ( ! is_array( $source ) ) {
			return [ 'supported' => 0, 'unsupported' => 0 ];
		}

		$supported = 0;
		$unsupported = 0;
		foreach ( [ 'seopress_titles_single_titles', 'seopress_titles_tax_titles' ] as $source_key ) {
			$group = isset( $source[ $source_key ] ) && is_array( $source[ $source_key ] ) ? $source[ $source_key ] : [];
			foreach ( $group as $values ) {
				if ( ! is_array( $values ) ) {
					continue;
				}
				foreach ( [ 'title', 'description' ] as $field ) {
					$raw = isset( $values[ $field ] ) ? trim( (string) $values[ $field ] ) : '';
					if ( '' === $raw ) {
						continue;
					}
					if ( null === self::convert_template( $raw ) ) {
						++$unsupported;
					} else {
						++$supported;
					}
				}
			}
		}
		return [ 'supported' => $supported, 'unsupported' => $unsupported ];
	}

	private static function has_titles_option(): bool {
		$value = get_option( self::TITLES_OPTION, null );
		return is_array( $value ) && ! empty( $value );
	}

	private static function count_objects( string $kind ): int {
		global $wpdb;
		if ( ! $wpdb instanceof wpdb ) {
			return 0;
		}
		$table = 'post' === $kind ? $wpdb->postmeta : $wpdb->termmeta;
		$id_column = 'post' === $kind ? 'post_id' : 'term_id';
		$placeholders = implode( ', ', array_fill( 0, count( self::SOURCE_META_KEYS ), '%s' ) );
		$sql = "SELECT COUNT(DISTINCT {$id_column}) FROM {$table} WHERE meta_key IN ({$placeholders})";
		return (int) $wpdb->get_var( $wpdb->prepare( $sql, ...self::SOURCE_META_KEYS ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table/column names are trusted WordPress properties/constants.
	}

	/** @return int[] */
	private static function object_ids_after( string $kind, int $last_id ): array {
		global $wpdb;
		if ( ! $wpdb instanceof wpdb ) {
			return [];
		}
		$table = 'post' === $kind ? $wpdb->postmeta : $wpdb->termmeta;
		$id_column = 'post' === $kind ? 'post_id' : 'term_id';
		$placeholders = implode( ', ', array_fill( 0, count( self::SOURCE_META_KEYS ), '%s' ) );
		$sql = "SELECT DISTINCT {$id_column} FROM {$table} WHERE meta_key IN ({$placeholders}) AND {$id_column} > %d ORDER BY {$id_column} ASC LIMIT %d";
		$params = [ ...self::SOURCE_META_KEYS, $last_id, self::BATCH_SIZE ];
		$ids = $wpdb->get_col( $wpdb->prepare( $sql, ...$params ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table/column names are trusted WordPress properties/constants.
		return array_values( array_filter( array_map( 'absint', is_array( $ids ) ? $ids : [] ) ) );
	}

	/** @return array<string,string> */
	private static function source_values( string $kind, int $id ): array {
		$values = [];
		foreach ( self::SOURCE_META_KEYS as $key ) {
			$values[ $key ] = 'post' === $kind
				? (string) get_post_meta( $id, $key, true )
				: (string) get_term_meta( $id, $key, true );
		}
		return $values;
	}

	private static function first_non_empty( string ...$values ): string {
		foreach ( $values as $value ) {
			if ( '' !== trim( $value ) ) {
				return $value;
			}
		}
		return '';
	}

	/** @param mixed $value */
	private static function truthy( $value ): bool {
		return in_array( strtolower( trim( (string) $value ) ), [ '1', 'yes', 'true', 'on' ], true );
	}
}
