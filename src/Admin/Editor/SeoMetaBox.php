<?php
declare(strict_types=1);
/**
 * Native WordPress SEO fields on post edit screens.
 *
 * @package Core_Blueprint_SEO
 */

namespace CB\SEO\Admin\Editor;

use CB\SEO\Governance\Audit;
use CB\SEO\Analysis\Repository as AnalysisRepository;
use CB\SEO\Metadata\Repository;
use CB\SEO\Metadata\Resolver;
use WP_Post;

defined( 'ABSPATH' ) || exit;

final class SeoMetaBox {
	private const NONCE_ACTION = 'cb_seo_save_post_metadata';
	private const NONCE_NAME   = 'cb_seo_post_nonce';

	public static function boot(): void {
		add_action( 'add_meta_boxes', [ self::class, 'register' ] );
		add_action( 'save_post', [ self::class, 'save' ], 10, 2 );
	}

	public static function register(): void {
		$post_types = get_post_types( [ 'show_ui' => true ], 'objects' );
		foreach ( $post_types as $post_type => $object ) {
			if ( 'attachment' === $post_type || ! $object->public ) {
				continue;
			}

			add_meta_box(
				'cb-seo-search-appearance',
				__( 'SEO — Search appearance', 'core-blueprint-seo' ),
				[ self::class, 'render_search_appearance' ],
				$post_type,
				'normal',
				'default'
			);
			add_meta_box(
				'cb-seo-analysis',
				__( 'SEO — Analysis', 'core-blueprint-seo' ),
				[ self::class, 'render_analysis' ],
				$post_type,
				'normal',
				'default'
			);
			add_meta_box(
				'cb-seo-indexing',
				__( 'SEO — Indexing', 'core-blueprint-seo' ),
				[ self::class, 'render_indexing' ],
				$post_type,
				'normal',
				'default'
			);
			add_meta_box(
				'cb-seo-social-appearance',
				__( 'SEO — Social appearance', 'core-blueprint-seo' ),
				[ self::class, 'render_social_appearance' ],
				$post_type,
				'normal',
				'default'
			);
		}
	}

	public static function render_search_appearance( WP_Post $post ): void {
		$meta = Repository::post( (int) $post->ID );
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );
		?>
		<div class="cb-seo-native-panel cb-seo-native-panel--search">
			<p>
				<label for="cb-seo-title"><strong><?php esc_html_e( 'SEO title', 'core-blueprint-seo' ); ?></strong></label><br>
				<input type="text" class="widefat" id="cb-seo-title" name="cb_seo_title" value="<?php echo esc_attr( $meta['title'] ); ?>" placeholder="<?php esc_attr_e( 'Use the configured post-type template', 'core-blueprint-seo' ); ?>">
			</p>
			<p class="description"><?php esc_html_e( 'Leave empty to use the global template for this post type. If no template exists, WordPress keeps its normal document title.', 'core-blueprint-seo' ); ?></p>

			<p>
				<label for="cb-seo-description"><strong><?php esc_html_e( 'Meta description', 'core-blueprint-seo' ); ?></strong></label><br>
				<textarea class="widefat" rows="4" id="cb-seo-description" name="cb_seo_description" placeholder="<?php esc_attr_e( 'Use the configured post-type template', 'core-blueprint-seo' ); ?>"><?php echo esc_textarea( $meta['description'] ); ?></textarea>
			</p>
			<p class="description"><?php esc_html_e( 'Leave empty to use the global template. Core Blueprint emits no fallback description when neither an override nor a template is configured.', 'core-blueprint-seo' ); ?></p>

			<?php if ( 'auto-draft' !== $post->post_status ) : ?>
				<div class="cb-seo-native-panel__preview">
					<p><strong><?php esc_html_e( 'Resolved preview', 'core-blueprint-seo' ); ?></strong></p>
					<p><code><?php echo esc_html( Resolver::post_title( $post ) ?? __( 'WordPress default title', 'core-blueprint-seo' ) ); ?></code></p>
					<p class="description"><?php echo esc_html( Resolver::post_description( $post ) ?? __( 'No Core Blueprint meta description', 'core-blueprint-seo' ) ); ?></p>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	public static function render_analysis( WP_Post $post ): void {
		?>
		<div class="cb-seo-native-panel cb-seo-native-panel--analysis">
			<?php AnalysisPanel::render( $post ); ?>
		</div>
		<?php
	}

	public static function render_indexing( WP_Post $post ): void {
		$meta = Repository::post( (int) $post->ID );
		?>
		<div class="cb-seo-native-panel cb-seo-native-panel--indexing">
			<fieldset class="cb-seo-native-panel__directives">
				<legend><strong><?php esc_html_e( 'Search engine directives', 'core-blueprint-seo' ); ?></strong></legend>
				<p class="description"><?php esc_html_e( 'These are restrictive per-content overrides. Leave them unchecked to keep the normal WordPress or global SEO policy.', 'core-blueprint-seo' ); ?></p>
				<?php self::render_checkbox( 'cb-seo-noindex', 'cb_seo_noindex', $meta['noindex'], __( 'Block this content from search engine indexes (noindex)', 'core-blueprint-seo' ) ); ?>
				<?php self::render_checkbox( 'cb-seo-nofollow', 'cb_seo_nofollow', $meta['nofollow'], __( 'Do not ask search engines to follow links on this content (nofollow)', 'core-blueprint-seo' ) ); ?>
				<?php self::render_checkbox( 'cb-seo-noimageindex', 'cb_seo_noimageindex', $meta['noimageindex'], __( 'Do not index images from this content (noimageindex)', 'core-blueprint-seo' ) ); ?>
				<?php self::render_checkbox( 'cb-seo-noarchive', 'cb_seo_noarchive', $meta['noarchive'], __( 'Do not provide a cached copy of this content (noarchive)', 'core-blueprint-seo' ) ); ?>
				<?php self::render_checkbox( 'cb-seo-nosnippet', 'cb_seo_nosnippet', $meta['nosnippet'], __( 'Do not show a text snippet for this content (nosnippet)', 'core-blueprint-seo' ) ); ?>
				<p class="description"><?php esc_html_e( 'Content marked noindex is also excluded from the WordPress XML sitemap while Core Blueprint SEO is active.', 'core-blueprint-seo' ); ?></p>
			</fieldset>

			<div class="cb-seo-native-panel__canonical">
				<label for="cb-seo-canonical"><strong><?php esc_html_e( 'Canonical URL', 'core-blueprint-seo' ); ?></strong></label><br>
				<input type="url" class="widefat" id="cb-seo-canonical" name="cb_seo_canonical" value="<?php echo esc_attr( $meta['canonical'] ); ?>" placeholder="<?php esc_attr_e( 'Use the normal WordPress canonical', 'core-blueprint-seo' ); ?>">
				<p class="description"><?php esc_html_e( 'Optional absolute HTTP(S) URL. Leave empty to keep the normal WordPress/site canonical behaviour.', 'core-blueprint-seo' ); ?></p>
			</div>
		</div>
		<?php
	}

	public static function render_social_appearance( WP_Post $post ): void {
		$meta = Repository::post( (int) $post->ID );
		?>
		<div class="cb-seo-native-panel cb-seo-native-panel--social">
			<p class="description"><?php esc_html_e( 'Optional Open Graph and X/Twitter overrides. Empty fields inherit the resolved SEO metadata.', 'core-blueprint-seo' ); ?></p>
			<p>
				<label for="cb-seo-social-title"><strong><?php esc_html_e( 'Social title', 'core-blueprint-seo' ); ?></strong></label><br>
				<input type="text" class="widefat" id="cb-seo-social-title" name="cb_seo_social_title" value="<?php echo esc_attr( $meta['social_title'] ); ?>" placeholder="<?php esc_attr_e( 'Use the resolved SEO title', 'core-blueprint-seo' ); ?>">
			</p>
			<p>
				<label for="cb-seo-social-description"><strong><?php esc_html_e( 'Social description', 'core-blueprint-seo' ); ?></strong></label><br>
				<textarea class="widefat" rows="3" id="cb-seo-social-description" name="cb_seo_social_description" placeholder="<?php esc_attr_e( 'Use the resolved SEO description', 'core-blueprint-seo' ); ?>"><?php echo esc_textarea( $meta['social_description'] ); ?></textarea>
			</p>
			<?php self::render_image_picker( 'cb_seo_social_image_id', $meta['social_image_id'], __( 'Social image', 'core-blueprint-seo' ) ); ?>
		</div>
		<?php
	}

	public static function save( int $post_id, WP_Post $post ): void {
		if ( 'attachment' === $post->post_type || wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		if ( ! isset( $_POST[ self::NONCE_NAME ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) ), self::NONCE_ACTION ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		AnalysisRepository::save_focus_keyword( $post_id, (string) ( $_POST['cb_seo_focus_keyword'] ?? '' ) );

		$before = Repository::post( $post_id );
		$changed = Repository::save_post(
			$post_id,
			[
				'title'        => $_POST['cb_seo_title'] ?? '',
				'description'  => $_POST['cb_seo_description'] ?? '',
				'canonical'          => $_POST['cb_seo_canonical'] ?? '',
				'social_title'       => $_POST['cb_seo_social_title'] ?? '',
				'social_description' => $_POST['cb_seo_social_description'] ?? '',
				'social_image_id'    => $_POST['cb_seo_social_image_id'] ?? 0,
				'noindex'      => isset( $_POST['cb_seo_noindex'] ),
				'nofollow'     => isset( $_POST['cb_seo_nofollow'] ),
				'noimageindex' => isset( $_POST['cb_seo_noimageindex'] ),
				'noarchive'    => isset( $_POST['cb_seo_noarchive'] ),
				'nosnippet'    => isset( $_POST['cb_seo_nosnippet'] ),
			]
		);

		if ( ! $changed ) {
			return;
		}

		$after = Repository::post( $post_id );
		if ( $before['title'] !== $after['title'] || $before['description'] !== $after['description'] ) {
			Audit::log( 'seo_object_metadata_updated', [ 'post_id' => $post_id, 'post_type' => $post->post_type ] );
		}
		if ( $before['canonical'] !== $after['canonical'] ) {
			Audit::log(
				'seo_object_canonical_updated',
				[
					'post_id'   => $post_id,
					'post_type' => $post->post_type,
					'canonical' => $after['canonical'],
				]
			);
		}
		if ( $before['social_title'] !== $after['social_title'] || $before['social_description'] !== $after['social_description'] || $before['social_image_id'] !== $after['social_image_id'] ) {
			Audit::log(
				'seo_object_social_updated',
				[
					'post_id'   => $post_id,
					'post_type' => $post->post_type,
				]
			);
		}
		if ( self::robots_changed( $before, $after ) ) {
			Audit::log(
				'seo_object_indexing_updated',
				[
					'post_id'   => $post_id,
					'post_type' => $post->post_type,
					'robots'    => Repository::robots_from_values( $after ),
				]
			);
		}
	}

	private static function render_image_picker( string $name, int $attachment_id, string $label ): void {
		$url = $attachment_id > 0 ? (string) wp_get_attachment_image_url( $attachment_id, 'medium' ) : '';
		?>
		<div class="cb-seo-image-field" data-cb-seo-image-field>
			<label><strong><?php echo esc_html( $label ); ?></strong></label>
			<input type="hidden" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( (string) $attachment_id ); ?>" data-cb-seo-image-id>
			<div class="cb-seo-image-field__preview" data-cb-seo-image-preview<?php echo '' === $url ? ' hidden' : ''; ?>>
				<?php if ( '' !== $url ) : ?><img src="<?php echo esc_url( $url ); ?>" alt=""><?php endif; ?>
			</div>
			<p class="cb-seo-image-field__actions">
				<button type="button" class="button" data-cb-seo-image-select><?php esc_html_e( 'Choose image', 'core-blueprint-seo' ); ?></button>
				<button type="button" class="button-link-delete" data-cb-seo-image-remove<?php echo $attachment_id <= 0 ? ' hidden' : ''; ?>><?php esc_html_e( 'Remove image', 'core-blueprint-seo' ); ?></button>
			</p>
			<p class="description"><?php esc_html_e( 'If empty, Core Blueprint uses the featured image and then the configured default social image.', 'core-blueprint-seo' ); ?></p>
		</div>
		<?php
	}

	private static function render_checkbox( string $id, string $name, bool $checked, string $label ): void {
		?>
		<p>
			<label for="<?php echo esc_attr( $id ); ?>">
				<input type="checkbox" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" value="1" <?php checked( $checked ); ?>>
				<?php echo esc_html( $label ); ?>
			</label>
		</p>
		<?php
	}

	/** @param array<string,mixed> $before
	 *  @param array<string,mixed> $after
	 */
	private static function robots_changed( array $before, array $after ): bool {
		return Repository::robots_from_values( $before ) !== Repository::robots_from_values( $after );
	}
}
