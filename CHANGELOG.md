# Changelog

## 1.0.0-rc4 — 2026-09-02

- Replaced the vendor-specific SEOPress migration surface with a vendor-neutral WordPress metadata importer.
- Added semantic discovery for compatible SEO fields stored in normal post and term metadata without identifying or targeting the plugin that created them.
- Added explicit source-to-target mapping with conservative defaults: only strong, unambiguous matches are preselected; review candidates remain opt-in.
- Preserved existing Core Blueprint SEO values, left all source metadata untouched and invalidated analysis snapshots only when metadata was actually imported.
- Deliberately stopped importing vendor-specific global options/templates because WordPress has no canonical SEO option schema that makes those settings safely portable.
- Replaced the SEOPress-specific governance event with the generic `seo_metadata_imported` event.
- Renamed the SEO Tools shortcut/tab to Import and removed the obsolete vendor-specific importer implementation.

### Import safety

- Existing Core Blueprint SEO values always win.
- Source post/term metadata is read-only and is never deleted or modified.
- Restrictive robots fields are imported only when the source value is explicitly truthy.
- Social images are accepted only when the source resolves to an existing WordPress attachment ID.
- Multiple source fields mapped to one Core Blueprint target are resolved conservatively: the first non-empty value wins and later conflicts are skipped.
- Ambiguous field names are never silently imported.

## 1.0.0-rc3 — 2026-09-02

- Removed the duplicate SEO settings master switch. Activation remains controlled by the Base dashboard through the existing SEO state and audit events; dormant settings and warnings remain available.
- Declared the public `disclosure` component so post-type and taxonomy sections receive Base-owned layout, chevrons, open/focus states and light/dark styling.
- Aligned the SEOPress migration preview with the public `kv-table` contract: removed WordPress zebra striping, added row-header semantics and kept only column layout in SEO CSS.
- Corrected the Tools section heading contrast and content/action spacing using public design tokens.
- No metadata, indexing, discovery, schema, analysis, settings persistence or import behavior changed.

## 1.0.0-rc1.1 — 2026-08-30

- Removed the retired pre-v1 `cb_core_status_tiles` registration and dedicated dead Dashboard `StatusTile` class.
- Kept canonical Dashboard Card shortcuts, module activation and extension integration unchanged.
- No SEO metadata, indexing, discovery, schema, analysis or migration behavior changed.


## 1.0.0-rc1

### Added

- Author and date archive indexing policy; WordPress search results and 404 views remain explicitly `noindex`.
- Active SEO-plugin conflict detection for SEOPress, Yoast SEO, Rank Math, All in One SEO and Slim SEO, with an extensible conflict registry.
- Fail-closed Core Blueprint Access boundary for AI Discovery: Access-managed content is excluded unless an explicit public bridge confirms anonymous discovery.
- Tools tab with non-destructive SEOPress migration preview and import.
- Migration of supported per-post/per-term titles, descriptions, canonicals, restrictive robots directives and social overrides.
- Migration of supported SEOPress metadata templates and global noindex policy where Core Blueprint SEO has no existing override.
- Governance event for SEOPress migration.

### Migration safety

- Existing Core Blueprint SEO values always win.
- Import is blocked while SEOPress and active Core Blueprint SEO frontend output overlap; use the dormant → import → disable SEOPress → validate/re-enable workflow.
- SEOPress source data is never deleted or modified.
- Facebook social values are preferred with X/Twitter fallback.
- Unknown SEOPress template variables are skipped instead of being imported as invalid templates.
- Redirects, analytics, schema settings and social image URLs without a WordPress attachment ID are intentionally outside the importer scope.

### Privacy and compatibility

- No analytics, tracking, telemetry or Google API integrations are included.
- SEO analysis remains builder-agnostic and based on rendered frontend HTML.
- WordPress Core remains authoritative for document-title fallbacks, robots rendering and XML sitemap generation.

## 0.8.0-rc8

### Added

- Global indexing policy per public post type and taxonomy.
- Independent XML sitemap inclusion policy per public post type and taxonomy.
- Compact Search visibility settings card in Core Blueprint admin.
- Reorganized the Core Blueprint SEO admin into primary SEO admin tabs for Search appearance, Indexing, Social & Schema and AI Discovery.
- Global post-type/taxonomy `noindex` policy merged into the existing WordPress `wp_robots` pipeline.
- WordPress sitemap provider filtering through `wp_sitemaps_post_types` and `wp_sitemaps_taxonomies`.
- AI Discovery eligibility now reuses the same resolved robots/indexability policy.
- Dedicated governance event for global search visibility changes.

### Safety

- Existing public types default to indexable and included in XML sitemaps after upgrade.
- Disabling indexing always forces sitemap exclusion; conflicting global states cannot be stored.
- Individual object controls remain restrictive-only and cannot override a globally blocked content group.
- WordPress Core remains the sitemap engine; Core Blueprint only filters provider visibility and individual `noindex` objects.

## 0.7.0-rc7

### Added

- Builder-agnostic live rendered-page analyzer on native WordPress post/page/CPT edit screens.
- Anonymous same-site frontend fetching with no editor/page-builder storage parsing.
- Focus-keyword checks across title, description, URL, H1, introduction, subheadings and visible body content.
- Technical checks for HTTP status, indexability, canonical, H1 count, heading hierarchy and missing image alt attributes.
- Informational visible-word, internal-link, external-link and image counts.
- Compact per-post analysis snapshots with automatic invalidation on normal WordPress saves.
- Native WordPress HTML API parsing; no PHP DOM extension dependency.
- Split the editor SEO UI into four native WordPress metaboxes: Search appearance, Analysis, Indexing and Social appearance.
- Group analysis findings by Needs attention, Optimisation and Good for faster scanning.
- Skip dependent focus-keyword checks when the underlying metadata is absent instead of reporting duplicate failures.
- Use positive result wording for indexability and image-alt checks where appropriate.

### Safety

- Analysis is manual/on-demand and never runs simply because an editor screen opens.
- Only published same-site HTTP(S) permalinks are fetched in RC7.
- Requests are anonymous, reject unsafe URLs and cap the response body size.
- Draft/preview analysis is deliberately deferred rather than falling back to builder-specific storage.

## 0.6.0-rc6

### Added

- Opt-in AI Discovery with a public `/llms.txt` endpoint.
- Compact Markdown index grouped by selected public WordPress post types.
- Optional resolved SEO descriptions with explicit WordPress excerpt fallback.
- Hard global item limit to keep discovery documents compact.
- `<link rel="describedby">` discovery hint while AI Discovery is enabled.
- `cb_seo_discovery_post_is_public` access-control veto filter for external access layers.
- Dedicated governance event for AI Discovery setting changes.

### Safety

- AI Discovery is disabled by default after upgrade.
- Only published, publicly viewable, non-password-protected and non-`noindex` content is eligible.
- The endpoint returns 404 while SEO or AI Discovery is disabled.
- No builder storage is parsed and no custom database/cache table is introduced.

## 0.5.0-rc5

- Added opt-in Open Graph and X/Twitter metadata.
- Added social title, description and image overrides for posts and public terms.
- Added Media Library image selection and featured/default-image fallback.
- Added opt-in schema.org JSON-LD with Organization/Person, WebSite and WebPage nodes.
- Added `cb_seo_schema_graph` extension boundary for domain-specific schema providers.
- Added governance events and uninstall cleanup for RC5 settings and metadata.
- Social/schema output remains disabled after upgrade until explicitly enabled to avoid duplicate output.

# Changelog

## 0.4.0-rc4

### Added

- Optional canonical URL override for posts, pages, public CPTs and public taxonomy terms.
- WordPress-first singular canonical integration through the `get_canonical_url` filter.
- Explicit term-archive canonical rendering only when a Core Blueprint override is configured.
- Absolute HTTP(S) canonical validation before values are stored.
- Dedicated governance events for post/term canonical changes.
- Canonical cleanup during plugin uninstall.

### Safety

- Empty canonical fields preserve normal WordPress/site canonical behaviour.
- Core Blueprint does not render a second canonical tag for singular content.
- `noindex` and canonical remain independent controls; enabling one does not silently mutate the other.
- Canonical runtime remains fully gated by the SEO master switch.

## 0.3.0-rc3

### Added

- Per-post, page, public CPT and public taxonomy-term robots controls for `noindex`, `nofollow`, `noimageindex`, `noarchive` and `nosnippet`.
- WordPress-first robots integration through the `wp_robots` filter.
- Automatic XML sitemap exclusion for objects explicitly marked `noindex`.
- Dedicated governance events for post/term indexing-directive changes.
- Master-switch gating for all new robots and sitemap behaviour.

### Safety

- Unchecked directives add no robots restrictions and leave WordPress/default behaviour untouched.
- No custom robots meta tag is rendered; Core Blueprint contributes only to WordPress' canonical `wp_robots` pipeline.
- Canonical URLs remain outside RC3 and are not modified.

## 0.2.0-rc2

### Added

- WordPress-first SEO metadata engine for public post types and taxonomies.
- Global title and meta-description templates per public post type and taxonomy.
- Native WordPress per-post and per-term SEO title / meta-description overrides.
- Central metadata resolver with `override → template → WordPress/default` fallback semantics.
- Frontend document-title integration via `pre_get_document_title`.
- Frontend meta-description output only when an explicit Core Blueprint value resolves.
- Template variables for title, site name, separator, explicit excerpt, post type, term, taxonomy and term description.
- Audit events for global template changes, content metadata changes and term metadata changes.
- Full master-switch gating: configuration remains editable while disabled, frontend SEO output does not run.
- Idempotent RC upgrade bookkeeping for normal in-place plugin updates.
- Compact Core Admin metadata-template UI with Post types / Taxonomies / Template variables tabs, collapsible content-type rows, and Foundation-native form controls.

### Architecture

- Metadata administration is builder-independent and lives on native WordPress edit screens.
- The engine deliberately does not parse Bricks, Gutenberg, Elementor, Divi or other builder storage.
- Rendered frontend HTML remains the canonical source planned for the later analysis engine.

### Safety

- Empty/unconfigured templates do not override the normal WordPress document title.
- Core Blueprint emits no fallback meta description unless a template or explicit object override is configured.
- Robots, canonicals, sitemaps, social metadata and schema remain untouched in RC2.

## 0.1.0-rc1

### Added

- Initial standalone Core Blueprint SEO extension.
- Core Blueprint Base `1.0.0-rc1+` requirement and Foundation-contract validation.
- Dedicated `CB\SEO\` namespace/autoload boundary.
- Core Blueprint → SEO registered admin page.
- Core Blueprint Foundation master switch through `ActivationRegistry`.
- Audit events for extension lifecycle and SEO subsystem state changes.
- Dashboard status tile with active/deactivated indicator.
- Safe translation loading on `init` for WordPress 6.7+.
- EN source strings plus NL, DE, FR, ES, IT and `pt_PT` language catalogs.

### Safety

- This release registers no frontend SEO hooks and changes no public metadata or crawl behaviour.
