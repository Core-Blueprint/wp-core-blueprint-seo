<?php
declare(strict_types=1);
/**
 * Builder-agnostic inspection of the final rendered HTML document.
 *
 * Uses WordPress' native HTML Tag Processor for attribute inspection and keeps
 * text extraction independent from page-builder storage formats.
 *
 * @package Core_Blueprint_SEO
 */

namespace CB\SEO\Analysis;

use WP_Error;
use WP_HTML_Tag_Processor;

defined( 'ABSPATH' ) || exit;

final class HtmlDocument {
	private string $html;
	private string $content_html;
	private string $url;

	/** @return self|WP_Error */
	public static function from_html( string $html, string $url ) {
		if ( ! class_exists( WP_HTML_Tag_Processor::class ) ) {
			return new WP_Error( 'cb_seo_analysis_html_api_missing', __( 'The WordPress HTML API is required for rendered-page analysis.', 'core-blueprint-seo' ) );
		}
		if ( '' === trim( $html ) ) {
			return new WP_Error( 'cb_seo_analysis_invalid_html', __( 'The live document could not be parsed as HTML.', 'core-blueprint-seo' ) );
		}
		return new self( $html, $url );
	}

	private function __construct( string $html, string $url ) {
		$this->html         = $html;
		$this->content_html = self::extract_content_region( $html );
		$this->url          = $url;
	}

	public function title(): string {
		if ( preg_match( '~<title\b[^>]*>(.*?)</title\s*>~is', $this->html, $match ) ) {
			return self::text_from_html( (string) $match[1] );
		}
		return '';
	}

	public function meta_description(): string {
		return $this->meta_content( 'name', 'description' );
	}

	public function robots(): string {
		return $this->meta_content( 'name', 'robots' );
	}

	public function canonical(): string {
		$processor = new WP_HTML_Tag_Processor( $this->html );
		while ( $processor->next_tag( 'LINK' ) ) {
			$rel = $processor->get_attribute( 'rel' );
			if ( ! is_string( $rel ) ) {
				continue;
			}
			$rels = preg_split( '/\s+/', strtolower( trim( $rel ) ) ) ?: [];
			if ( ! in_array( 'canonical', $rels, true ) ) {
				continue;
			}
			$href = $processor->get_attribute( 'href' );
			return is_string( $href ) ? trim( $href ) : '';
		}
		return '';
	}

	public function has_open_graph_title(): bool {
		return '' !== $this->meta_content( 'property', 'og:title' );
	}

	public function has_open_graph_description(): bool {
		return '' !== $this->meta_content( 'property', 'og:description' );
	}

	/** @return string[] */
	public function h1_texts(): array {
		return $this->heading_texts( 1 );
	}

	/** @return string[] */
	public function subheading_texts(): array {
		$texts = [];
		foreach ( [ 2, 3, 4, 5, 6 ] as $level ) {
			$texts = array_merge( $texts, $this->heading_texts( $level ) );
		}
		return $texts;
	}

	public function heading_hierarchy_valid(): bool {
		if ( ! preg_match_all( '~<h([1-6])\b[^>]*>~i', $this->content_html, $matches ) ) {
			return true;
		}
		$previous = null;
		foreach ( $matches[1] as $value ) {
			$level = (int) $value;
			if ( null !== $previous && $level > $previous + 1 ) {
				return false;
			}
			$previous = $level;
		}
		return true;
	}

	public function content_text(): string {
		$html = preg_replace( '~<(script|style|noscript|template|svg)\b[^>]*>.*?</\1\s*>~is', ' ', $this->content_html ) ?? $this->content_html;
		return self::text_from_html( $html );
	}

	public function word_count(): int {
		$text = $this->content_text();
		if ( '' === $text ) {
			return 0;
		}
		$words = preg_split( '/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY );
		return is_array( $words ) ? count( $words ) : 0;
	}

	/** @return array{total:int,missing_alt:int} */
	public function image_stats(): array {
		$total     = 0;
		$missing   = 0;
		$processor = new WP_HTML_Tag_Processor( $this->content_html );
		while ( $processor->next_tag( 'IMG' ) ) {
			++$total;
			if ( null === $processor->get_attribute( 'alt' ) ) {
				++$missing;
			}
		}
		return [ 'total' => $total, 'missing_alt' => $missing ];
	}

	/** @return array{internal:int,external:int} */
	public function link_stats(): array {
		$internal  = 0;
		$external  = 0;
		$site_host = strtolower( (string) wp_parse_url( $this->url, PHP_URL_HOST ) );
		$processor = new WP_HTML_Tag_Processor( $this->content_html );
		while ( $processor->next_tag( 'A' ) ) {
			$href = $processor->get_attribute( 'href' );
			if ( ! is_string( $href ) ) {
				continue;
			}
			$href = trim( $href );
			if ( '' === $href || '#' === $href[0] || preg_match( '#^(?:mailto|tel|javascript|data):#i', $href ) ) {
				continue;
			}
			$host = wp_parse_url( $href, PHP_URL_HOST );
			if ( ! is_string( $host ) || '' === $host || strtolower( $host ) === $site_host ) {
				++$internal;
			} else {
				++$external;
			}
		}
		return [ 'internal' => $internal, 'external' => $external ];
	}

	/** @return string[] */
	private function heading_texts( int $level ): array {
		$pattern = '~<h' . $level . '\b[^>]*>(.*?)</h' . $level . '\s*>~is';
		if ( ! preg_match_all( $pattern, $this->content_html, $matches ) ) {
			return [];
		}
		$texts = [];
		foreach ( $matches[1] as $html ) {
			$text = self::text_from_html( (string) $html );
			if ( '' !== $text ) {
				$texts[] = $text;
			}
		}
		return $texts;
	}

	private function meta_content( string $attribute, string $needle ): string {
		$processor = new WP_HTML_Tag_Processor( $this->html );
		while ( $processor->next_tag( 'META' ) ) {
			$value = $processor->get_attribute( $attribute );
			if ( ! is_string( $value ) || strtolower( trim( $value ) ) !== $needle ) {
				continue;
			}
			$content = $processor->get_attribute( 'content' );
			return is_string( $content ) ? trim( $content ) : '';
		}
		return '';
	}

	private static function extract_content_region( string $html ): string {
		foreach ( [ 'main', 'article', 'body' ] as $tag ) {
			if ( preg_match( '~<' . $tag . '\b[^>]*>(.*?)</' . $tag . '\s*>~is', $html, $match ) ) {
				return (string) $match[1];
			}
		}
		return $html;
	}

	private static function text_from_html( string $html ): string {
		$text = wp_strip_all_tags( $html, true );
		$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$text = preg_replace( '/\s+/u', ' ', $text ) ?? $text;
		return trim( $text );
	}
}
