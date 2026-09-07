<?php
declare(strict_types=1);
/**
 * Shared base for optional Core Blueprint SEO Bricks elements.
 *
 * @package Core_Blueprint_SEO
 */

namespace CB\SEO\Integration\Builders\Bricks\Elements;

defined( 'ABSPATH' ) || exit;

abstract class Element extends \Bricks\Element {
	public $category = 'core-blueprint-seo';

	/** @return string[] */
	public function get_keywords(): array {
		return [ 'core blueprint', 'seo', 'sitemap' ];
	}

	protected function render_component( string $html, string $empty_message = '' ): void {
		if ( '' === trim( $html ) ) {
			if ( function_exists( 'bricks_is_builder' ) && bricks_is_builder() ) {
				$this->render_element_placeholder( [
					'icon-class' => (string) $this->icon,
					'text'       => '' !== $empty_message
						? $empty_message
						: esc_html__( 'No sitemap content is available for the current settings.', 'core-blueprint-seo' ),
				] );
			}
			return;
		}

		$this->set_attribute( '_root', 'class', [ 'cb-seo-element', 'cb-seo-element--sitemap' ] );
		echo '<div ' . $this->render_attributes( '_root' ) . '>' . $html . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- trusted builder-neutral SEO renderer output.
	}
}
