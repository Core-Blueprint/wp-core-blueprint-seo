# Core Blueprint SEO

Version: `1.0.0-rc4`

Core Blueprint SEO is the privacy-first SEO and discovery extension for Core Blueprint. It improves how public WordPress content is described, indexed, shared and discovered without analytics, visitor tracking, advertising integrations or Google SDKs.

## v1 baseline

- metadata templates for public post types and taxonomies;
- per-object SEO title and meta-description overrides;
- canonical URL overrides;
- restrictive robots directives (`noindex`, `nofollow`, `noimageindex`, `noarchive`, `nosnippet`);
- global indexing and WordPress XML sitemap policy;
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

## WordPress metadata import

The Import screen scans normal WordPress post and term metadata for field names with recognizable SEO semantics. Discovery is based on the stored metadata itself rather than the identity of the plugin or system that created it.

Strong, unambiguous matches can be preselected automatically. Ambiguous candidates remain disabled until an administrator explicitly maps them to a Core Blueprint SEO field. Source metadata is never changed or deleted, and existing Core Blueprint SEO values always win.

Global plugin settings and title-template options are intentionally not guessed: WordPress does not define a canonical SEO option schema that would make those values safely portable between independent implementations.

## Builder independence

Content analysis uses the rendered frontend document as its canonical source. It does not parse Bricks, Gutenberg, Elementor, Divi or other builder storage formats. If WordPress renders the public page, the analyzer can inspect it.

## Privacy boundary

Core Blueprint SEO does not contain Google Analytics, Search Console dashboards, PageSpeed/Google API SDKs, advertising integrations, visitor tracking or telemetry. Analytics remains a separate concern.

## Post-v1 roadmap

- site-wide SEO diagnostics and cross-page checks;
- redirects and 404 monitoring;
- optional additional schema/discovery providers when a concrete use case requires them.
