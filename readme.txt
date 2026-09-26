=== Core Blueprint SEO ===
Contributors: coreblueprint
Tags: seo, metadata, sitemap, schema, robots
Requires at least: 7.0
Requires PHP: 8.4
Stable tag: 1.0.0-rc1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Privacy-first SEO metadata, indexing, sitemap, social and structured-data controls for Core Blueprint.

== Description ==

Core Blueprint SEO provides WordPress-first SEO metadata and indexing controls without analytics, visitor tracking, advertising integrations or Google SDKs.

Features include metadata templates, per-object title and description overrides, canonical URLs, robots directives, WordPress XML sitemap policy, a builder-neutral HTML sitemap, Open Graph and X/Twitter metadata, basic schema.org JSON-LD, opt-in AI Discovery through /llms.txt, rendered-page content analysis and vendor-neutral import of compatible SEO metadata stored in WordPress.

Core Blueprint Base is required.

If another supported SEO-output plugin is active, Core Blueprint SEO keeps its administration and stored configuration available but fails closed for overlapping public SEO output.

The rendered-page analyzer fetches the public URL of the content being analyzed. By default it permits only this WordPress site's own home/site host and sends no authentication cookies.

Bricks support is optional. The SEO Sitemap element uses the same builder-neutral sitemap renderer as the shortcode.

Core Blueprint SEO does not include telemetry, analytics or visitor tracking.

== Installation ==

1. Install and activate Core Blueprint Base.
2. Install and activate Core Blueprint SEO.
3. Open Core Blueprint > SEO.
4. Review metadata, indexing, social/schema and discovery settings.
5. If another SEO-output plugin is active, resolve the reported conflict before enabling public SEO output.

== Privacy ==

Core Blueprint SEO stores SEO configuration and object metadata in the local WordPress database.

The optional rendered-page analyzer performs a WordPress HTTP request to the public URL of the content being analyzed. By default, requests are restricted to this site's own home/site host, contain no authentication cookies and are used only to inspect the rendered HTML for the requested analysis.

The plugin does not include analytics, advertising integrations, visitor tracking or telemetry.

== Frequently Asked Questions ==

= Does Core Blueprint SEO require an external service? =

No. Core SEO features are local to WordPress. Rendered-page analysis requests the site's own public page URL by default.

= Can I use another SEO plugin at the same time? =

Core Blueprint SEO detects known plugins that emit overlapping SEO output and keeps its own overlapping frontend output dormant while a conflict is active.

= Is Bricks required? =

No. Bricks is an optional adapter for the HTML sitemap renderer.

= Does the metadata importer delete source metadata? =

No. Source metadata is read-only and existing Core Blueprint SEO values are preserved.

== Changelog ==

= 1.0.0-rc1 =
* First public release candidate.
* Adds SEO metadata, canonical and robots controls.
* Adds sitemap, social metadata and structured-data output.
* Adds opt-in AI Discovery and rendered-page analysis.
* Adds vendor-neutral WordPress metadata import.
* Adds optional Bricks SEO Sitemap element.
