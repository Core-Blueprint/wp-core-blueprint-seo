<?php
declare(strict_types=1);

$root     = dirname( __DIR__ );
$registry = (string) file_get_contents( $root . '/src/Integration/Builders/Bricks/ElementRegistry.php' );
$base     = (string) file_get_contents( $root . '/src/Integration/Builders/Bricks/Elements/Element.php' );
$element  = (string) file_get_contents( $root . '/src/Integration/Builders/Bricks/Elements/Sitemap.php' );
$renderer = (string) file_get_contents( $root . '/src/Frontend/Sitemap.php' );

function cb_seo_bricks_assert( bool $condition, string $message ): void {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: $message\n" );
		exit( 1 );
	}
}

function cb_seo_control_block( string $content, string $name ): string {
	$marker = "\t\t\$this->controls['" . $name . "'] = [";
	$start  = strpos( $content, $marker );
	cb_seo_bricks_assert( false !== $start, 'Missing Bricks control: ' . $name );
	$next   = strpos( $content, "\n\t\t\$this->controls[", (int) $start + strlen( $marker ) );
	$render = strpos( $content, "\n\t}\n\n\tpublic function render", (int) $start + strlen( $marker ) );
	$end    = false !== $next ? $next : $render;
	cb_seo_bricks_assert( false !== $end, 'Could not delimit Bricks control: ' . $name );
	return substr( $content, (int) $start, (int) $end - (int) $start );
}

cb_seo_bricks_assert( str_contains( $registry, "public const CATEGORY = 'core-blueprint-seo';" ), 'SEO must use one canonical Bricks category id.' );
cb_seo_bricks_assert( str_contains( $registry, "bricks/builder/i18n" ), 'SEO must expose a translated Bricks category label.' );
cb_seo_bricks_assert( str_contains( $base, 'public $category = ElementRegistry::CATEGORY;' ), 'SEO element base must consume the canonical category id.' );

cb_seo_bricks_assert( ! str_contains( $element, "'tab'   => 'style'" ) && ! str_contains( $element, "'tab'      => 'style'" ), 'SEO-specific controls must remain under Content.' );
cb_seo_bricks_assert( ! preg_match( "/'type'\s*=>\s*'slider'/", $element ), 'SEO Sitemap must not use sliders for CSS lengths.' );
cb_seo_bricks_assert( str_contains( $element, 'SitemapRenderer::post_type_options()' ) && str_contains( $element, 'SitemapRenderer::taxonomy_options()' ), 'SEO Sitemap option controls must delegate to the builder-neutral provider.' );
cb_seo_bricks_assert( ! str_contains( $element, 'get_post_types(' ) && ! str_contains( $element, 'get_taxonomies(' ), 'SEO Bricks adapter must not discover content types directly.' );

$section_gap = cb_seo_control_block( $element, 'sectionGap' );
cb_seo_bricks_assert( (bool) preg_match( "/'type'\s*=>\s*'number'/", $section_gap ), 'Section gap must use native Bricks number control.' );
cb_seo_bricks_assert( (bool) preg_match( "/'units'\s*=>\s*true/", $section_gap ), 'Section gap must expose native Bricks units.' );

$list_marker = cb_seo_control_block( $element, 'listStyleType' );
cb_seo_bricks_assert( str_contains( $list_marker, "'property' => 'list-style-type'" ), 'SEO Sitemap semantic lists must expose a List marker control.' );
cb_seo_bricks_assert( str_contains( $list_marker, "Browser default" ), 'SEO Sitemap List marker must include Browser default.' );

$heading = cb_seo_control_block( $element, 'headingTypography' );
cb_seo_bricks_assert( str_contains( $heading, "'showHeadings'" ) && str_contains( $heading, 'true' ), 'Heading styling must only appear when headings can render.' );

foreach ( [ "'listMargin'", "'listPadding'", "'linkHoverColor'", "'linkFocusColor'" ] as $needle ) {
	cb_seo_bricks_assert( str_contains( $element, $needle ), 'SEO Sitemap Golden control contract missing ' . $needle . '.' );
}

preg_match_all( "/'selector'\s*=>\s*'([^']+)'/", $element, $selectors );
foreach ( $selectors[1] ?? [] as $selector ) {
	preg_match_all( '/\.([a-zA-Z0-9_-]+)/', (string) $selector, $classes );
	foreach ( $classes[1] ?? [] as $class ) {
		cb_seo_bricks_assert( str_contains( $renderer, (string) $class ), 'SEO selector class .' . $class . ' is absent from canonical sitemap markup.' );
	}
}

fwrite( STDOUT, "SEO Golden Bricks regression PASS\n" );
