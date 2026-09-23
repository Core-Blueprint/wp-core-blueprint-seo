<?php
declare(strict_types=1);
/**
 * Minimal schema.org JSON-LD graph with an extension filter boundary.
 *
 * @package Core_Blueprint_SEO
 */

namespace CB\SEO\Schema;

use CB\SEO\Social\Resolver as SocialResolver;
use WP_Post;

defined( 'ABSPATH' ) || exit;

final class Runtime {
	public static function boot(): void {
		if ( ! SettingsRepository::all()['enabled'] ) {
			return;
		}
		add_action( 'wp_head', [ self::class, 'render' ], 20 );
	}

	public static function render(): void {
		if ( ! SocialResolver::supports_current() ) {
			return;
		}
		$graph = self::graph();
		if ( [] === $graph ) {
			return;
		}
		$payload = [
			'@context' => 'https://schema.org',
			'@graph'   => $graph,
		];
		$json = wp_json_encode(
			$payload,
			JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
		);
		if ( ! is_string( $json ) || '' === $json ) {
			return;
		}
		echo "\n<script type=\"application/ld+json\" class=\"cb-seo-schema\">";
		echo $json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON_HEX_* keeps the inline script context safe.
		echo "</script>\n";
	}

	/** @return array<int,array<string,mixed>> */
	public static function graph(): array {
		$settings = SettingsRepository::all();
		$home     = home_url( '/' );
		$url      = SocialResolver::current_url();
		$title    = trim( wp_strip_all_tags( SocialResolver::current_title(), true ) );
		$desc     = trim( wp_strip_all_tags( SocialResolver::current_description(), true ) );
		if ( '' === $url || '' === $title ) {
			return [];
		}

		$identity_name = '' !== trim( $settings['identity_name'] ) ? trim( $settings['identity_name'] ) : (string) get_bloginfo( 'name' );
		$identity_id   = $home . '#identity';
		$website_id    = $home . '#website';
		$page_id       = trailingslashit( $url ) . '#webpage';

		$identity = [
			'@type' => 'person' === $settings['identity_type'] ? 'Person' : 'Organization',
			'@id'   => $identity_id,
			'name'  => $identity_name,
			'url'   => $home,
		];
		$logo_url = $settings['logo_id'] > 0 ? (string) wp_get_attachment_image_url( $settings['logo_id'], 'full' ) : '';
		if ( '' !== $logo_url ) {
			if ( 'person' === $settings['identity_type'] ) {
				$identity['image'] = [ '@type' => 'ImageObject', 'url' => $logo_url ];
			} else {
				$identity['logo'] = [ '@type' => 'ImageObject', 'url' => $logo_url ];
			}
		}

		$website = [
			'@type'     => 'WebSite',
			'@id'       => $website_id,
			'url'       => $home,
			'name'      => (string) get_bloginfo( 'name' ),
			'publisher' => [ '@id' => $identity_id ],
		];
		$site_desc = trim( (string) get_bloginfo( 'description' ) );
		if ( '' !== $site_desc ) {
			$website['description'] = $site_desc;
		}

		$page = [
			'@type'    => 'WebPage',
			'@id'      => $page_id,
			'url'      => $url,
			'name'     => $title,
			'isPartOf' => [ '@id' => $website_id ],
			'about'    => [ '@id' => $identity_id ],
		];
		if ( '' !== $desc ) {
			$page['description'] = $desc;
		}
		if ( is_singular() ) {
			$post = get_queried_object();
			if ( $post instanceof WP_Post ) {
				$page['datePublished'] = get_the_date( DATE_W3C, $post );
				$page['dateModified']  = get_the_modified_date( DATE_W3C, $post );
			}
		}

		$graph = [ $identity, $website, $page ];
		/**
		 * Filters the Core Blueprint SEO schema graph.
		 *
		 * Extensions may append domain-specific nodes while keeping one renderer.
		 *
		 * @param array<int,array<string,mixed>> $graph
		 * @param string                         $url
		 */
		$graph = apply_filters( 'cb_seo_schema_graph', $graph, $url );
		return is_array( $graph ) ? array_values( array_filter( $graph, 'is_array' ) ) : [];
	}
}
