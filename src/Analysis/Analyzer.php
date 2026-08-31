<?php
declare(strict_types=1);
/**
 * Analyzes the rendered public page instead of editor/builder storage.
 *
 * @package Core_Blueprint_SEO
 */

namespace CB\SEO\Analysis;

use WP_Error;
use WP_Post;

defined( 'ABSPATH' ) || exit;

final class Analyzer {
	/** @return array<string,mixed>|WP_Error */
	public static function analyze( WP_Post $post, string $focus_keyword ) {
		$fetched = DocumentFetcher::fetch( $post );
		if ( is_wp_error( $fetched ) ) {
			return $fetched;
		}

		$document = HtmlDocument::from_html( $fetched['body'], $fetched['url'] );
		if ( is_wp_error( $document ) ) {
			return $document;
		}

		$images = $document->image_stats();
		$links  = $document->link_stats();
		$h1s    = $document->h1_texts();
		$robots = trim( $document->robots() . ',' . $fetched['x_robots_tag'], ',' );
		$indexable = ! self::contains_phrase( $robots, 'noindex' );

		$result = [
			'url'          => $fetched['url'],
			'http_status'  => $fetched['status'],
			'title'        => $document->title(),
			'description'  => $document->meta_description(),
			'canonical'    => $document->canonical(),
			'robots'       => $robots,
			'indexable'    => $indexable,
			'h1_count'     => count( $h1s ),
			'first_h1'     => $h1s[0] ?? '',
			'heading_hierarchy_valid' => $document->heading_hierarchy_valid(),
			'word_count'   => $document->word_count(),
			'image_count'  => $images['total'],
			'images_missing_alt' => $images['missing_alt'],
			'internal_links' => $links['internal'],
			'external_links' => $links['external'],
			'og_title'       => $document->has_open_graph_title(),
			'og_description' => $document->has_open_graph_description(),
			'keyword'        => self::keyword_analysis( $document, $fetched['url'], $focus_keyword ),
		];

		return $result;
	}

	/** @return array{focus:string,passed:int,total:int,occurrences:int,checks:array<string,bool>} */
	private static function keyword_analysis( HtmlDocument $document, string $url, string $focus_keyword ): array {
		$focus_keyword = trim( sanitize_text_field( $focus_keyword ) );
		if ( '' === $focus_keyword ) {
			return [ 'focus' => '', 'passed' => 0, 'total' => 0, 'occurrences' => 0, 'checks' => [] ];
		}

		$content = $document->content_text();
		$h1      = implode( ' ', $document->h1_texts() );
		$sub     = implode( ' ', $document->subheading_texts() );
		$intro   = self::slice( $content, 0, 700 );
		$path    = (string) wp_parse_url( $url, PHP_URL_PATH );
		$url_text = rawurldecode( str_replace( [ '-', '_', '/' ], ' ', $path ) );

		$checks = [
			'title'       => self::contains_phrase( $document->title(), $focus_keyword ),
			'description' => self::contains_phrase( $document->meta_description(), $focus_keyword ),
			'url'         => self::contains_phrase( $url_text, $focus_keyword ),
			'h1'          => self::contains_phrase( $h1, $focus_keyword ),
			'introduction' => self::contains_phrase( $intro, $focus_keyword ),
			'subheading'  => self::contains_phrase( $sub, $focus_keyword ),
			'body'        => self::contains_phrase( $content, $focus_keyword ),
		];
		$passed = count( array_filter( $checks ) );

		return [
			'focus'       => $focus_keyword,
			'passed'      => $passed,
			'total'       => count( $checks ),
			'occurrences' => self::count_occurrences( $content, $focus_keyword ),
			'checks'      => $checks,
		];
	}

	private static function contains_phrase( string $haystack, string $needle ): bool {
		if ( '' === trim( $needle ) ) {
			return false;
		}
		if ( function_exists( 'mb_stripos' ) ) {
			return false !== mb_stripos( $haystack, $needle, 0, 'UTF-8' );
		}
		return false !== stripos( $haystack, $needle );
	}

	private static function count_occurrences( string $haystack, string $needle ): int {
		if ( '' === trim( $needle ) ) {
			return 0;
		}
		if ( function_exists( 'mb_strtolower' ) ) {
			$haystack = mb_strtolower( $haystack, 'UTF-8' );
			$needle   = mb_strtolower( $needle, 'UTF-8' );
		} else {
			$haystack = strtolower( $haystack );
			$needle   = strtolower( $needle );
		}
		return substr_count( $haystack, $needle );
	}

	private static function slice( string $value, int $start, int $length ): string {
		if ( function_exists( 'mb_substr' ) ) {
			return (string) mb_substr( $value, $start, $length, 'UTF-8' );
		}
		return substr( $value, $start, $length );
	}
}
