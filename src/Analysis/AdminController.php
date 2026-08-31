<?php
declare(strict_types=1);
/**
 * AJAX controller for on-demand rendered-page analysis.
 *
 * @package Core_Blueprint_SEO
 */

namespace CB\SEO\Analysis;

use CB\SEO\Admin\Editor\AnalysisPanel;

defined( 'ABSPATH' ) || exit;

final class AdminController {
	public static function boot(): void {
		add_action( 'wp_ajax_cb_seo_analyze_post', [ self::class, 'analyze' ] );
		add_action( 'save_post', [ self::class, 'invalidate_on_save' ], 99, 3 );
	}

	public static function analyze(): void {
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$nonce   = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( $post_id <= 0 || ! wp_verify_nonce( $nonce, 'cb_seo_analyze_post_' . $post_id ) ) {
			wp_send_json_error( [ 'message' => __( 'The analysis request could not be verified.', 'core-blueprint-seo' ) ], 403 );
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( [ 'message' => __( 'You are not allowed to analyze this content.', 'core-blueprint-seo' ) ], 403 );
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			wp_send_json_error( [ 'message' => __( 'The content could not be found.', 'core-blueprint-seo' ) ], 404 );
		}
		$post_type = get_post_type_object( $post->post_type );
		if ( ! $post_type || ! $post_type->public || ! $post_type->show_ui ) {
			wp_send_json_error( [ 'message' => __( 'This content type is not eligible for public SEO analysis.', 'core-blueprint-seo' ) ], 400 );
		}

		$focus = isset( $_POST['focus_keyword'] ) ? sanitize_text_field( wp_unslash( $_POST['focus_keyword'] ) ) : '';
		Repository::save_focus_keyword( $post_id, $focus );

		$result = Analyzer::analyze( $post, $focus );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => $result->get_error_message() ], 422 );
		}

		$snapshot = [
			'version'           => 1,
			'analyzed_at'       => time(),
			'post_modified_gmt' => (string) $post->post_modified_gmt,
			'result'            => $result,
		];
		Repository::save_snapshot( $post_id, $snapshot );

		wp_send_json_success(
			[
				'html' => AnalysisPanel::results_html( $post, $snapshot ),
			]
		);
	}

	public static function invalidate_on_save( int $post_id, \WP_Post $post, bool $update ): void {
		unset( $update );
		if ( 'attachment' === $post->post_type || wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		Repository::delete_snapshot( $post_id );
	}
}
