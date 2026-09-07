# Core Blueprint SEO

Version: `1.0.0-rc1`

Core Blueprint SEO is the privacy-first SEO and discovery extension for Core Blueprint. It improves how public WordPress content is described, indexed, shared and discovered without analytics, visitor tracking, advertising integrations or Google SDKs.

## v1 baseline

- metadata templates for public post types and taxonomies;
- per-object SEO title and meta-description overrides;
- canonical URL overrides;
- restrictive robots directives (`noindex`, `nofollow`, `noimageindex`, `noarchive`, `nosnippet`);
- global indexing and WordPress XML sitemap policy;
- builder-neutral HTML sitemap shortcode with the same sitemap/indexability policy as XML sitemaps;
- optional Bricks SEO Sitemap element backed by the same frontend renderer;
- author/date archive indexing policy, with search and 404 views kept `noindex`;
- Open Graph and X/Twitter metadata;
- basic schema.org JSON-LD with extension graph filter;
- opt-in `/llms.txt` AI Discovery;
- fail-closed AI Discovery handling for Core Blueprint Access-managed content;
- builder-agnostic rendered frontend analyzer with focus-keyword checks;
- detection of overlapping SEO plugins;
- vendor-neutral import of compatible SEO metadata already stored in WordPress post/term meta;
- explicit review/mapping for ambiguous metadata fields, with existing Core Blueprint values always preserved;
- Core Blueprint governance/audit events.

## HTML sitemap

Use `[cb_seo_sitemap]` to render a public HTML sitemap. With no attributes it includes all eligible public post types, preserves hierarchy and uses two responsive columns. Taxonomies are opt-in.

Supported attributes:

- `post_types="page,post"` limits output to selected public post types;
- `taxonomies="category,post_tag"` adds selected public taxonomy sections;
- `show_headings="true|false"` toggles section headings;
- `hierarchy="true|false"` controls nested hierarchical content;
- `orderby="title|date|menu_order"` controls post ordering;
- `order="asc|desc"` controls sort direction;
- `columns="1|2|3|4"` controls the desktop column count.

The HTML sitemap reuses the same Core Blueprint SEO sitemap visibility policy as WordPress XML sitemaps. Post types, taxonomies and individual objects excluded from indexing/sitemaps remain excluded from the frontend sitemap.

## Bricks

When Bricks is active, Core Blueprint SEO registers an optional **SEO Sitemap** element. The adapter contains no SEO business logic: it passes the Bricks controls to the same builder-neutral sitemap renderer used by the shortcode. Sites without Bricks do not load a Bricks dependency.

## WordPress metadata import

The Import screen scans normal WordPress post and term metadata for field names with recognizable SEO semantics. Discovery is based on the stored metadata itself rather than the identity of the plugin or system that created it.

Strong, unambiguous matches can be preselected automatically. Ambiguous candidates remain disabled until an administrator explicitly maps them to a Core Blueprint SEO field. Source metadata is never changed or deleted, and existing Core Blueprint SEO values always win.

Global plugin settings and title-template options are intentionally not guessed: WordPress does not define a canonical SEO option schema that would make those values safely portable between independent implementations.

## Builder independence

Content analysis uses the rendered frontend document as its canonical source. It does not parse Bricks, Gutenberg, Elementor, Divi or other builder storage formats. If WordPress renders the public page, the analyzer can inspect it.

Frontend sitemap rendering follows the same rule: the shortcode/renderer is canonical and builder adapters remain thin optional integrations.

## Privacy boundary

Core Blueprint SEO does not contain Google Analytics, Search Console dashboards, PageSpeed/Google API SDKs, advertising integrations, visitor tracking or telemetry. Analytics remains a separate concern.

## Post-v1 roadmap

- site-wide SEO diagnostics and cross-page checks;
- redirects and 404 monitoring;
- optional additional schema/discovery providers when a concrete use case requires them.
