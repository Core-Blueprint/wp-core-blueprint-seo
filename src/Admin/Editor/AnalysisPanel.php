<?php
declare(strict_types=1);
/**
 * Native WordPress editor UI for rendered-page analysis.
 *
 * @package Core_Blueprint_SEO
 */

namespace CB\SEO\Admin\Editor;

use CB\SEO\Analysis\Repository;
use WP_Post;

defined( 'ABSPATH' ) || exit;

final class AnalysisPanel {
	public static function render( WP_Post $post ): void {
		$focus     = Repository::focus_keyword( (int) $post->ID );
		$snapshot  = Repository::snapshot( (int) $post->ID );
		$published = 'publish' === $post->post_status;
		?>
		<section class="cb-seo-analysis" data-cb-seo-analysis data-post-id="<?php echo esc_attr( (string) $post->ID ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'cb_seo_analyze_post_' . $post->ID ) ); ?>" data-error-label="<?php esc_attr_e( 'Analysis failed.', 'core-blueprint-seo' ); ?>">
			<p class="description"><?php esc_html_e( 'Analyze the rendered public URL instead of editor or page-builder storage. The request is made as an anonymous visitor. Save or update the page first if you want recent changes included.', 'core-blueprint-seo' ); ?></p>
			<p>
				<label for="cb-seo-focus-keyword"><strong><?php esc_html_e( 'Focus keyword', 'core-blueprint-seo' ); ?></strong></label><br>
				<input type="text" class="widefat" id="cb-seo-focus-keyword" name="cb_seo_focus_keyword" value="<?php echo esc_attr( $focus ); ?>" placeholder="<?php esc_attr_e( 'Example: wordpress maintenance', 'core-blueprint-seo' ); ?>" data-cb-seo-focus-keyword>
			</p>
			<p class="description"><?php esc_html_e( 'The keyword is used for guidance only. Core Blueprint does not treat keyword density as a search-engine ranking formula.', 'core-blueprint-seo' ); ?></p>
			<p>
				<button type="button" class="button button-secondary" data-cb-seo-analyze-button data-analyzing-label="<?php esc_attr_e( 'Analyzing…', 'core-blueprint-seo' ); ?>" <?php disabled( ! $published ); ?>><?php esc_html_e( 'Analyze live page', 'core-blueprint-seo' ); ?></button>
			</p>
			<?php if ( ! $published ) : ?>
				<p class="description"><?php esc_html_e( 'Publish this content before running the live analysis. Draft analysis will be added separately so the public renderer remains the source of truth.', 'core-blueprint-seo' ); ?></p>
			<?php endif; ?>
			<div class="cb-seo-analysis__output" data-cb-seo-analysis-output aria-live="polite">
				<?php
				if ( is_array( $snapshot ) ) {
					self::render_results( $post, $snapshot );
				} elseif ( $published ) {
					?><p class="description"><?php esc_html_e( 'No live analysis has been run yet.', 'core-blueprint-seo' ); ?></p><?php
				}
				?>
			</div>
		</section>
		<?php
	}

	/** @param array<string,mixed> $snapshot */
	public static function results_html( WP_Post $post, array $snapshot ): string {
		ob_start();
		self::render_results( $post, $snapshot );
		return (string) ob_get_clean();
	}

	/** @param array<string,mixed> $snapshot */
	private static function render_results( WP_Post $post, array $snapshot ): void {
		$result = isset( $snapshot['result'] ) && is_array( $snapshot['result'] ) ? $snapshot['result'] : [];
		if ( empty( $result ) ) {
			return;
		}

		$keyword       = isset( $result['keyword'] ) && is_array( $result['keyword'] ) ? $result['keyword'] : [];
		$analyzed_at   = isset( $snapshot['analyzed_at'] ) ? absint( $snapshot['analyzed_at'] ) : 0;
		$stale         = isset( $snapshot['post_modified_gmt'] ) && (string) $snapshot['post_modified_gmt'] !== (string) $post->post_modified_gmt;
		$needs         = [];
		$optimise      = [];
		$good          = [];
		$not_evaluated = [];

		self::classify_technical_results( $result, $needs, $good );
		$keyword_summary = self::classify_keyword_results( $result, $keyword, $optimise, $good, $not_evaluated );
		?>
		<div class="cb-seo-analysis__meta">
			<strong><?php esc_html_e( 'Analysis source:', 'core-blueprint-seo' ); ?></strong>
			<?php esc_html_e( 'Rendered frontend', 'core-blueprint-seo' ); ?>
			<?php if ( $analyzed_at > 0 ) : ?>
				<span class="cb-seo-analysis__time">· <?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $analyzed_at ) ); ?></span>
			<?php endif; ?>
			<?php if ( $stale ) : ?>
				<span class="cb-seo-analysis__stale"><?php esc_html_e( 'Content changed since this analysis.', 'core-blueprint-seo' ); ?></span>
			<?php endif; ?>
		</div>

		<?php if ( '' !== $keyword_summary ) : ?>
			<div class="cb-seo-analysis__keyword-summary">
				<strong><?php echo esc_html( $keyword_summary ); ?></strong>
				<p class="description"><?php echo esc_html( sprintf( __( 'Exact phrase occurrences in visible content: %d', 'core-blueprint-seo' ), (int) ( $keyword['occurrences'] ?? 0 ) ) ); ?></p>
				<?php foreach ( $not_evaluated as $message ) : ?>
					<p class="description cb-seo-analysis__not-evaluated"><?php echo esc_html( $message ); ?></p>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<?php self::render_group( __( 'Needs attention', 'core-blueprint-seo' ), $needs, 'needs-attention' ); ?>
		<?php self::render_group( __( 'Optimisation', 'core-blueprint-seo' ), $optimise, 'is-optimisation' ); ?>
		<?php self::render_group( __( 'Good', 'core-blueprint-seo' ), $good, 'is-good' ); ?>

		<div class="cb-seo-analysis__facts" aria-label="<?php esc_attr_e( 'Content overview', 'core-blueprint-seo' ); ?>">
			<span><?php echo esc_html( sprintf( __( 'Visible words: %d', 'core-blueprint-seo' ), (int) ( $result['word_count'] ?? 0 ) ) ); ?></span>
			<span><?php echo esc_html( sprintf( __( 'Internal links: %d', 'core-blueprint-seo' ), (int) ( $result['internal_links'] ?? 0 ) ) ); ?></span>
			<span><?php echo esc_html( sprintf( __( 'External links: %d', 'core-blueprint-seo' ), (int) ( $result['external_links'] ?? 0 ) ) ); ?></span>
			<span><?php echo esc_html( sprintf( __( 'Images: %d', 'core-blueprint-seo' ), (int) ( $result['image_count'] ?? 0 ) ) ); ?></span>
		</div>
		<?php
	}

	/**
	 * @param array<string,mixed> $result
	 * @param array<int,string>   $needs
	 * @param array<int,string>   $good
	 */
	private static function classify_technical_results( array $result, array &$needs, array &$good ): void {
		$status = (int) ( $result['http_status'] ?? 0 );
		if ( 200 === $status ) {
			$good[] = __( 'HTTP status is 200', 'core-blueprint-seo' );
		} else {
			$needs[] = sprintf( __( 'HTTP status is %d', 'core-blueprint-seo' ), $status );
		}

		$good_or_needs = static function ( bool $ok, string $good_label, string $bad_label ) use ( &$good, &$needs ): void {
			if ( $ok ) {
				$good[] = $good_label;
			} else {
				$needs[] = $bad_label;
			}
		};

		$good_or_needs( '' !== (string) ( $result['title'] ?? '' ), __( 'Document title is present', 'core-blueprint-seo' ), __( 'Document title is missing', 'core-blueprint-seo' ) );
		$good_or_needs( '' !== (string) ( $result['description'] ?? '' ), __( 'Meta description is present', 'core-blueprint-seo' ), __( 'Meta description is missing', 'core-blueprint-seo' ) );
		$good_or_needs( ! empty( $result['indexable'] ), __( 'Page is indexable', 'core-blueprint-seo' ), __( 'Page is marked noindex', 'core-blueprint-seo' ) );
		$good_or_needs( '' !== (string) ( $result['canonical'] ?? '' ), __( 'Canonical URL is present', 'core-blueprint-seo' ), __( 'Canonical URL is missing', 'core-blueprint-seo' ) );

		$h1_count = (int) ( $result['h1_count'] ?? 0 );
		if ( 1 === $h1_count ) {
			$good[] = __( 'Exactly one H1 heading is present', 'core-blueprint-seo' );
		} else {
			$needs[] = sprintf( __( 'H1 headings found: %d', 'core-blueprint-seo' ), $h1_count );
		}

		$good_or_needs( ! empty( $result['heading_hierarchy_valid'] ), __( 'Heading hierarchy has no skipped levels', 'core-blueprint-seo' ), __( 'Heading hierarchy skips one or more levels', 'core-blueprint-seo' ) );

		$image_count = (int) ( $result['image_count'] ?? 0 );
		$missing_alt = (int) ( $result['images_missing_alt'] ?? 0 );
		if ( $image_count > 0 && 0 === $missing_alt ) {
			$good[] = 1 === $image_count
				? __( 'The image has an alt attribute', 'core-blueprint-seo' )
				: sprintf( __( 'All %d images have alt attributes', 'core-blueprint-seo' ), $image_count );
		} elseif ( $missing_alt > 0 ) {
			$needs[] = 1 === $missing_alt
				? __( '1 image is missing an alt attribute', 'core-blueprint-seo' )
				: sprintf( __( '%d images are missing an alt attribute', 'core-blueprint-seo' ), $missing_alt );
		}
	}

	/**
	 * @param array<string,mixed> $result
	 * @param array<string,mixed> $keyword
	 * @param array<int,string>   $optimise
	 * @param array<int,string>   $good
	 * @param array<int,string>   $not_evaluated
	 */
	private static function classify_keyword_results( array $result, array $keyword, array &$optimise, array &$good, array &$not_evaluated ): string {
		if ( empty( $keyword['focus'] ) ) {
			return '';
		}

		$checks = isset( $keyword['checks'] ) && is_array( $keyword['checks'] ) ? $keyword['checks'] : [];
		$labels = [
			'title'        => [ __( 'Focus keyword appears in the document title', 'core-blueprint-seo' ), __( 'Consider using the focus keyword in the document title', 'core-blueprint-seo' ) ],
			'description'  => [ __( 'Focus keyword appears in the meta description', 'core-blueprint-seo' ), __( 'Consider using the focus keyword in the meta description', 'core-blueprint-seo' ) ],
			'url'          => [ __( 'Focus keyword appears in the URL', 'core-blueprint-seo' ), __( 'The focus keyword does not appear in the URL', 'core-blueprint-seo' ) ],
			'h1'           => [ __( 'Focus keyword appears in an H1 heading', 'core-blueprint-seo' ), __( 'Consider using the focus keyword in the H1 heading', 'core-blueprint-seo' ) ],
			'introduction' => [ __( 'Focus keyword appears near the start of the visible content', 'core-blueprint-seo' ), __( 'Consider mentioning the focus keyword near the start of the visible content', 'core-blueprint-seo' ) ],
			'subheading'   => [ __( 'Focus keyword appears in a subheading', 'core-blueprint-seo' ), __( 'Consider using the focus keyword in a subheading', 'core-blueprint-seo' ) ],
			'body'         => [ __( 'Focus keyword appears in the visible page content', 'core-blueprint-seo' ), __( 'The focus keyword does not appear in the visible page content', 'core-blueprint-seo' ) ],
		];

		$evaluated = 0;
		$passed    = 0;
		foreach ( $labels as $key => $labels_for_state ) {
			if ( 'description' === $key && '' === (string) ( $result['description'] ?? '' ) ) {
				$not_evaluated[] = __( 'Meta description keyword check was not evaluated because no meta description is published.', 'core-blueprint-seo' );
				continue;
			}
			if ( 'h1' === $key && 0 === (int) ( $result['h1_count'] ?? 0 ) ) {
				$not_evaluated[] = __( 'H1 keyword check was not evaluated because no H1 heading is published.', 'core-blueprint-seo' );
				continue;
			}

			++$evaluated;
			if ( ! empty( $checks[ $key ] ) ) {
				++$passed;
				$good[] = $labels_for_state[0];
			} else {
				$optimise[] = $labels_for_state[1];
			}
		}

		return sprintf( __( 'Focus keyword checks: %1$d/%2$d', 'core-blueprint-seo' ), $passed, $evaluated );
	}

	/** @param array<int,string> $items */
	private static function render_group( string $title, array $items, string $class_name ): void {
		if ( empty( $items ) ) {
			return;
		}
		?>
		<div class="cb-seo-analysis__group <?php echo esc_attr( $class_name ); ?>">
			<h4><?php echo esc_html( $title ); ?> <span class="cb-seo-analysis__count"><?php echo esc_html( (string) count( $items ) ); ?></span></h4>
			<ul class="cb-seo-analysis-list">
				<?php foreach ( $items as $label ) : ?>
					<?php self::render_item( $class_name, $label ); ?>
				<?php endforeach; ?>
			</ul>
		</div>
		<?php
	}

	private static function render_item( string $class_name, string $label ): void {
		$icon = 'is-good' === $class_name ? '✓' : ( 'is-optimisation' === $class_name ? '○' : '!' );
		?>
		<li class="cb-seo-analysis-list__item <?php echo esc_attr( $class_name ); ?>">
			<span class="cb-seo-analysis-list__icon" aria-hidden="true"><?php echo esc_html( $icon ); ?></span>
			<span><?php echo esc_html( $label ); ?></span>
		</li>
		<?php
	}
}
