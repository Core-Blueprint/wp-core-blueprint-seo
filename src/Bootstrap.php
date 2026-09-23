<?php
declare(strict_types=1);
/**
 * Wires the SEO extension into Core Blueprint Base.
 *
 * @package Core_Blueprint_SEO
 */

namespace CB\SEO;

use CB\Core\Admin\SettingsRegistry;
use CB\Core\Dashboard\CardRegistry;
use CB\Core\ExtensionRegistry;
use CB\SEO\Admin\SettingsPage;
use CB\SEO\Admin\Assets;
use CB\SEO\Admin\Editor\SeoMetaBox;
use CB\SEO\Admin\Editor\TermFields;
use CB\SEO\Compatibility\SeoPluginConflictDetector;
use CB\SEO\Compatibility\RuntimeGate;
use CB\SEO\Metadata\Runtime;
use CB\SEO\Indexing\Runtime as IndexingRuntime;
use CB\SEO\Social\Runtime as SocialRuntime;
use CB\SEO\Schema\Runtime as SchemaRuntime;
use CB\SEO\Discovery\Runtime as DiscoveryRuntime;
use CB\SEO\Analysis\AdminController as AnalysisAdminController;

defined( 'ABSPATH' ) || exit;

final class Bootstrap {

	public static function boot(): void {
		Lifecycle::maybe_upgrade();

		// The settings surface remains available while SEO is disabled because the
		// Base dashboard controls activation through the canonical State.
		add_action( 'cb_core_register_extensions', [ self::class, 'register_extension' ] );
		add_action( 'cb_core_register_settings', [ self::class, 'register_settings' ] );
		add_filter( 'plugin_action_links_' . CB_SEO_BASENAME, [ SettingsPage::class, 'plugin_action_links' ] );
		add_action( 'admin_init', [ SettingsPage::class, 'handle_save' ] );
		add_action( 'admin_enqueue_scripts', [ Assets::class, 'enqueue' ] );
		SeoMetaBox::boot();
		TermFields::boot();
		AnalysisAdminController::boot();

		// Public Base Foundation boundaries consumed by extensions.
		add_filter( 'cb_core_module_activation_definitions', [ self::class, 'register_module_activation' ] );
		add_filter( 'cb_core_module_status_definitions', [ self::class, 'register_status_definition' ] );
		add_filter( 'cb_core_event_labels', [ self::class, 'register_event_labels' ] );
		add_action( 'cb_core_dashboard_register_cards', [ self::class, 'register_dashboard_shortcuts' ] );

		// Discovery owns a stable route even while dormant so stale rewrite rules
		// can never fall through to unrelated WordPress content.
		DiscoveryRuntime::boot();

		// Frontend metadata output is atomically governed by the same master state.
		if ( ! RuntimeGate::frontend_allowed() ) {
			return;
		}

		Runtime::boot();
		IndexingRuntime::boot();
		SocialRuntime::boot();
		SchemaRuntime::boot();
	}

	public static function register_extension(): void {
		ExtensionRegistry::register( [
			'id'           => 'core-blueprint-seo',
			'plugin_file'  => CB_SEO_BASENAME,
			'requires_api'  => CB_SEO_REQUIRED_API,
			'requires_base' => CB_SEO_REQUIRED_BASE,
			'menu_url'     => SettingsRegistry::url( 'core-blueprint-seo' ),
			'status_id'    => 'seo',
		] );
	}

	public static function register_settings(): void {
		SettingsRegistry::register(
			'core-blueprint-seo',
			[
				'label'        => __( 'SEO', 'core-blueprint-seo' ),
				'description'  => __( 'Governed search metadata with WordPress-first fallbacks and builder-independent administration.', 'core-blueprint-seo' ),
				'group'        => SettingsRegistry::GROUP_CONTENT_PUBLISHING,
				'capability'   => 'manage_options',
				'renderer'     => [ SettingsPage::class, 'render' ],
				'requirements' => [
					'components' => [
						'disclosure',
						'notices',
						'nav-tabs',
						'cards',
						'fields',
						'form-controls',
						'badges',
						'kv-table',
					],
				],
			]
		);
	}

	public static function register_dashboard_shortcuts(): void {
		if ( ! class_exists( CardRegistry::class ) ) {
			return;
		}

		CardRegistry::register_shortcuts( 'core-blueprint-seo', [
			[
				'id'         => 'indexing',
				'label'      => __( 'Indexing', 'core-blueprint-seo' ),
				'url'        => SettingsRegistry::url( 'core-blueprint-seo', [ 'tab' => 'indexing' ] ),
				'capability' => 'manage_options',
				'order'      => 10,
			],
			[
				'id'         => 'social-schema',
				'label'      => __( 'Social & Schema', 'core-blueprint-seo' ),
				'url'        => SettingsRegistry::url( 'core-blueprint-seo', [ 'tab' => 'social-schema' ] ),
				'capability' => 'manage_options',
				'order'      => 20,
			],
			[
				'id'         => 'ai-discovery',
				'label'      => __( 'AI Discovery', 'core-blueprint-seo' ),
				'url'        => SettingsRegistry::url( 'core-blueprint-seo', [ 'tab' => 'ai-discovery' ] ),
				'capability' => 'manage_options',
				'order'      => 30,
			],
			[
				'id'         => 'import',
				'label'      => __( 'Import', 'core-blueprint-seo' ),
				'url'        => SettingsRegistry::url( 'core-blueprint-seo', [ 'tab' => 'import' ] ),
				'capability' => 'manage_options',
				'order'      => 40,
			],
		] );
	}

	/** @param array<string,array{state:class-string,capability:string}> $definitions */
	public static function register_module_activation( array $definitions ): array {
		$definitions['seo'] = [
			'state'      => State::class,
			'capability' => 'manage_options',
		];
		return $definitions;
	}

	/** @param array<string,array<string,mixed>> $definitions
	 *  @return array<string,array<string,mixed>>
	 */
	public static function register_status_definition( array $definitions ): array {
		$definitions['seo'] = [
			'provider' => [ self::class, 'extension_status' ],
			'label'    => __( 'SEO', 'core-blueprint-seo' ),
			'url'      => SettingsRegistry::url( 'core-blueprint-seo' ),
		];
		return $definitions;
	}

	/** @return array{state:string,detail:string,url:string} */
	public static function extension_status(): array {
		$url = SettingsRegistry::url( 'core-blueprint-seo' );

		if ( ! State::is_enabled() ) {
			return [
				'state'  => 'off',
				'detail' => __( 'SEO disabled', 'core-blueprint-seo' ),
				'url'    => $url,
			];
		}

		if ( SeoPluginConflictDetector::active_conflicts() ) {
			return [
				'state'  => 'warn',
				'detail' => __( 'Another SEO plugin is active', 'core-blueprint-seo' ),
				'url'    => $url,
			];
		}

		return [
			'state'  => 'ok',
			'detail' => __( 'SEO enabled', 'core-blueprint-seo' ),
			'url'    => $url,
		];
	}

	/** @param array<string,string> $labels */
	public static function register_event_labels( array $labels ): array {
		// Defensive: AuditLog can be touched by maintenance code before init.
		// Keep the labels available without triggering WordPress 6.7+ early
		// just-in-time translation loading.
		$translate = did_action( 'init' ) > 0;
		$labels['seo_subsystem_enabled']     = $translate ? __( 'SEO enabled', 'core-blueprint-seo' ) : 'SEO enabled';
		$labels['seo_subsystem_disabled']    = $translate ? __( 'SEO disabled', 'core-blueprint-seo' ) : 'SEO disabled';
		$labels['seo_extension_activated']   = $translate ? __( 'SEO extension activated', 'core-blueprint-seo' ) : 'SEO extension activated';
		$labels['seo_extension_deactivated'] = $translate ? __( 'SEO extension deactivated', 'core-blueprint-seo' ) : 'SEO extension deactivated';
		$labels['seo_metadata_settings_updated'] = $translate ? __( 'SEO metadata templates updated', 'core-blueprint-seo' ) : 'SEO metadata templates updated';
		$labels['seo_object_metadata_updated']   = $translate ? __( 'SEO content metadata updated', 'core-blueprint-seo' ) : 'SEO content metadata updated';
		$labels['seo_term_metadata_updated']      = $translate ? __( 'SEO term metadata updated', 'core-blueprint-seo' ) : 'SEO term metadata updated';
		$labels['seo_object_indexing_updated']    = $translate ? __( 'SEO content indexing directives updated', 'core-blueprint-seo' ) : 'SEO content indexing directives updated';
		$labels['seo_term_indexing_updated']      = $translate ? __( 'SEO term indexing directives updated', 'core-blueprint-seo' ) : 'SEO term indexing directives updated';
		$labels['seo_object_canonical_updated']  = $translate ? __( 'SEO content canonical updated', 'core-blueprint-seo' ) : 'SEO content canonical updated';
		$labels['seo_term_canonical_updated']    = $translate ? __( 'SEO term canonical updated', 'core-blueprint-seo' ) : 'SEO term canonical updated';
		$labels['seo_object_social_updated']      = $translate ? __( 'SEO content social metadata updated', 'core-blueprint-seo' ) : 'SEO content social metadata updated';
		$labels['seo_term_social_updated']        = $translate ? __( 'SEO term social metadata updated', 'core-blueprint-seo' ) : 'SEO term social metadata updated';
		$labels['seo_social_settings_updated']    = $translate ? __( 'SEO social settings updated', 'core-blueprint-seo' ) : 'SEO social settings updated';
		$labels['seo_schema_settings_updated']    = $translate ? __( 'SEO structured data settings updated', 'core-blueprint-seo' ) : 'SEO structured data settings updated';
		$labels['seo_discovery_settings_updated'] = $translate ? __( 'SEO AI discovery settings updated', 'core-blueprint-seo' ) : 'SEO AI discovery settings updated';
		$labels['seo_indexing_settings_updated']  = $translate ? __( 'SEO indexing policy updated', 'core-blueprint-seo' ) : 'SEO indexing policy updated';
		$labels['seo_metadata_imported']          = $translate ? __( 'SEO metadata imported', 'core-blueprint-seo' ) : 'SEO metadata imported';
		return $labels;
	}
}
