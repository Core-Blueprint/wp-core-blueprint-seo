<?php
declare(strict_types=1);
/**
 * Detects other plugins that commonly emit overlapping SEO frontend output.
 *
 * @package Core_Blueprint_SEO
 */

namespace CB\SEO\Compatibility;

defined( 'ABSPATH' ) || exit;

final class SeoPluginConflictDetector {
	public static function seopress_is_active(): bool {
		$conflicts = self::active_conflicts();
		return isset( $conflicts['wp-seopress/seopress.php'] ) || isset( $conflicts['wp-seopress-pro/seopress-pro.php'] );
	}

	/** @return array<string,string> plugin basename => display name */
	public static function active_conflicts(): array {
		$known = [
			'wp-seopress/seopress.php'                 => 'SEOPress',
			'wp-seopress-pro/seopress-pro.php'         => 'SEOPress PRO',
			'wordpress-seo/wp-seo.php'                 => 'Yoast SEO',
			'seo-by-rank-math/rank-math.php'           => 'Rank Math SEO',
			'all-in-one-seo-pack/all_in_one_seo_pack.php' => 'All in One SEO',
			'slim-seo/slim-seo.php'                    => 'Slim SEO',
		];

		/**
		 * Allows sites to add another known SEO-output provider without coupling
		 * Core Blueprint SEO to its runtime classes.
		 *
		 * @param array<string,string> $known Plugin basenames and labels.
		 */
		$known = apply_filters( 'cb_seo_conflicting_plugins', $known );
		if ( ! is_array( $known ) ) {
			return [];
		}

		$active = get_option( 'active_plugins', [] );
		$active = is_array( $active ) ? array_map( 'strval', $active ) : [];
		if ( is_multisite() ) {
			$network = get_site_option( 'active_sitewide_plugins', [] );
			if ( is_array( $network ) ) {
				$active = array_merge( $active, array_map( 'strval', array_keys( $network ) ) );
			}
		}
		$active = array_fill_keys( array_unique( $active ), true );

		$conflicts = [];
		foreach ( $known as $basename => $label ) {
			$basename = (string) $basename;
			if ( isset( $active[ $basename ] ) ) {
				$conflicts[ $basename ] = (string) $label;
			}
		}

		return $conflicts;
	}
}
