<?php
declare(strict_types=1);
/**
 * Bricks adapter for the builder-neutral HTML sitemap renderer.
 *
 * @package Core_Blueprint_SEO
 */

namespace CB\SEO\Integration\Builders\Bricks\Elements;

use CB\SEO\Frontend\Sitemap as SitemapRenderer;
use CB\SEO\Integration\Builders\Bricks\ElementRegistry;
use CB\SEO\State;

defined( 'ABSPATH' ) || exit;

final class Sitemap extends Element {
	public $category = ElementRegistry::CATEGORY;
	public $name = 'cb-seo-sitemap';
	public $icon = 'ti-map-alt';

	public function get_label(): string {
		return esc_html__( 'SEO Sitemap', 'core-blueprint-seo' );
	}

	public function set_control_groups(): void {
		foreach ( [
			'content' => esc_html__( 'Sitemap', 'core-blueprint-seo' ),
			'layout'  => esc_html__( 'Layout', 'core-blueprint-seo' ),
			'lists'   => esc_html__( 'Lists', 'core-blueprint-seo' ),
			'heading' => esc_html__( 'Section headings', 'core-blueprint-seo' ),
			'links'   => esc_html__( 'Links', 'core-blueprint-seo' ),
		] as $key => $title ) {
			$this->control_groups[ $key ] = [
				'title' => $title,
				'tab'   => 'content',
			];
		}
	}

	public function set_controls(): void {
		$this->controls['postTypes'] = [
			'group' => 'content',
			'tab'         => 'content',
			'label'       => esc_html__( 'Post types', 'core-blueprint-seo' ),
			'type'        => 'select',
			'options'     => SitemapRenderer::post_type_options(),
			'multiple'    => true,
			'placeholder' => esc_html__( 'All eligible public post types', 'core-blueprint-seo' ),
			'description' => esc_html__( 'Leave empty to use all public post types allowed by the Core Blueprint SEO sitemap policy.', 'core-blueprint-seo' ),
		];
		$this->controls['taxonomies'] = [
			'group' => 'content',
			'tab'         => 'content',
			'label'       => esc_html__( 'Taxonomies', 'core-blueprint-seo' ),
			'type'        => 'select',
			'options'     => SitemapRenderer::taxonomy_options(),
			'multiple'    => true,
			'placeholder' => esc_html__( 'None', 'core-blueprint-seo' ),
			'description' => esc_html__( 'Optional taxonomy sections. Only public taxonomies allowed by the SEO sitemap policy are rendered.', 'core-blueprint-seo' ),
		];
		$this->controls['showHeadings'] = [
			'group' => 'content',
			'tab'     => 'content',
			'label'   => esc_html__( 'Show section headings', 'core-blueprint-seo' ),
			'type'    => 'checkbox',
			'default' => true,
		];
		$this->controls['hierarchy'] = [
			'group' => 'content',
			'tab'         => 'content',
			'label'       => esc_html__( 'Preserve hierarchy', 'core-blueprint-seo' ),
			'type'        => 'checkbox',
			'default'     => true,
			'description' => esc_html__( 'Nest hierarchical pages and taxonomy terms below their visible parent.', 'core-blueprint-seo' ),
		];
		$this->controls['orderby'] = [
			'group' => 'content',
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
			'group' => 'content',
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
			'group' => 'layout',
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

		$this->controls['sectionGap'] = [
			'group' => 'layout',
			'tab'   => 'content',
			'label' => esc_html__( 'Section gap', 'core-blueprint-seo' ),
			'type'  => 'number',
			'units' => true,
			'css'   => [ [ 'property' => 'gap', 'selector' => '.cb-seo-sitemap' ] ],
		];
		$this->controls['listStyleType'] = [
			'group' => 'lists',
			'tab'     => 'content',
			'label'   => esc_html__( 'List marker', 'core-blueprint-seo' ),
			'type'    => 'select',
			'options' => [
				'revert'      => esc_html__( 'Browser default', 'core-blueprint-seo' ),
				'none'        => esc_html__( 'None', 'core-blueprint-seo' ),
				'disc'        => esc_html__( 'Disc', 'core-blueprint-seo' ),
				'circle'      => esc_html__( 'Circle', 'core-blueprint-seo' ),
				'square'      => esc_html__( 'Square', 'core-blueprint-seo' ),
				'decimal'     => esc_html__( 'Decimal', 'core-blueprint-seo' ),
				'lower-alpha' => esc_html__( 'Lower alpha', 'core-blueprint-seo' ),
				'upper-alpha' => esc_html__( 'Upper alpha', 'core-blueprint-seo' ),
				'lower-roman' => esc_html__( 'Lower roman', 'core-blueprint-seo' ),
				'upper-roman' => esc_html__( 'Upper roman', 'core-blueprint-seo' ),
			],
			'default' => 'none',
			'css'     => [ [ 'property' => 'list-style-type', 'selector' => '.cb-seo-sitemap__list' ] ],
		];
		$this->controls['listMargin'] = [
			'group' => 'lists',
			'tab'   => 'content',
			'label' => esc_html__( 'List margin', 'core-blueprint-seo' ),
			'type'  => 'dimensions',
			'css'   => [ [ 'property' => 'margin', 'selector' => '.cb-seo-sitemap__list' ] ],
		];
		$this->controls['listPadding'] = [
			'group' => 'lists',
			'tab'   => 'content',
			'label' => esc_html__( 'List padding', 'core-blueprint-seo' ),
			'type'  => 'dimensions',
			'css'   => [ [ 'property' => 'padding', 'selector' => '.cb-seo-sitemap__list' ] ],
		];
		$this->controls['itemTypography'] = [
			'group' => 'lists',
			'tab'   => 'content',
			'label' => esc_html__( 'Item typography', 'core-blueprint-seo' ),
			'type'  => 'typography',
			'css'   => [ [ 'property' => 'typography', 'selector' => '.cb-seo-sitemap__item' ] ],
		];
		$this->controls['headingTypography'] = [
			'group' => 'heading',
			'tab'   => 'content',
			'label' => esc_html__( 'Section headings', 'core-blueprint-seo' ),
			'type'  => 'typography',
			'css'      => [ [ 'property' => 'typography', 'selector' => '.cb-seo-sitemap__heading' ] ],
			'required' => [ 'showHeadings', '=', true ],
		];
		$this->controls['linkTypography'] = [
			'group' => 'links',
			'tab'   => 'content',
			'label' => esc_html__( 'Links', 'core-blueprint-seo' ),
			'type'  => 'typography',
			'css'   => [ [ 'property' => 'typography', 'selector' => '.cb-seo-sitemap__link' ] ],
		];
		$this->controls['linkHoverColor'] = [
			'group' => 'links',
			'tab'   => 'content',
			'label' => esc_html__( 'Link hover color', 'core-blueprint-seo' ),
			'type'  => 'color',
			'css'   => [ [ 'property' => 'color', 'selector' => '.cb-seo-sitemap__link:hover' ] ],
		];
		$this->controls['linkFocusColor'] = [
			'group' => 'links',
			'tab'   => 'content',
			'label' => esc_html__( 'Link focus color', 'core-blueprint-seo' ),
			'type'  => 'color',
			'css'   => [ [ 'property' => 'color', 'selector' => '.cb-seo-sitemap__link:focus-visible' ] ],
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

}
