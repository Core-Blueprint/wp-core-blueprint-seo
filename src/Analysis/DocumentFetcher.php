<?php
declare(strict_types=1);
/**
 * Fetches the public rendered document used as the canonical analysis source.
 *
 * @package Core_Blueprint_SEO
 */

namespace CB\SEO\Analysis;

use WP_Error;
use WP_Post;

defined( 'ABSPATH' ) || exit;

final class DocumentFetcher {
	/** @return array{url:string,status:int,body:string,x_robots_tag:string}|WP_Error */
	public static function fetch( WP_Post $post ) {
		if ( 'publish' !== $post->post_status ) {
			return new WP_Error( 'cb_seo_analysis_not_published', __( 'Live analysis is available after this content has been published.', 'core-blueprint-seo' ) );
		}

		$url = get_permalink( $post );
		if ( ! is_string( $url ) || '' === $url ) {
			return new WP_Error( 'cb_seo_analysis_no_url', __( 'Core Blueprint could not resolve a public URL for this content.', 'core-blueprint-seo' ) );
		}

		if ( ! self::is_allowed_url( $url ) ) {
			return new WP_Error( 'cb_seo_analysis_external_url', __( 'The resolved URL is outside this WordPress site and was not fetched for security reasons.', 'core-blueprint-seo' ) );
		}

		$args = [
			'timeout'     => 15,
			'redirection'       => 3,
			'reject_unsafe_urls' => true,
			'limit_response_size' => 4 * MB_IN_BYTES,
			'user-agent'  => 'Core Blueprint SEO Analyzer/' . CB_SEO_VERSION,
			'headers'     => [
				'Accept'         => 'text/html,application/xhtml+xml',
				'Cache-Control'  => 'no-cache',
			],
			'cookies'     => [],
		];

		/**
		 * Filters the anonymous live-analysis request arguments.
		 *
		 * Never add authenticated cookies here unless you intentionally want the
		 * analyzer to stop representing a normal public visitor.
		 *
		 * @param array<string,mixed> $args
		 * @param string              $url
		 * @param WP_Post             $post
		 */
		$args = (array) apply_filters( 'cb_seo_analysis_request_args', $args, $url, $post );
		$response = wp_remote_get( $url, $args );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = (string) wp_remote_retrieve_body( $response );
		if ( '' === trim( $body ) ) {
			return new WP_Error( 'cb_seo_analysis_empty_body', __( 'The live URL returned an empty document.', 'core-blueprint-seo' ) );
		}

		return [
			'url'          => $url,
			'status'       => (int) wp_remote_retrieve_response_code( $response ),
			'body'         => $body,
			'x_robots_tag' => (string) wp_remote_retrieve_header( $response, 'x-robots-tag' ),
		];
	}

	private static function is_allowed_url( string $url ): bool {
		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
			return false;
		}
		if ( ! in_array( strtolower( (string) $parts['scheme'] ), [ 'http', 'https' ], true ) ) {
			return false;
		}

		$allowed = [];
		foreach ( [ home_url( '/' ), site_url( '/' ) ] as $site_url ) {
			$host = wp_parse_url( $site_url, PHP_URL_HOST );
			if ( is_string( $host ) && '' !== $host ) {
				$allowed[] = strtolower( $host );
			}
		}

		/** @param string[] $allowed */
		$allowed = (array) apply_filters( 'cb_seo_analysis_allowed_hosts', array_values( array_unique( $allowed ) ), $url );
		$allowed = array_map( 'strtolower', array_filter( array_map( 'strval', $allowed ) ) );
		return in_array( strtolower( (string) $parts['host'] ), $allowed, true );
	}
}
