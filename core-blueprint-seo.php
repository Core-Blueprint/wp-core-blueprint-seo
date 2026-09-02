<?php
/**
 * Plugin Name: Core Blueprint SEO
 * Plugin URI:  https://coreblueprint.io
 * Description: Governed SEO metadata and indexing controls for Core Blueprint with WordPress-first fallbacks and builder-independent administration.
 * Version:     1.0.0-rc3
 * Author:      Core Blueprint
 * Author URI: https://coreblueprint.io
 * License:     GPL-2.0+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: core-blueprint-seo
 * Domain Path: /languages
 * Requires at least: 7.0
 * Requires PHP:      8.4
 *
 * @package Core_Blueprint_SEO
 */

defined( 'ABSPATH' ) || exit;

if ( defined( 'CB_SEO_FILE' ) || defined( 'CB_SEO_VERSION' ) ) {
	$loaded_file = defined( 'CB_SEO_FILE' ) ? (string) CB_SEO_FILE : '';
	if ( '' !== $loaded_file && $loaded_file !== __FILE__ ) {
		error_log( sprintf( '[Core Blueprint SEO] Duplicate plugin load prevented. Active entrypoint: %s; skipped: %s', $loaded_file, __FILE__ ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- bootstrap diagnostic.
	}
	return;
}

define( 'CB_SEO_VERSION', '1.0.0-rc3' );
define( 'CB_SEO_FILE', __FILE__ );
define( 'CB_SEO_DIR', plugin_dir_path( __FILE__ ) );
define( 'CB_SEO_URL', plugin_dir_url( __FILE__ ) );
define( 'CB_SEO_BASENAME', plugin_basename( __FILE__ ) );
define( 'CB_SEO_ENABLED_OPT', 'cb_seo_enabled' );
define( 'CB_SEO_METADATA_SETTINGS_OPT', 'cb_seo_metadata_settings' );
define( 'CB_SEO_SOCIAL_SETTINGS_OPT', 'cb_seo_social_settings' );
define( 'CB_SEO_SCHEMA_SETTINGS_OPT', 'cb_seo_schema_settings' );
define( 'CB_SEO_DISCOVERY_SETTINGS_OPT', 'cb_seo_discovery_settings' );
define( 'CB_SEO_INDEXING_SETTINGS_OPT', 'cb_seo_indexing_settings' );

spl_autoload_register( static function ( string $class ): void {
	$prefix = 'CB\\SEO\\';
	$length = strlen( $prefix );

	if ( 0 !== strncmp( $class, $prefix, $length ) ) {
		return;
	}

	$relative = substr( $class, $length );
	$file     = CB_SEO_DIR . 'src/' . str_replace( '\\', '/', $relative ) . '.php';

	if ( is_file( $file ) ) {
		require_once $file;
	}
} );

register_activation_hook( __FILE__, [ \CB\SEO\Lifecycle::class, 'activate' ] );
register_deactivation_hook( __FILE__, [ \CB\SEO\Lifecycle::class, 'deactivate' ] );

// WordPress 6.7+ requires translation loading on init or later.
add_action( 'init', static function (): void {
	load_plugin_textdomain(
		'core-blueprint-seo',
		false,
		dirname( plugin_basename( CB_SEO_FILE ) ) . '/languages'
	);
}, 1 );

add_action( 'plugins_loaded', static function (): void {
	if ( ! \CB\SEO\Requirements::base_ready() ) {
		if ( is_admin() ) {
			add_action( 'admin_notices', static function (): void {
				echo '<div class="notice notice-error"><p><strong>Core Blueprint SEO:</strong> ';
				echo esc_html__( 'A compatible Core Blueprint Base installation with API 1.0 or newer is required. Activate or update Core Blueprint Base first.', 'core-blueprint-seo' );
				echo '</p></div>';
			} );
		}
		return;
	}

	\CB\SEO\Bootstrap::boot();
}, 1 );
