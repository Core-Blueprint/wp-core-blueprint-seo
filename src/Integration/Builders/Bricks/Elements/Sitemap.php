<?php
declare(strict_types=1);
/**
 * Bricks adapter for the builder-neutral HTML sitemap renderer.
 *
 * @package Core_Blueprint_SEO
 */

namespace CB\SEO\Integration\Builders\Bricks\Elements;

use CB\SEO\Frontend\Sitemap as SitemapRenderer;
use CB\SEO\State;
use WP_Post_Type;
use WP_Taxonomy;

defined( 'ABSPATH' ) || exit;

final class Sitemap extends Element {
	public $name = 'cb-seo-sitemap';
	public $icon = 'ti-map-alt';

	public function get_label(): string {
		return esc_html__( 'SEO Sitemap', 'core-blueprint-seo' );
	}

	public function set_controls(): void {
		$this->controls['postTypes'] = [
			'tab'         => 'content',
			'label'       => esc_html__( 'Post types', 'core-blueprint-seo' ),
			'type'        => 'select',
			'options'     => self::post_type_options(),
			'multiple'    => true,
			'placeholder' => esc_html__( 'All eligible public post types', 'core-blueprint-seo' ),
			'description' => esc_html__( 'Leave empty to use all public post types allowed by the Core Blueprint SEO sitemap policy.', 'core-blueprint-seo' ),
		];
		$this->controls['taxonomies'] = [
			'tab'         => 'content',
			'label'       => esc_html__( 'Taxonomies', 'core-blueprint-seo' ),
			'type'        => 'select',
			'options'     => self::taxonomy_options(),
			'multiple'    => true,
			'placeholder' => esc_html__( 'None', 'core-blueprint-seo' ),
			'description' => esc_html__( 'Optional taxonomy sections. Only public taxonomies allowed by the SEO sitemap policy are rendered.', 'core-blueprint-seo' ),
		];
		$this->controls['showHeadings'] = [
			'tab'     => 'content',
			'label'   => esc_html__( 'Show section headings', 'core-blueprint-seo' ),
			'type'    => 'checkbox',
			'default' => true,
		];
		$this->controls['hierarchy'] = [
			'tab'         => 'content',
			'label'       => esc_html__( 'Preserve hierarchy', 'core-blueprint-seo' ),
			'type'        => 'checkbox',
			'default'     => true,
			'description' => esc_html__( 'Nest hierarchical pages and taxonomy terms below their visible parent.', 'core-blueprint-seo' ),
		];
		$this->controls['orderby'] = [
			'tab'     => 'content',
			'label'   => esc_html__( 'Order by', 'core-blueprint-seo' ),
			'type'    => 'select',
			'options' => [
				'title'      => esc_html__( 'Title', 'core-blueprint-seo' ),
				'date'       => esc_html__( 'Date', 'core-blueprint-seo' ),
				'menu_order' => esc_html__( 'Menu order', 'core-blueprint-seo' ),
			],
			'default' => 'title',
		];
		$this->controls['order'] = [
			'tab'     => 'content',
			'label'   => esc_html__( 'Order', 'core-blueprint-seo' ),
			'type'    => 'select',
			'options' => [
				'ASC'  => esc_html__( 'Ascending', 'core-blueprint-seo' ),
				'DESC' => esc_html__( 'Descending', 'core-blueprint-seo' ),
			],
			'default' => 'ASC',
		];
		$this->controls['columns'] = [
			'tab'     => 'content',
			'label'   => esc_html__( 'Columns', 'core-blueprint-seo' ),
			'type'    => 'select',
			'options' => [
				'1' => '1',
				'2' => '2',
				'3' => '3',
				'4' => '4',
			],
			'default' => '2',
		];

		$this->controls['headingTypography'] = [
			'tab'   => 'style',
			'group' => 'typography',
			'label' => esc_html__( 'Section headings', 'core-blueprint-seo' ),
			'type'  => 'typography',
			'css'   => [ [ 'property' => 'typography', 'selector' => '.cb-seo-sitemap__heading' ] ],
		];
		$this->controls['linkTypography'] = [
			'tab'   => 'style',
			'group' => 'typography',
			'label' => esc_html__( 'Links', 'core-blueprint-seo' ),
			'type'  => 'typography',
			'css'   => [ [ 'property' => 'typography', 'selector' => '.cb-seo-sitemap__link' ] ],
		];
	}

	public function render(): void {
		if ( ! State::is_enabled() ) {
			$this->render_component( '', esc_html__( 'Core Blueprint SEO is disabled.', 'core-blueprint-seo' ) );
			return;
		}

		$this->render_component(
			SitemapRenderer::render( [
				'post_types'    => $this->settings['postTypes'] ?? [],
				'taxonomies'    => $this->settings['taxonomies'] ?? [],
				'show_headings' => $this->settings['showHeadings'] ?? true,
				'hierarchy'     => $this->settings['hierarchy'] ?? true,
				'orderby'       => $this->settings['orderby'] ?? 'title',
				'order'         => $this->settings['order'] ?? 'ASC',
				'columns'       => $this->settings['columns'] ?? 2,
			] ),
			esc_html__( 'No indexable sitemap content matches these settings.', 'core-blueprint-seo' )
		);
	}

	/** @return array<string,string> */
	private static function post_type_options(): array {
		$objects = get_post_types( [ 'public' => true ], 'objects' );
		$options = [];
		if ( ! is_array( $objects ) ) {
			return $options;
		}
		foreach ( $objects as $object ) {
			if ( ! $object instanceof WP_Post_Type || 'attachment' === $object->name ) {
				continue;
			}
			$options[ $object->name ] = isset( $object->labels->name ) ? (string) $object->labels->name : $object->name;
		}
		return $options;
	}

	/** @return array<string,string> */
	private static function taxonomy_options(): array {
		$objects = get_taxonomies( [ 'public' => true ], 'objects' );
		$options = [];
		if ( ! is_array( $objects ) ) {
			return $options;
		}
		foreach ( $objects as $object ) {
			if ( ! $object instanceof WP_Taxonomy ) {
				continue;
			}
			$options[ $object->name ] = isset( $object->labels->name ) ? (string) $object->labels->name : $object->name;
		}
		return $options;
	}
}
