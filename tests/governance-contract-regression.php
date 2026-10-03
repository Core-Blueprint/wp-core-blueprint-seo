<?php
declare(strict_types=1);

$root = dirname( __DIR__ );

function cb_seo_governance_expect( bool $condition, string $message ): void {
	if ( $condition ) {
		return;
	}
	fwrite( STDERR, "SEO governance contract regression failed: {$message}\n" );
	exit( 1 );
}

$bootstrap = (string) file_get_contents( $root . '/src/Bootstrap.php' );
$audit     = (string) file_get_contents( $root . '/src/Governance/Audit.php' );
$state     = (string) file_get_contents( $root . '/src/State.php' );
$requirements = (string) file_get_contents( $root . '/src/Requirements.php' );
$runtime = '';
foreach ( [
	'src/Lifecycle.php',
	'src/Admin/SettingsPage.php',
	'src/Admin/Editor/SeoMetaBox.php',
	'src/Admin/Editor/TermFields.php',
	'src/State.php',
] as $relative ) {
	$runtime .= "\n" . (string) file_get_contents( $root . '/' . $relative );
}

foreach ( [
	'core_blueprint_register_extensions',
	'core_blueprint_register_settings',
	'core_blueprint_module_activation_definitions',
	'core_blueprint_module_status_definitions',
	'core_blueprint_dashboard_register_cards',
] as $hook ) {
	cb_seo_governance_expect( str_contains( $bootstrap, $hook ), 'Missing canonical Base hook: ' . $hook );
}
foreach ( [
	'cb_core_register_extensions',
	'cb_core_register_settings',
	'cb_core_module_activation_definitions',
	'cb_core_module_status_definitions',
	'cb_core_dashboard_register_cards',
	'cb_core_event_labels',
] as $legacy_hook ) {
	cb_seo_governance_expect( ! str_contains( $bootstrap, $legacy_hook ), 'Legacy Base hook returned: ' . $legacy_hook );
}

cb_seo_governance_expect( str_contains( $audit, 'EventRegistry::register' ), 'SEO must register governance events through EventRegistry.' );
cb_seo_governance_expect( str_contains( $audit, 'CoreAudit::record' ), 'SEO must write governance events through the public Audit facade.' );
cb_seo_governance_expect( ! str_contains( $state, 'AuditLog::log' ), 'SEO State must not call internal AuditLog directly.' );
cb_seo_governance_expect( str_contains( $requirements, '\\\\CoreBlueprint\\\\Core\\\\Governance\\\\Audit' ) && str_contains( $requirements, '\\\\CoreBlueprint\\\\Core\\\\Governance\\\\EventRegistry' ), 'SEO runtime gate must require public Governance contracts.' );
cb_seo_governance_expect( ! str_contains( $requirements, '\\\\CoreBlueprint\\\\Core\\\\Log\\\\AuditLog' ), 'SEO runtime gate must not require internal AuditLog.' );

foreach ( [
	'seo.subsystem.enabled',
	'seo.subsystem.disabled',
	'seo.extension.activated',
	'seo.metadata.settings.updated',
	'seo.object.metadata.updated',
	'seo.term.metadata.updated',
	'seo.indexing.settings.updated',
	'seo.metadata.imported',
] as $event_id ) {
	cb_seo_governance_expect( str_contains( $audit . $runtime, $event_id ), 'Missing canonical dotted event id: ' . $event_id );
}
foreach ( [
	'seo_subsystem_enabled',
	'seo_subsystem_disabled',
	'seo_extension_activated',
	'seo_extension_deactivated',
	'seo_metadata_settings_updated',
	'seo_object_metadata_updated',
	'seo_term_metadata_updated',
	'seo_object_indexing_updated',
	'seo_term_indexing_updated',
	'seo_object_canonical_updated',
	'seo_term_canonical_updated',
	'seo_object_social_updated',
	'seo_term_social_updated',
	'seo_social_settings_updated',
	'seo_schema_settings_updated',
	'seo_discovery_settings_updated',
	'seo_indexing_settings_updated',
	'seo_metadata_imported',
] as $legacy_event ) {
	cb_seo_governance_expect( ! str_contains( $runtime, $legacy_event ), 'Legacy underscore event id returned: ' . $legacy_event );
}

fwrite( STDOUT, "SEO governance contract regression PASS\n" );
