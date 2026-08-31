<?php
declare(strict_types=1);
/**
 * Generates the public llms.txt document from WordPress-visible content.
 *
 * @package Core_Blueprint_SEO
 */

namespace CB\SEO\Discovery;

use CB\SEO\Indexing\RobotsPolicy;
use CB\SEO\Metadata\Resolver;
use WP_Post;

defined( 'ABSPATH' ) || exit;

final class Document {
	public static function render(): string {
		$settings = SettingsRepository::all();
		$site_name = self::plain( (string) get_bloginfo( 'name' ) );
		$tagline = self::plain( (string) get_bloginfo( 'description' ) );
		$home = home_url( '/' );

		$lines = [ '# ' . ( '' !== $site_name ? $site_name : 'Website' ), '' ];
		if ( '' !== $tagline ) {
			$lines[] = '> ' . $tagline;
			$lines[] = '';
		}
		$lines[] = 'Site: ' . $home;

		$remaining = $settings['max_items'];
		foreach ( $settings['post_types'] as $post_type ) {
			if ( $remaining <= 0 ) {
				break;
			}

			$object = get_post_type_object( $post_type );
			if ( ! $object || empty( $object->public ) ) {
				continue;
			}

			$posts = get_posts( [
				'post_type'              => $post_type,
				'post_status'            => 'publish',
				'posts_per_page'         => $remaining,
				'orderby'                => 'title',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'ignore_sticky_posts'    => true,
				'suppress_filters'       => false,
			] );

			$items = [];
			foreach ( $posts as $post ) {
				if ( ! $post instanceof WP_Post || ! self::is_eligible( $post ) ) {
					continue;
				}
				$url = get_permalink( $post );
				if ( ! is_string( $url ) || '' === $url ) {
					continue;
				}
				$title = self::markdown_label( get_the_title( $post ) );
				if ( '' === $title ) {
					$title = self::markdown_label( $url );
				}
				$line = '- [' . $title . '](' . esc_url_raw( $url ) . ')';
				if ( $settings['include_descriptions'] ) {
					$description = Resolver::post_description( $post );
					if ( null === $description || '' === trim( $description ) ) {
						$description = trim( (string) $post->post_excerpt );
					}
					$description = self::plain( (string) $description );
					if ( '' !== $description ) {
						$line .= ': ' . $description;
					}
				}
				$items[] = $line;
				--$remaining;
				if ( $remaining <= 0 ) {
					break;
				}
			}

			if ( empty( $items ) ) {
				continue;
			}
			$label = isset( $object->labels->name ) ? self::plain( (string) $object->labels->name ) : self::plain( $post_type );
			$lines[] = '';
			$lines[] = '## ' . $label;
			$lines[] = '';
			array_push( $lines, ...$items );
		}

		$lines[] = '';
		return implode( "\n", $lines );
	}

	private static function is_eligible( WP_Post $post ): bool {
		$eligible = 'publish' === $post->post_status
			&& '' === (string) $post->post_password
			&& is_post_publicly_viewable( $post )
			&& empty( RobotsPolicy::for_post( $post )['noindex'] );

		/**
		 * Allows any public-access layer to veto discovery before suite-specific
		 * hardening is applied.
		 *
		 * @param bool    $eligible Current WordPress/SEO eligibility.
		 * @param WP_Post $post     Candidate post.
		 */
		$eligible = (bool) apply_filters( 'cb_seo_discovery_post_is_public', $eligible, $post );
		if ( ! $eligible ) {
			return false;
		}

		return AccessGuard::allows_public_discovery( $post );
	}

	private static function plain( string $value ): string {
		$value = wp_strip_all_tags( $value, true );
		$value = preg_replace( '/\s+/u', ' ', $value );
		return trim( is_string( $value ) ? $value : '' );
	}

	private static function markdown_label( string $value ): string {
		$value = self::plain( $value );
		return str_replace( [ '\\', '[', ']' ], [ '\\\\', '\\[', '\\]' ], $value );
	}
}
