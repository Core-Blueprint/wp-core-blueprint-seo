# Core Blueprint SEO

Version: `1.0.0-rc1.1`

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
- non-destructive SEOPress migration with preview;
- Core Blueprint governance/audit events.

## Builder independence

Content analysis uses the rendered frontend document as its canonical source. It does not parse Bricks, Gutenberg, Elementor, Divi or other builder storage formats. If WordPress renders the public page, the analyzer can inspect it.

## Privacy boundary

Core Blueprint SEO does not contain Google Analytics, Search Console dashboards, PageSpeed/Google API SDKs, advertising integrations, visitor tracking or telemetry. Analytics remains a separate concern.

## Post-v1 roadmap

- site-wide SEO diagnostics and cross-page checks;
- redirects and 404 monitoring;
- optional additional schema/discovery providers when a concrete use case requires them.
