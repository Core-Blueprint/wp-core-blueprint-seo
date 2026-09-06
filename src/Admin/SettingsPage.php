<?php
declare(strict_types=1);
/**
 * SEO settings and metadata-template configuration.
 *
 * @package Core_Blueprint_SEO
 */

namespace CB\SEO\Admin;

use CB\Core\Admin\SettingsRegistry;
use CB\Core\UI\Card;
use CB\Core\UI\Icon;
use CB\Core\UI\Notice;
use CB\SEO\Governance\Audit;
use CB\SEO\Metadata\SettingsRepository;
use CB\SEO\Indexing\SettingsRepository as IndexingSettingsRepository;
use CB\SEO\Analysis\Repository as AnalysisRepository;
use CB\SEO\Social\SettingsRepository as SocialSettingsRepository;
use CB\SEO\Schema\SettingsRepository as SchemaSettingsRepository;
use CB\SEO\Discovery\SettingsRepository as DiscoverySettingsRepository;
use CB\SEO\Discovery\AccessGuard;
use CB\SEO\State;
use CB\SEO\Compatibility\SeoPluginConflictDetector;
use CB\SEO\Migration\WordPressMetadataImporter;

defined( 'ABSPATH' ) || exit;

final class SettingsPage {
	private const NONCE_ACTION = 'cb_seo_save_metadata_settings';
	private const NONCE_NAME   = 'cb_seo_settings_nonce';
	private const SECTIONS     = [ 'post-types', 'taxonomies', 'variables' ];
	private const TABS         = [ 'search-appearance', 'indexing', 'social-schema', 'ai-discovery', 'import' ];

	/** @param string[] $links */
	public static function plugin_action_links( array $links ): array {
		$url = SettingsRegistry::url( 'core-blueprint-seo' );
		array_unshift(
			$links,
			sprintf(
				'<a href="%s">%s</a>',
				esc_url( $url ),
				esc_html__( 'Settings', 'core-blueprint-seo' )
			)
		);
		return $links;
	}

	public static function handle_save(): void {
		if ( ! isset( $_POST['cb_seo_action'] ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$action = sanitize_key( wp_unslash( $_POST['cb_seo_action'] ) );
		if ( ! in_array( $action, [ 'save_metadata_templates', 'save_presentation_settings', 'save_discovery_settings', 'save_indexing_settings', 'import_wordpress_metadata' ], true ) ) {
			return;
		}
		check_admin_referer( self::NONCE_ACTION, self::NONCE_NAME );

		if ( 'import_wordpress_metadata' === $action ) {
			$raw = isset( $_POST['cb_seo_import_sources'] ) && is_array( $_POST['cb_seo_import_sources'] ) ? $_POST['cb_seo_import_sources'] : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- importer validates discovered keys and target identifiers.
			$report = WordPressMetadataImporter::import( $raw );
			if ( $report['fields_imported'] > 0 ) {
				AnalysisRepository::delete_all_snapshots();
			}
			if ( $report['mappings_used'] > 0 ) {
				Audit::log( 'seo_metadata_imported', $report );
			}
			wp_safe_redirect(
				SettingsRegistry::url(
					'core-blueprint-seo',
					[
						'tab'                     => 'import',
						'cb_seo_notice'           => $report['mappings_used'] > 0 ? 'metadata-imported' : 'metadata-import-empty',
						'cb_seo_imported_objects' => $report['posts_changed'] + $report['terms_changed'],
						'cb_seo_imported_fields'  => $report['fields_imported'],
						'cb_seo_import_mappings'  => $report['mappings_used'],
						'cb_seo_import_skipped'   => $report['skipped_existing'],
					]
				)
			);
			exit;
		}

		if ( 'save_indexing_settings' === $action ) {
			$raw = isset( $_POST['cb_seo_indexing'] ) && is_array( $_POST['cb_seo_indexing'] ) ? $_POST['cb_seo_indexing'] : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- repository sanitizes values.
			$changed = IndexingSettingsRepository::save( $raw );
			if ( $changed ) {
				AnalysisRepository::delete_all_snapshots();
				Audit::log( 'seo_indexing_settings_updated' );
			}
			wp_safe_redirect( SettingsRegistry::url( 'core-blueprint-seo', [ 'tab' => 'indexing', 'cb_seo_notice' => $changed ? 'indexing-saved' : 'indexing-unchanged' ] ) );
			exit;
		}

		if ( 'save_discovery_settings' === $action ) {
			$raw = isset( $_POST['cb_seo_discovery'] ) && is_array( $_POST['cb_seo_discovery'] ) ? $_POST['cb_seo_discovery'] : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- repository sanitizes values.
			$changed = DiscoverySettingsRepository::save( $raw );
			if ( $changed ) {
				Audit::log( 'seo_discovery_settings_updated' );
			}
			wp_safe_redirect( SettingsRegistry::url( 'core-blueprint-seo', [ 'tab' => 'ai-discovery', 'cb_seo_notice' => $changed ? 'discovery-saved' : 'discovery-unchanged' ] ) );
			exit;
		}

		if ( 'save_presentation_settings' === $action ) {
			$social_raw = isset( $_POST['cb_seo_social'] ) && is_array( $_POST['cb_seo_social'] ) ? $_POST['cb_seo_social'] : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- repository sanitizes values.
			$schema_raw = isset( $_POST['cb_seo_schema'] ) && is_array( $_POST['cb_seo_schema'] ) ? $_POST['cb_seo_schema'] : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- repository sanitizes values.
			$social_changed = SocialSettingsRepository::save( $social_raw );
			$schema_changed = SchemaSettingsRepository::save( $schema_raw );
			if ( $social_changed ) {
				Audit::log( 'seo_social_settings_updated' );
			}
			if ( $schema_changed ) {
				Audit::log( 'seo_schema_settings_updated' );
			}
			$changed = $social_changed || $schema_changed;
			wp_safe_redirect( SettingsRegistry::url( 'core-blueprint-seo', [ 'tab' => 'social-schema', 'cb_seo_notice' => $changed ? 'presentation-saved' : 'presentation-unchanged' ] ) );
			exit;
		}

		$raw = isset( $_POST['cb_seo_templates'] ) && is_array( $_POST['cb_seo_templates'] ) ? $_POST['cb_seo_templates'] : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- repository sanitizes every scalar.
		$section = isset( $_POST['cb_seo_section'] ) ? sanitize_key( wp_unslash( $_POST['cb_seo_section'] ) ) : 'post-types';
		if ( ! in_array( $section, self::SECTIONS, true ) ) {
			$section = 'post-types';
		}
		$changed = SettingsRepository::save( $raw );
		if ( $changed ) {
			Audit::log( 'seo_metadata_settings_updated' );
		}
		wp_safe_redirect( SettingsRegistry::url( 'core-blueprint-seo', [ 'tab' => 'search-appearance', 'section' => $section, 'cb_seo_notice' => $changed ? 'saved' : 'unchanged' ] ) );
		exit;
	}

	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$enabled = State::is_enabled();
		?>
		<div class="wrap cb-core-wrap cb-core-wrap--narrow cb-core-seo-wrap">
			<h1 class="cb-core-title"><?php esc_html_e( 'SEO', 'core-blueprint-seo' ); ?></h1>
			<p class="cb-core-intro"><?php esc_html_e( 'Governed search metadata with WordPress-first fallbacks and builder-independent administration.', 'core-blueprint-seo' ); ?></p>

			<?php self::render_notice(); ?>
			<?php self::render_conflict_notice( $enabled ); ?>

			<?php if ( ! $enabled ) : ?>
				<?php
				echo Notice::render( [ // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Foundation renderer is escape-clean.
					'variant' => Notice::WARNING,
					'title'   => __( 'SEO output is disabled', 'core-blueprint-seo' ),
					'message' => __( 'You can prepare metadata below while the module is dormant. Nothing is emitted on the frontend until SEO is enabled.', 'core-blueprint-seo' ),
				] );
				?>
			<?php endif; ?>

			<?php self::render_primary_tabs(); ?>

			<?php
			$active_tab = self::active_tab();
			if ( 'search-appearance' === $active_tab ) {
				echo Card::render( [ // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- body is escaped by builder below.
					'variant' => Card::VARIANT_SPACIOUS,
					'title'   => __( 'Metadata templates', 'core-blueprint-seo' ),
					'body'    => self::templates_body(),
				] );
			} elseif ( 'indexing' === $active_tab ) {
				echo Card::render( [ // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- method escapes fields.
					'variant' => Card::VARIANT_SPACIOUS,
					'title'   => __( 'Search visibility', 'core-blueprint-seo' ),
					'body'    => self::indexing_body(),
				] );
			} elseif ( 'social-schema' === $active_tab ) {
				echo Card::render( [ // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- method escapes fields.
					'variant' => Card::VARIANT_SPACIOUS,
					'title'   => __( 'Social and structured data', 'core-blueprint-seo' ),
					'body'    => self::presentation_body(),
				] );
			} elseif ( 'ai-discovery' === $active_tab ) {
				echo Card::render( [ // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- method escapes fields.
					'variant' => Card::VARIANT_SPACIOUS,
					'title'   => __( 'AI discovery', 'core-blueprint-seo' ),
					'body'    => self::discovery_body(),
				] );
			} else {
				echo Card::render( [ // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- method escapes fields.
					'variant' => Card::VARIANT_SPACIOUS,
					'title'   => __( 'Import', 'core-blueprint-seo' ),
					'body'    => self::import_body(),
				] );
			}
			?>
		</div>
		<?php
	}

	private static function render_primary_tabs(): void {
		$active = self::active_tab();
		$tabs = [
			'search-appearance' => __( 'Search appearance', 'core-blueprint-seo' ),
			'indexing'          => __( 'Indexing', 'core-blueprint-seo' ),
			'social-schema'     => __( 'Social & Schema', 'core-blueprint-seo' ),
			'ai-discovery'      => __( 'AI Discovery', 'core-blueprint-seo' ),
			'import'            => __( 'Import', 'core-blueprint-seo' ),
		];
		?>
		<nav class="nav-tab-wrapper cb-core-tab-wrapper cb-seo-primary-tabs" aria-label="<?php esc_attr_e( 'SEO sections', 'core-blueprint-seo' ); ?>">
			<?php foreach ( $tabs as $slug => $label ) : ?>
				<a class="nav-tab<?php echo $slug === $active ? ' nav-tab-active' : ''; ?>" href="<?php echo esc_url( self::tab_url( $slug ) ); ?>"<?php echo $slug === $active ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $label ); ?></a>
			<?php endforeach; ?>
		</nav>
		<?php
	}

	private static function active_tab(): string {
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'search-appearance'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only UI state.
		return in_array( $tab, self::TABS, true ) ? $tab : 'search-appearance';
	}

	private static function tab_url( string $tab ): string {
		$args = [ 'tab' => $tab ];
		if ( 'search-appearance' === $tab ) {
			$args['section'] = self::active_section();
		}
		return SettingsRegistry::url( 'core-blueprint-seo', $args );
	}

	private static function render_conflict_notice( bool $enabled ): void {
		if ( ! $enabled ) {
			return;
		}

		$conflicts = SeoPluginConflictDetector::active_conflicts();
		if ( empty( $conflicts ) ) {
			return;
		}

		$names = implode( ', ', array_values( array_unique( $conflicts ) ) );
		echo Notice::render( [ // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Foundation renderer is escape-clean.
			'variant' => Notice::WARNING,
			'title'   => __( 'Another SEO plugin is active', 'core-blueprint-seo' ),
			'message' => sprintf(
				/* translators: %s: comma-separated plugin names. */
				__( '%s can emit overlapping titles, canonicals, robots directives, social metadata or structured data. Keep only one SEO output provider active when validating the frontend.', 'core-blueprint-seo' ),
				$names
			),
		] );
	}

	private static function render_notice(): void {
		$notice = isset( $_GET['cb_seo_notice'] ) ? sanitize_key( wp_unslash( $_GET['cb_seo_notice'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only redirect feedback.
		if ( 'saved' === $notice ) {
			echo Notice::render( [ // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				'variant' => Notice::SUCCESS,
				'message' => __( 'SEO metadata templates saved.', 'core-blueprint-seo' ),
			] );
		} elseif ( 'indexing-saved' === $notice ) {
			echo Notice::render( [ 'variant' => Notice::SUCCESS, 'message' => __( 'Search visibility settings saved.', 'core-blueprint-seo' ) ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		} elseif ( 'indexing-unchanged' === $notice ) {
			echo Notice::render( [ 'variant' => Notice::INFO, 'message' => __( 'No search visibility changes were detected.', 'core-blueprint-seo' ) ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		} elseif ( 'presentation-saved' === $notice ) {
			echo Notice::render( [ 'variant' => Notice::SUCCESS, 'message' => __( 'Social and structured data settings saved.', 'core-blueprint-seo' ) ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		} elseif ( 'presentation-unchanged' === $notice ) {
			echo Notice::render( [ 'variant' => Notice::INFO, 'message' => __( 'No social or structured data changes were detected.', 'core-blueprint-seo' ) ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		} elseif ( 'discovery-saved' === $notice ) {
			echo Notice::render( [ 'variant' => Notice::SUCCESS, 'message' => __( 'AI discovery settings saved.', 'core-blueprint-seo' ) ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		} elseif ( 'discovery-unchanged' === $notice ) {
			echo Notice::render( [ 'variant' => Notice::INFO, 'message' => __( 'No AI discovery changes were detected.', 'core-blueprint-seo' ) ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		} elseif ( 'metadata-import-empty' === $notice ) {
			echo Notice::render( [
				'variant' => Notice::INFO,
				'message' => __( 'No valid SEO metadata mappings were selected, so nothing was imported.', 'core-blueprint-seo' ),
			] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		} elseif ( 'metadata-imported' === $notice ) {
			$objects  = isset( $_GET['cb_seo_imported_objects'] ) ? absint( $_GET['cb_seo_imported_objects'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only redirect feedback.
			$fields   = isset( $_GET['cb_seo_imported_fields'] ) ? absint( $_GET['cb_seo_imported_fields'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only redirect feedback.
			$mappings = isset( $_GET['cb_seo_import_mappings'] ) ? absint( $_GET['cb_seo_import_mappings'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only redirect feedback.
			$skipped  = isset( $_GET['cb_seo_import_skipped'] ) ? absint( $_GET['cb_seo_import_skipped'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only redirect feedback.
			echo Notice::render( [ // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Foundation renderer is escape-clean.
				'variant' => Notice::SUCCESS,
				'message' => sprintf(
					/* translators: 1: changed objects, 2: imported fields, 3: mappings used, 4: existing values preserved. */
					__( 'SEO metadata import completed. %1$d content objects changed, %2$d fields were imported through %3$d mappings, and %4$d existing Core Blueprint values were preserved.', 'core-blueprint-seo' ),
					$objects,
					$fields,
					$mappings,
					$skipped
				),
			] );
		} elseif ( 'unchanged' === $notice ) {
			echo Notice::render( [ // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				'variant' => Notice::INFO,
				'message' => __( 'No metadata template changes were detected.', 'core-blueprint-seo' ),
			] );
		}
	}

	private static function indexing_body(): string {
		$settings   = IndexingSettingsRepository::all();
		$post_types = get_post_types( [ 'public' => true ], 'objects' );
		$taxonomies = get_taxonomies( [ 'public' => true ], 'objects' );
		unset( $post_types['attachment'] );
		if ( function_exists( 'is_post_type_viewable' ) ) {
			$post_types = array_filter( $post_types, 'is_post_type_viewable' );
		}
		if ( function_exists( 'is_taxonomy_viewable' ) ) {
			$taxonomies = array_filter( $taxonomies, 'is_taxonomy_viewable' );
		}

		ob_start();
		?>
		<form method="post" class="cb-core-form-scope cb-seo-indexing-form">
			<?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME ); ?>
			<input type="hidden" name="cb_seo_action" value="save_indexing_settings">

			<p class="cb-seo-panel-intro"><?php esc_html_e( 'Control which public content groups search engines may index and which groups WordPress publishes in its XML sitemap.', 'core-blueprint-seo' ); ?></p>
			<p class="description"><?php esc_html_e( 'Individual SEO controls can add restrictions, but they cannot override a content group that is disabled here.', 'core-blueprint-seo' ); ?></p>

			<div class="cb-seo-policy-groups">
				<?php self::render_policy_group( 'post_types', __( 'Post types', 'core-blueprint-seo' ), $post_types, $settings['post_types'] ?? [] ); ?>
				<?php self::render_policy_group( 'taxonomies', __( 'Taxonomies', 'core-blueprint-seo' ), $taxonomies, $settings['taxonomies'] ?? [] ); ?>
			</div>

			<?php self::render_archive_policy( $settings['contexts'] ?? [] ); ?>

			<p class="description cb-seo-policy-note"><?php esc_html_e( 'When indexing is disabled, Core Blueprint also removes that content group from XML sitemaps. Disabling only the sitemap keeps public pages indexable but stops advertising them through the WordPress sitemap.', 'core-blueprint-seo' ); ?></p>
			<div class="cb-seo-save-row"><button type="submit" class="button button-primary cb-core-button cb-core-button--primary"><?php esc_html_e( 'Save search visibility', 'core-blueprint-seo' ); ?></button></div>
		</form>
		<?php
		return (string) ob_get_clean();
	}

	/** @param array<string,array{index:bool}> $contexts */
	private static function render_archive_policy( array $contexts ): void {
		$author = $contexts['author']['index'] ?? true;
		$date   = $contexts['date']['index'] ?? true;
		?>
		<fieldset class="cb-seo-settings-fieldset cb-seo-archive-policy">
			<legend><strong><?php esc_html_e( 'Archive pages', 'core-blueprint-seo' ); ?></strong></legend>
			<p class="description"><?php esc_html_e( 'Control indexability for WordPress archive contexts that are not individual post types or taxonomies.', 'core-blueprint-seo' ); ?></p>
			<label>
				<input type="hidden" name="cb_seo_indexing[contexts][author][index]" value="0">
				<input type="checkbox" name="cb_seo_indexing[contexts][author][index]" value="1" <?php checked( $author ); ?>>
				<?php esc_html_e( 'Allow author archives to be indexed', 'core-blueprint-seo' ); ?>
			</label>
			<label>
				<input type="hidden" name="cb_seo_indexing[contexts][date][index]" value="0">
				<input type="checkbox" name="cb_seo_indexing[contexts][date][index]" value="1" <?php checked( $date ); ?>>
				<?php esc_html_e( 'Allow date archives to be indexed', 'core-blueprint-seo' ); ?>
			</label>
			<p class="description"><?php esc_html_e( 'Search results and 404 pages remain noindex in line with WordPress defaults.', 'core-blueprint-seo' ); ?></p>
		</fieldset>
		<?php
	}

	/** @param array<string,object> $objects
	 *  @param array<string,array{index:bool,sitemap:bool}> $settings
	 */
	private static function render_policy_group( string $group, string $title, array $objects, array $settings ): void {
		?>
		<fieldset class="cb-seo-policy-group">
			<legend><strong><?php echo esc_html( $title ); ?></strong></legend>
			<div class="cb-seo-policy-header" aria-hidden="true">
				<span><?php esc_html_e( 'Content', 'core-blueprint-seo' ); ?></span>
				<span><?php esc_html_e( 'Index', 'core-blueprint-seo' ); ?></span>
				<span><?php esc_html_e( 'Sitemap', 'core-blueprint-seo' ); ?></span>
			</div>
			<div class="cb-seo-policy-list">
				<?php foreach ( $objects as $slug => $object ) :
					$current = $settings[ $slug ] ?? [ 'index' => true, 'sitemap' => true ];
					$label = isset( $object->labels->name ) ? (string) $object->labels->name : (string) $slug;
					?>
					<div class="cb-seo-policy-row" data-cb-seo-policy-row>
						<div class="cb-seo-policy-label"><strong><?php echo esc_html( $label ); ?></strong><code><?php echo esc_html( (string) $slug ); ?></code></div>
						<label class="cb-seo-policy-toggle">
							<input type="hidden" name="cb_seo_indexing[<?php echo esc_attr( $group ); ?>][<?php echo esc_attr( (string) $slug ); ?>][index]" value="0">
							<input type="checkbox" name="cb_seo_indexing[<?php echo esc_attr( $group ); ?>][<?php echo esc_attr( (string) $slug ); ?>][index]" value="1" <?php checked( $current['index'] ); ?> data-cb-seo-policy-index>
							<span class="screen-reader-text"><?php echo esc_html( sprintf( __( 'Allow search engines to index %s', 'core-blueprint-seo' ), $label ) ); ?></span>
						</label>
						<label class="cb-seo-policy-toggle">
							<input type="hidden" name="cb_seo_indexing[<?php echo esc_attr( $group ); ?>][<?php echo esc_attr( (string) $slug ); ?>][sitemap]" value="0">
							<input type="checkbox" name="cb_seo_indexing[<?php echo esc_attr( $group ); ?>][<?php echo esc_attr( (string) $slug ); ?>][sitemap]" value="1" <?php checked( $current['sitemap'] ); ?> data-cb-seo-policy-sitemap>
							<span class="screen-reader-text"><?php echo esc_html( sprintf( __( 'Include %s in the XML sitemap', 'core-blueprint-seo' ), $label ) ); ?></span>
						</label>
					</div>
				<?php endforeach; ?>
			</div>
		</fieldset>
		<?php
	}

	private static function presentation_body(): string {
		$social = SocialSettingsRepository::all();
		$schema = SchemaSettingsRepository::all();
		ob_start();
		?>
		<form method="post" class="cb-core-form-scope cb-seo-presentation-form">
			<?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME ); ?>
			<input type="hidden" name="cb_seo_action" value="save_presentation_settings">

			<fieldset class="cb-seo-settings-fieldset">
				<legend><strong><?php esc_html_e( 'Social metadata', 'core-blueprint-seo' ); ?></strong></legend>
				<label><input type="checkbox" name="cb_seo_social[enabled]" value="1" <?php checked( $social['enabled'] ); ?>> <?php esc_html_e( 'Emit Open Graph and X/Twitter metadata', 'core-blueprint-seo' ); ?></label>
				<p class="description"><?php esc_html_e( 'Disabled by default on upgrade so existing SEO plugins are not duplicated. Per-content social overrides remain stored either way.', 'core-blueprint-seo' ); ?></p>
				<?php self::render_settings_image_picker( 'cb_seo_social[default_image_id]', $social['default_image_id'], __( 'Default social image', 'core-blueprint-seo' ), __( 'Used when content has no explicit social image or featured image.', 'core-blueprint-seo' ) ); ?>
			</fieldset>

			<fieldset class="cb-seo-settings-fieldset">
				<legend><strong><?php esc_html_e( 'Structured data', 'core-blueprint-seo' ); ?></strong></legend>
				<label><input type="checkbox" name="cb_seo_schema[enabled]" value="1" <?php checked( $schema['enabled'] ); ?>> <?php esc_html_e( 'Emit Core Blueprint JSON-LD', 'core-blueprint-seo' ); ?></label>
				<p class="description"><?php esc_html_e( 'Outputs one small schema.org graph for the site identity, website and current page. Other Core Blueprint extensions can add their own nodes through the shared graph filter.', 'core-blueprint-seo' ); ?></p>

				<div class="cb-core-field">
					<label class="cb-core-field__label" for="cb-seo-identity-type"><?php esc_html_e( 'Site identity type', 'core-blueprint-seo' ); ?></label>
					<div class="cb-core-field__control"><select id="cb-seo-identity-type" name="cb_seo_schema[identity_type]"><option value="organization" <?php selected( $schema['identity_type'], 'organization' ); ?>><?php esc_html_e( 'Organization', 'core-blueprint-seo' ); ?></option><option value="person" <?php selected( $schema['identity_type'], 'person' ); ?>><?php esc_html_e( 'Person', 'core-blueprint-seo' ); ?></option></select></div>
				</div>
				<div class="cb-core-field">
					<label class="cb-core-field__label" for="cb-seo-identity-name"><?php esc_html_e( 'Site identity name', 'core-blueprint-seo' ); ?></label>
					<div class="cb-core-field__control"><input type="text" id="cb-seo-identity-name" name="cb_seo_schema[identity_name]" value="<?php echo esc_attr( $schema['identity_name'] ); ?>" placeholder="<?php echo esc_attr( (string) get_bloginfo( 'name' ) ); ?>"></div>
					<p class="description"><?php esc_html_e( 'Leave empty to use the WordPress site title.', 'core-blueprint-seo' ); ?></p>
				</div>
				<?php self::render_settings_image_picker( 'cb_seo_schema[logo_id]', $schema['logo_id'], __( 'Identity logo or image', 'core-blueprint-seo' ), __( 'Used as the Organization logo or Person image in structured data.', 'core-blueprint-seo' ) ); ?>
			</fieldset>

			<div class="cb-seo-save-row"><button type="submit" class="button button-primary cb-core-button cb-core-button--primary"><?php esc_html_e( 'Save social and structured data', 'core-blueprint-seo' ); ?></button></div>
		</form>
		<?php
		return (string) ob_get_clean();
	}

	private static function discovery_body(): string {
		$settings = DiscoverySettingsRepository::all();
		$post_types = get_post_types( [ 'public' => true, 'show_ui' => true ], 'objects' );
		unset( $post_types['attachment'] );
		$access_managed_types = AccessGuard::managed_types( $settings['post_types'] );
		ob_start();
		?>
		<?php if ( ! empty( $access_managed_types ) ) : ?>
			<?php
			echo Notice::render( [ // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Foundation renderer is escape-clean.
				'variant' => Notice::WARNING,
				'title'   => __( 'Access-managed content is protected from AI discovery', 'core-blueprint-seo' ),
				'message' => sprintf(
					/* translators: %s: comma-separated post type slugs. */
					__( 'For privacy, Core Blueprint SEO excludes selected Access-managed content types until Core Blueprint Access explicitly confirms anonymous public discovery: %s.', 'core-blueprint-seo' ),
					implode( ', ', array_map( 'sanitize_key', $access_managed_types ) )
				),
			] );
			?>
		<?php endif; ?>

		<form method="post" class="cb-core-form-scope cb-seo-discovery-form">
			<?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME ); ?>
			<input type="hidden" name="cb_seo_action" value="save_discovery_settings">

			<fieldset class="cb-seo-settings-fieldset">
				<legend><strong><?php esc_html_e( 'llms.txt', 'core-blueprint-seo' ); ?></strong></legend>
				<label><input type="checkbox" name="cb_seo_discovery[enabled]" value="1" <?php checked( $settings['enabled'] ); ?>> <?php esc_html_e( 'Publish an AI discovery document at /llms.txt', 'core-blueprint-seo' ); ?></label>
				<p class="description"><?php esc_html_e( 'The document lists selected public and indexable WordPress content. It is disabled by default on upgrade and does not affect search rankings.', 'core-blueprint-seo' ); ?></p>
				<p><code><?php echo esc_html( home_url( '/llms.txt' ) ); ?></code></p>
			</fieldset>

			<fieldset class="cb-seo-settings-fieldset">
				<legend><strong><?php esc_html_e( 'Included content', 'core-blueprint-seo' ); ?></strong></legend>
				<p class="description"><?php esc_html_e( 'Only published, publicly viewable, non-password-protected content is eligible. Content explicitly marked noindex is always excluded.', 'core-blueprint-seo' ); ?></p>
				<div class="cb-seo-discovery-types">
					<?php foreach ( $post_types as $slug => $object ) : ?>
						<label><input type="checkbox" name="cb_seo_discovery[post_types][]" value="<?php echo esc_attr( (string) $slug ); ?>" <?php checked( in_array( (string) $slug, $settings['post_types'], true ) ); ?>> <?php echo esc_html( (string) $object->labels->name ); ?> <code><?php echo esc_html( (string) $slug ); ?></code></label>
					<?php endforeach; ?>
				</div>
			</fieldset>

			<fieldset class="cb-seo-settings-fieldset">
				<legend><strong><?php esc_html_e( 'Document detail', 'core-blueprint-seo' ); ?></strong></legend>
				<label><input type="checkbox" name="cb_seo_discovery[include_descriptions]" value="1" <?php checked( $settings['include_descriptions'] ); ?>> <?php esc_html_e( 'Include descriptions when available', 'core-blueprint-seo' ); ?></label>
				<p class="description"><?php esc_html_e( 'Core Blueprint uses the resolved SEO description first and falls back only to an explicit WordPress excerpt. Builder content is not parsed.', 'core-blueprint-seo' ); ?></p>
				<div class="cb-core-field">
					<label class="cb-core-field__label" for="cb-seo-discovery-max-items"><?php esc_html_e( 'Maximum listed items', 'core-blueprint-seo' ); ?></label>
					<div class="cb-core-field__control"><input type="number" min="10" max="1000" step="10" id="cb-seo-discovery-max-items" name="cb_seo_discovery[max_items]" value="<?php echo esc_attr( (string) $settings['max_items'] ); ?>"></div>
					<p class="description"><?php esc_html_e( 'Hard limit across all selected content types to keep the document compact.', 'core-blueprint-seo' ); ?></p>
				</div>
			</fieldset>

			<div class="cb-seo-save-row"><button type="submit" class="button button-primary cb-core-button cb-core-button--primary"><?php esc_html_e( 'Save AI discovery', 'core-blueprint-seo' ); ?></button></div>
		</form>
		<?php
		return (string) ob_get_clean();
	}

	private static function import_body(): string {
		$preview = WordPressMetadataImporter::preview();
		$targets = self::import_target_labels();
		ob_start();
		?>
		<section class="cb-seo-import-section" aria-labelledby="cb-seo-import-title">
			<h3 id="cb-seo-import-title"><?php esc_html_e( 'Import existing SEO metadata', 'core-blueprint-seo' ); ?></h3>
			<p><?php esc_html_e( 'Core Blueprint discovers existing WordPress metadata by field semantics rather than vendor-specific import profiles. Stored field names are shown as-is so you can review the source before importing.', 'core-blueprint-seo' ); ?></p>
			<p class="description"><?php esc_html_e( 'Source metadata is never changed or deleted, and existing Core Blueprint SEO values always win. Global plugin settings and templates are intentionally not guessed because WordPress has no canonical SEO option schema.', 'core-blueprint-seo' ); ?></p>

			<?php if ( ! $preview['detected'] ) : ?>
				<?php
				echo Notice::render( [ // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Foundation renderer is escape-clean.
					'variant' => Notice::INFO,
					'message' => __( 'No compatible SEO metadata candidates were discovered in WordPress post or term metadata.', 'core-blueprint-seo' ),
				] );
				?>
			<?php else : ?>
				<form method="post" class="cb-core-form-scope cb-seo-import-form">
					<?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME ); ?>
					<input type="hidden" name="cb_seo_action" value="import_wordpress_metadata">

					<div class="cb-seo-import-overview">
						<span class="cb-core-badge cb-core-badge-standard"><?php echo esc_html( sprintf( __( '%d automatic', 'core-blueprint-seo' ), $preview['automatic_mappings'] ) ); ?></span>
						<span class="cb-core-badge cb-core-badge-neutral"><?php echo esc_html( sprintf( __( '%d review', 'core-blueprint-seo' ), $preview['review_mappings'] ) ); ?></span>
					</div>

					<div class="cb-seo-import-list">
						<?php foreach ( $preview['candidates'] as $index => $candidate ) :
							$select_id = 'cb-seo-import-target-' . absint( $index );
							$is_automatic = 'automatic' === $candidate['confidence'] && '' !== $candidate['default_target'];
							?>
							<div class="cb-seo-import-row">
								<div class="cb-seo-import-source">
									<div class="cb-seo-import-source__key"><code><?php echo esc_html( $candidate['key'] ); ?></code></div>
									<div class="cb-seo-import-source__meta">
										<?php echo esc_html( sprintf( __( 'Posts/CPTs: %1$d · Terms: %2$d', 'core-blueprint-seo' ), $candidate['post_objects'], $candidate['term_objects'] ) ); ?>
										<span class="cb-core-badge <?php echo $is_automatic ? 'cb-core-badge-standard' : 'cb-core-badge-neutral'; ?>"><?php echo esc_html( $is_automatic ? __( 'Automatic', 'core-blueprint-seo' ) : __( 'Review', 'core-blueprint-seo' ) ); ?></span>
									</div>
								</div>
								<div class="cb-seo-import-target">
									<input type="hidden" name="cb_seo_import_sources[<?php echo esc_attr( (string) $index ); ?>][key]" value="<?php echo esc_attr( $candidate['key'] ); ?>">
									<label for="<?php echo esc_attr( $select_id ); ?>"><?php esc_html_e( 'Map to', 'core-blueprint-seo' ); ?></label>
									<select id="<?php echo esc_attr( $select_id ); ?>" name="cb_seo_import_sources[<?php echo esc_attr( (string) $index ); ?>][target]">
										<option value=""><?php esc_html_e( 'Do not import', 'core-blueprint-seo' ); ?></option>
										<?php foreach ( $targets as $target_id => $target_label ) : ?>
											<option value="<?php echo esc_attr( $target_id ); ?>" <?php selected( $candidate['default_target'], $target_id ); ?>><?php echo esc_html( $target_label ); ?></option>
										<?php endforeach; ?>
									</select>
									<?php if ( '' === $candidate['default_target'] && '' !== $candidate['suggested_target'] && isset( $targets[ $candidate['suggested_target'] ] ) ) : ?>
										<p class="description"><?php echo esc_html( sprintf( __( 'Suggested: %s. Review before importing.', 'core-blueprint-seo' ), $targets[ $candidate['suggested_target'] ] ) ); ?></p>
									<?php endif; ?>
								</div>
							</div>
						<?php endforeach; ?>
					</div>

					<p class="description cb-seo-import-note"><?php esc_html_e( 'Automatic mappings are preselected only when the semantic match is strong and unambiguous. Review candidates remain disabled until you explicitly map them. If multiple source fields target the same Core Blueprint field on one object, the first non-empty mapped source wins and later conflicts are skipped.', 'core-blueprint-seo' ); ?></p>
					<div class="cb-seo-save-row"><button type="submit" class="button button-primary cb-core-button cb-core-button--primary"><?php esc_html_e( 'Import selected metadata', 'core-blueprint-seo' ); ?></button></div>
				</form>
			<?php endif; ?>
		</section>
		<?php
		return (string) ob_get_clean();
	}

	/** @return array<string,string> */
	private static function import_target_labels(): array {
		return [
			'title'              => __( 'SEO title', 'core-blueprint-seo' ),
			'description'        => __( 'Meta description', 'core-blueprint-seo' ),
			'canonical'          => __( 'Canonical URL', 'core-blueprint-seo' ),
			'noindex'            => __( 'Robots: noindex', 'core-blueprint-seo' ),
			'nofollow'           => __( 'Robots: nofollow', 'core-blueprint-seo' ),
			'noimageindex'       => __( 'Robots: noimageindex', 'core-blueprint-seo' ),
			'noarchive'          => __( 'Robots: noarchive', 'core-blueprint-seo' ),
			'nosnippet'          => __( 'Robots: nosnippet', 'core-blueprint-seo' ),
			'social_title'       => __( 'Social title', 'core-blueprint-seo' ),
			'social_description' => __( 'Social description', 'core-blueprint-seo' ),
			'social_image_id'    => __( 'Social image attachment ID', 'core-blueprint-seo' ),
		];
	}

	private static function render_settings_image_picker( string $name, int $attachment_id, string $label, string $description ): void {
		$url = $attachment_id > 0 ? (string) wp_get_attachment_image_url( $attachment_id, 'medium' ) : '';
		?>
		<div class="cb-core-field cb-seo-image-field" data-cb-seo-image-field>
			<label class="cb-core-field__label"><?php echo esc_html( $label ); ?></label>
			<input type="hidden" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( (string) $attachment_id ); ?>" data-cb-seo-image-id>
			<div class="cb-seo-image-field__preview" data-cb-seo-image-preview<?php echo '' === $url ? ' hidden' : ''; ?>><?php if ( '' !== $url ) : ?><img src="<?php echo esc_url( $url ); ?>" alt=""><?php endif; ?></div>
			<p class="cb-seo-image-field__actions"><button type="button" class="button cb-core-button" data-cb-seo-image-select><?php esc_html_e( 'Choose image', 'core-blueprint-seo' ); ?></button><button type="button" class="button-link-delete" data-cb-seo-image-remove<?php echo $attachment_id <= 0 ? ' hidden' : ''; ?>><?php esc_html_e( 'Remove image', 'core-blueprint-seo' ); ?></button></p>
			<p class="description"><?php echo esc_html( $description ); ?></p>
		</div>
		<?php
	}

	private static function templates_body(): string {
		$settings       = SettingsRepository::all();
		$post_types     = get_post_types( [ 'public' => true, 'show_ui' => true ], 'objects' );
		$taxonomies     = get_taxonomies( [ 'public' => true, 'show_ui' => true ], 'objects' );
		$active_section = self::active_section();
		unset( $post_types['attachment'] );

		$post_type_configured = self::configured_count( $settings['post_types'] ?? [] );
		$taxonomy_configured  = self::configured_count( $settings['taxonomies'] ?? [] );

		ob_start();
		?>
		<p><?php esc_html_e( 'Templates apply only when an individual post or term has no SEO override. Leave a template empty to keep WordPress in control of that value.', 'core-blueprint-seo' ); ?></p>

		<form method="post" class="cb-core-form-scope">
			<?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME ); ?>
			<input type="hidden" name="cb_seo_action" value="save_metadata_templates">
			<input type="hidden" name="cb_seo_section" value="<?php echo esc_attr( $active_section ); ?>">

			<nav class="nav-tab-wrapper cb-core-tab-wrapper cb-seo-template-tabs" aria-label="<?php esc_attr_e( 'Metadata template sections', 'core-blueprint-seo' ); ?>" role="tablist" data-cb-seo-tabs>
				<?php self::render_tab( 'post-types', __( 'Post types', 'core-blueprint-seo' ), count( $post_types ), $post_type_configured, $active_section ); ?>
				<?php self::render_tab( 'taxonomies', __( 'Taxonomies', 'core-blueprint-seo' ), count( $taxonomies ), $taxonomy_configured, $active_section ); ?>
				<?php self::render_tab( 'variables', __( 'Template variables', 'core-blueprint-seo' ), null, null, $active_section ); ?>
			</nav>

			<section id="cb-seo-post-types-panel" class="cb-seo-template-panel" data-cb-seo-panel data-section="post-types" role="tabpanel" aria-labelledby="cb-seo-tab-post-types">
				<p class="cb-seo-panel-intro"><?php esc_html_e( 'Open only the content types you want to customize. Unconfigured content keeps WordPress defaults.', 'core-blueprint-seo' ); ?></p>
				<div class="cb-seo-template-list">
					<?php foreach ( $post_types as $slug => $object ) :
						$current = $settings['post_types'][ $slug ] ?? [ 'title' => '', 'description' => '' ];
						self::render_template_item(
							'post_types',
							(string) $slug,
							(string) $object->labels->singular_name,
							(string) $current['title'],
							(string) $current['description'],
							'%title% %separator% %site_name%',
							'%excerpt%'
						);
					endforeach; ?>
				</div>
			</section>

			<section id="cb-seo-taxonomies-panel" class="cb-seo-template-panel" data-cb-seo-panel data-section="taxonomies" role="tabpanel" aria-labelledby="cb-seo-tab-taxonomies">
				<p class="cb-seo-panel-intro"><?php esc_html_e( 'Taxonomy templates apply to public term archive pages when a term has no individual SEO override.', 'core-blueprint-seo' ); ?></p>
				<div class="cb-seo-template-list">
					<?php foreach ( $taxonomies as $slug => $object ) :
						$current = $settings['taxonomies'][ $slug ] ?? [ 'title' => '', 'description' => '' ];
						self::render_template_item(
							'taxonomies',
							(string) $slug,
							(string) $object->labels->singular_name,
							(string) $current['title'],
							(string) $current['description'],
							'%term% %separator% %site_name%',
							'%description%'
						);
					endforeach; ?>
				</div>
			</section>

			<section id="cb-seo-variables-panel" class="cb-seo-template-panel" data-cb-seo-panel data-section="variables" role="tabpanel" aria-labelledby="cb-seo-tab-variables">
				<?php echo self::variables_body(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- method escapes each cell. ?>
			</section>

			<div class="cb-seo-save-row">
				<button type="submit" class="button button-primary cb-core-button cb-core-button--primary"><?php esc_html_e( 'Save metadata templates', 'core-blueprint-seo' ); ?></button>
				<span class="description"><?php esc_html_e( 'All post-type and taxonomy templates are saved together.', 'core-blueprint-seo' ); ?></span>
			</div>
		</form>
		<?php
		return (string) ob_get_clean();
	}

	private static function render_tab( string $section, string $label, ?int $total, ?int $configured, string $active_section ): void {
		$active = $section === $active_section;
		?>
		<a
			id="cb-seo-tab-<?php echo esc_attr( $section ); ?>"
			class="nav-tab<?php echo $active ? ' nav-tab-active' : ''; ?>"
			href="#cb-seo-<?php echo esc_attr( $section ); ?>-panel"
			role="tab"
			aria-selected="<?php echo $active ? 'true' : 'false'; ?>"
			aria-controls="cb-seo-<?php echo esc_attr( $section ); ?>-panel"
			data-cb-seo-tab
			data-section="<?php echo esc_attr( $section ); ?>"
		>
			<?php echo esc_html( $label ); ?>
			<?php if ( null !== $total && null !== $configured ) : ?>
				<span class="cb-seo-tab-count"><?php echo esc_html( sprintf( '%d/%d', $configured, $total ) ); ?></span>
			<?php endif; ?>
		</a>
		<?php
	}

	private static function render_template_item( string $group, string $slug, string $label, string $title, string $description, string $title_placeholder, string $description_placeholder ): void {
		$configured = '' !== trim( $title ) || '' !== trim( $description );
		$item_id    = 'cb-seo-' . sanitize_html_class( $group . '-' . $slug );
		?>
		<details class="cb-core-disclosure cb-core-disclosure--compact cb-seo-template-item">
			<summary class="cb-core-disclosure__summary">
				<?php echo Icon::render( 'expand', [ 'size' => Icon::SIZE_COMPACT, 'class' => 'cb-core-disclosure__icon' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Foundation icon renderer is escape-clean. ?>
				<span class="cb-core-disclosure__title"><?php echo esc_html( $label ); ?></span>
				<code class="cb-seo-template-item__slug"><?php echo esc_html( $slug ); ?></code>
				<span class="cb-seo-template-item__status cb-core-disclosure__meta">
					<span class="cb-core-badge <?php echo $configured ? 'cb-core-badge-standard' : 'cb-core-badge-neutral'; ?>">
						<?php echo esc_html( $configured ? __( 'Custom', 'core-blueprint-seo' ) : __( 'WordPress default', 'core-blueprint-seo' ) ); ?>
					</span>
				</span>
			</summary>
			<div class="cb-core-disclosure__body">
				<div class="cb-core-field">
					<label class="cb-core-field__label" for="<?php echo esc_attr( $item_id . '-title' ); ?>"><?php esc_html_e( 'Title template', 'core-blueprint-seo' ); ?></label>
					<div class="cb-core-field__control">
						<input class="code" id="<?php echo esc_attr( $item_id . '-title' ); ?>" type="text" name="cb_seo_templates[<?php echo esc_attr( $group ); ?>][<?php echo esc_attr( $slug ); ?>][title]" value="<?php echo esc_attr( $title ); ?>" placeholder="<?php echo esc_attr( $title_placeholder ); ?>">
					</div>
					<p class="description"><?php esc_html_e( 'Leave empty to keep WordPress responsible for this title.', 'core-blueprint-seo' ); ?></p>
				</div>

				<div class="cb-core-field">
					<label class="cb-core-field__label" for="<?php echo esc_attr( $item_id . '-description' ); ?>"><?php esc_html_e( 'Description template', 'core-blueprint-seo' ); ?></label>
					<div class="cb-core-field__control">
						<textarea class="code" rows="3" id="<?php echo esc_attr( $item_id . '-description' ); ?>" name="cb_seo_templates[<?php echo esc_attr( $group ); ?>][<?php echo esc_attr( $slug ); ?>][description]" placeholder="<?php echo esc_attr( $description_placeholder ); ?>"><?php echo esc_textarea( $description ); ?></textarea>
					</div>
					<p class="description"><?php esc_html_e( 'Leave empty to emit no Core Blueprint meta description for this content type.', 'core-blueprint-seo' ); ?></p>
				</div>
			</div>
		</details>
		<?php
	}

	/** @param array<string,array{title?:string,description?:string}> $items */
	private static function configured_count( array $items ): int {
		$count = 0;
		foreach ( $items as $item ) {
			$title       = isset( $item['title'] ) ? trim( (string) $item['title'] ) : '';
			$description = isset( $item['description'] ) ? trim( (string) $item['description'] ) : '';
			if ( '' !== $title || '' !== $description ) {
				++$count;
			}
		}
		return $count;
	}

	private static function active_section(): string {
		$section = isset( $_GET['section'] ) ? sanitize_key( wp_unslash( $_GET['section'] ) ) : 'post-types'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only UI state.
		return in_array( $section, self::SECTIONS, true ) ? $section : 'post-types';
	}

	private static function variables_body(): string {
		$rows = [
			'%title%'       => __( 'Post title or term name.', 'core-blueprint-seo' ),
			'%site_name%'   => __( 'WordPress site title.', 'core-blueprint-seo' ),
			'%separator%'   => __( 'A typographic dash separator.', 'core-blueprint-seo' ),
			'%excerpt%'     => __( 'Explicit WordPress post excerpt. Builder content is intentionally not parsed.', 'core-blueprint-seo' ),
			'%post_type%'   => __( 'Singular post-type label.', 'core-blueprint-seo' ),
			'%term%'        => __( 'Current term name.', 'core-blueprint-seo' ),
			'%description%' => __( 'Current taxonomy term description.', 'core-blueprint-seo' ),
			'%taxonomy%'    => __( 'Singular taxonomy label.', 'core-blueprint-seo' ),
		];

		$out = '<p class="cb-seo-panel-intro">' . esc_html__( 'Use these variables inside title and description templates. They are resolved at render time.', 'core-blueprint-seo' ) . '</p>';
		$out .= '<table class="widefat cb-core-kv cb-seo-variable-table"><tbody>';
		foreach ( $rows as $token => $description ) {
			$out .= '<tr><th scope="row"><code>' . esc_html( $token ) . '</code></th><td>' . esc_html( $description ) . '</td></tr>';
		}
		$out .= '</tbody></table>';
		$out .= '<p class="description">' . esc_html__( 'Rendered-page content analysis remains a later stage and will use the live frontend document rather than builder storage.', 'core-blueprint-seo' ) . '</p>';
		return $out;
	}
}
