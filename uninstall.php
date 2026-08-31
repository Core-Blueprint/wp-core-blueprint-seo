<?php
/**
 * Core Blueprint SEO uninstall cleanup.
 *
 * @package Core_Blueprint_SEO
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'cb_seo_enabled' );
delete_option( 'cb_seo_installed_version' );
delete_option( 'cb_seo_metadata_settings' );
delete_option( 'cb_seo_social_settings' );
delete_option( 'cb_seo_schema_settings' );
delete_option( 'cb_seo_discovery_settings' );
delete_option( 'cb_seo_indexing_settings' );
delete_option( 'cb_seo_llms_rewrite_version' );

delete_post_meta_by_key( '_cb_seo_title' );
delete_post_meta_by_key( '_cb_seo_description' );
delete_post_meta_by_key( '_cb_seo_canonical' );
delete_metadata( 'term', 0, '_cb_seo_title', '', true );
delete_metadata( 'term', 0, '_cb_seo_description', '', true );
delete_metadata( 'term', 0, '_cb_seo_canonical', '', true );

delete_post_meta_by_key( '_cb_seo_noindex' );
delete_metadata( 'term', 0, '_cb_seo_noindex', '', true );
delete_post_meta_by_key( '_cb_seo_nofollow' );
delete_metadata( 'term', 0, '_cb_seo_nofollow', '', true );
delete_post_meta_by_key( '_cb_seo_noimageindex' );
delete_metadata( 'term', 0, '_cb_seo_noimageindex', '', true );
delete_post_meta_by_key( '_cb_seo_noarchive' );
delete_metadata( 'term', 0, '_cb_seo_noarchive', '', true );
delete_post_meta_by_key( '_cb_seo_nosnippet' );
delete_metadata( 'term', 0, '_cb_seo_nosnippet', '', true );

delete_post_meta_by_key( '_cb_seo_social_title' );
delete_post_meta_by_key( '_cb_seo_social_description' );
delete_post_meta_by_key( '_cb_seo_social_image_id' );
delete_post_meta_by_key( '_cb_seo_focus_keyword' );
delete_post_meta_by_key( '_cb_seo_analysis_snapshot' );
delete_metadata( 'term', 0, '_cb_seo_social_title', '', true );
delete_metadata( 'term', 0, '_cb_seo_social_description', '', true );
delete_metadata( 'term', 0, '_cb_seo_social_image_id', '', true );

if ( function_exists( 'flush_rewrite_rules' ) ) {
	flush_rewrite_rules( false );
}
