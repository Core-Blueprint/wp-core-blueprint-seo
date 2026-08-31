<?php
declare(strict_types=1);
/**
 * Stores editor-only analysis preferences and snapshots.
 *
 * @package Core_Blueprint_SEO
 */

namespace CB\SEO\Analysis;

defined( 'ABSPATH' ) || exit;

final class Repository {
	public const FOCUS_KEY    = '_cb_seo_focus_keyword';
	public const SNAPSHOT_KEY = '_cb_seo_analysis_snapshot';

	public static function focus_keyword( int $post_id ): string {
		return (string) get_post_meta( $post_id, self::FOCUS_KEY, true );
	}

	public static function save_focus_keyword( int $post_id, string $value ): bool {
		$value  = sanitize_text_field( wp_unslash( $value ) );
		$before = self::focus_keyword( $post_id );
		if ( $before === $value ) {
			return false;
		}

		if ( '' === $value ) {
			delete_post_meta( $post_id, self::FOCUS_KEY );
		} else {
			update_post_meta( $post_id, self::FOCUS_KEY, $value );
		}
		return true;
	}

	/** @return array<string,mixed>|null */
	public static function snapshot( int $post_id ): ?array {
		$value = get_post_meta( $post_id, self::SNAPSHOT_KEY, true );
		return is_array( $value ) ? $value : null;
	}

	/** @param array<string,mixed> $snapshot */
	public static function save_snapshot( int $post_id, array $snapshot ): void {
		update_post_meta( $post_id, self::SNAPSHOT_KEY, $snapshot );
	}

	public static function delete_snapshot( int $post_id ): void {
		delete_post_meta( $post_id, self::SNAPSHOT_KEY );
	}

	public static function delete_all_snapshots(): void {
		delete_post_meta_by_key( self::SNAPSHOT_KEY );
	}
}
