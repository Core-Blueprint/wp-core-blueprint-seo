<?php
declare(strict_types=1);
/**
 * SEO-specific audit helpers.
 *
 * @package Core_Blueprint_SEO
 */

namespace CB\SEO\Governance;

use CoreBlueprint\Core\Governance\Audit as CoreAudit;
use CoreBlueprint\Core\Governance\EventRegistry;

defined( 'ABSPATH' ) || exit;

final class Audit {
	public static function init(): void {
		add_action( 'init', [ self::class, 'register_events' ], 1 );
	}

	public static function register_events(): void {
		if ( ! class_exists( EventRegistry::class ) ) {
			return;
		}
		foreach ( self::event_definitions() as $id => $label ) {
			EventRegistry::register( [
				'id'    => $id,
				'label' => $label,
			] );
		}
	}

	/** @return array<string,string> */
	private static function event_definitions(): array {
		return [
			'seo.subsystem.enabled'          => __( 'SEO enabled', 'core-blueprint-seo' ),
			'seo.subsystem.disabled'         => __( 'SEO disabled', 'core-blueprint-seo' ),
			'seo.extension.activated'        => __( 'SEO extension activated', 'core-blueprint-seo' ),
			'seo.extension.deactivated'      => __( 'SEO extension deactivated', 'core-blueprint-seo' ),
			'seo.metadata.settings.updated'  => __( 'SEO metadata templates updated', 'core-blueprint-seo' ),
			'seo.object.metadata.updated'    => __( 'SEO content metadata updated', 'core-blueprint-seo' ),
			'seo.term.metadata.updated'      => __( 'SEO term metadata updated', 'core-blueprint-seo' ),
			'seo.object.indexing.updated'    => __( 'SEO content indexing directives updated', 'core-blueprint-seo' ),
			'seo.term.indexing.updated'      => __( 'SEO term indexing directives updated', 'core-blueprint-seo' ),
			'seo.object.canonical.updated'   => __( 'SEO content canonical updated', 'core-blueprint-seo' ),
			'seo.term.canonical.updated'     => __( 'SEO term canonical updated', 'core-blueprint-seo' ),
			'seo.object.social.updated'      => __( 'SEO content social metadata updated', 'core-blueprint-seo' ),
			'seo.term.social.updated'        => __( 'SEO term social metadata updated', 'core-blueprint-seo' ),
			'seo.social.settings.updated'    => __( 'SEO social settings updated', 'core-blueprint-seo' ),
			'seo.schema.settings.updated'    => __( 'SEO structured data settings updated', 'core-blueprint-seo' ),
			'seo.discovery.settings.updated' => __( 'SEO AI discovery settings updated', 'core-blueprint-seo' ),
			'seo.indexing.settings.updated'  => __( 'SEO indexing policy updated', 'core-blueprint-seo' ),
			'seo.metadata.imported'          => __( 'SEO metadata imported', 'core-blueprint-seo' ),
		];
	}

	/** @param array<string,mixed> $context */
	public static function log( string $event, array $context = [] ): void {
		$context['actor'] ??= 'user:' . get_current_user_id();
		$context['version'] = CB_SEO_VERSION;
		CoreAudit::record( $event, 'notice', $context );
	}
}
