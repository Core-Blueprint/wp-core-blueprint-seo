<?php
declare(strict_types=1);
/**
 * Native WordPress SEO fields on public taxonomy term screens.
 *
 * @package Core_Blueprint_SEO
 */

namespace CB\SEO\Admin\Editor;

use CB\SEO\Governance\Audit;
use CB\SEO\Metadata\Repository;
use WP_Term;

defined( 'ABSPATH' ) || exit;

final class TermFields {
	private const NONCE_ACTION = 'cb_seo_save_term_metadata';
	private const NONCE_NAME   = 'cb_seo_term_nonce';

	public static function boot(): void {
		add_action( 'admin_init', [ self::class, 'register_taxonomies' ] );
	}

	public static function register_taxonomies(): void {
		$taxonomies = get_taxonomies( [ 'show_ui' => true, 'public' => true ], 'names' );
		foreach ( $taxonomies as $taxonomy ) {
			add_action( $taxonomy . '_add_form_fields', [ self::class, 'render_add' ] );
			add_action( $taxonomy . '_edit_form_fields', [ self::class, 'render_edit' ] );
			add_action( 'created_' . $taxonomy, [ self::class, 'save' ] );
			add_action( 'edited_' . $taxonomy, [ self::class, 'save' ] );
		}
	}

	public static function render_add(): void {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );
		?>
		<div class="form-field term-cb-seo-title-wrap">
			<label for="cb-seo-term-title"><?php esc_html_e( 'SEO title', 'core-blueprint-seo' ); ?></label>
			<input type="text" id="cb-seo-term-title" name="cb_seo_title" value="">
			<p><?php esc_html_e( 'Optional. Leave empty to use the taxonomy template or the normal WordPress title.', 'core-blueprint-seo' ); ?></p>
		</div>
		<div class="form-field term-cb-seo-description-wrap">
			<label for="cb-seo-term-description"><?php esc_html_e( 'Meta description', 'core-blueprint-seo' ); ?></label>
			<textarea id="cb-seo-term-description" name="cb_seo_description" rows="4"></textarea>
			<p><?php esc_html_e( 'Optional. Leave empty to use the taxonomy template.', 'core-blueprint-seo' ); ?></p>
		</div>
		<div class="form-field term-cb-seo-robots-wrap">
			<label><?php esc_html_e( 'Search engine directives', 'core-blueprint-seo' ); ?></label>
			<?php self::render_add_checkbox( 'cb-seo-term-noindex', 'cb_seo_noindex', __( 'Block this term archive from search engine indexes (noindex)', 'core-blueprint-seo' ) ); ?>
			<?php self::render_add_checkbox( 'cb-seo-term-nofollow', 'cb_seo_nofollow', __( 'Do not ask search engines to follow links on this term archive (nofollow)', 'core-blueprint-seo' ) ); ?>
			<?php self::render_add_checkbox( 'cb-seo-term-noimageindex', 'cb_seo_noimageindex', __( 'Do not index images from this term archive (noimageindex)', 'core-blueprint-seo' ) ); ?>
			<?php self::render_add_checkbox( 'cb-seo-term-noarchive', 'cb_seo_noarchive', __( 'Do not provide a cached copy of this term archive (noarchive)', 'core-blueprint-seo' ) ); ?>
			<?php self::render_add_checkbox( 'cb-seo-term-nosnippet', 'cb_seo_nosnippet', __( 'Do not show a text snippet for this term archive (nosnippet)', 'core-blueprint-seo' ) ); ?>
			<p><?php esc_html_e( 'Unchecked directives inherit the normal WordPress or global SEO policy. noindex also removes this term from the WordPress XML sitemap.', 'core-blueprint-seo' ); ?></p>
		</div>

		<div class="form-field term-cb-seo-canonical-wrap">
			<label for="cb-seo-term-canonical"><?php esc_html_e( 'Canonical URL', 'core-blueprint-seo' ); ?></label>
			<input type="url" id="cb-seo-term-canonical" name="cb_seo_canonical" value="" placeholder="<?php esc_attr_e( 'Optional absolute HTTP(S) URL', 'core-blueprint-seo' ); ?>">
			<p><?php esc_html_e( 'Leave empty to keep the normal site behaviour. Core Blueprint only emits a term canonical when an explicit override is configured.', 'core-blueprint-seo' ); ?></p>
		</div>

		<div class="form-field term-cb-seo-social-wrap">
			<label for="cb-seo-term-social-title"><?php esc_html_e( 'Social appearance', 'core-blueprint-seo' ); ?></label>
			<input type="text" id="cb-seo-term-social-title" name="cb_seo_social_title" value="" placeholder="<?php esc_attr_e( 'Social title (optional)', 'core-blueprint-seo' ); ?>">
			<textarea id="cb-seo-term-social-description" name="cb_seo_social_description" rows="3" placeholder="<?php esc_attr_e( 'Social description (optional)', 'core-blueprint-seo' ); ?>"></textarea>
			<?php self::render_image_picker( 'cb_seo_social_image_id', 0 ); ?>
			<p><?php esc_html_e( 'Open Graph and X/Twitter overrides. Empty values inherit the resolved SEO metadata.', 'core-blueprint-seo' ); ?></p>
		</div>
		<?php
	}

	public static function render_edit( WP_Term $term ): void {
		$meta = Repository::term( (int) $term->term_id );
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );
		?>
		<tr class="form-field term-cb-seo-title-wrap">
			<th scope="row"><label for="cb-seo-term-title"><?php esc_html_e( 'SEO title', 'core-blueprint-seo' ); ?></label></th>
			<td>
				<input type="text" class="regular-text" id="cb-seo-term-title" name="cb_seo_title" value="<?php echo esc_attr( $meta['title'] ); ?>">
				<p class="description"><?php esc_html_e( 'Optional. Leave empty to use the taxonomy template or the normal WordPress title.', 'core-blueprint-seo' ); ?></p>
			</td>
		</tr>
		<tr class="form-field term-cb-seo-description-wrap">
			<th scope="row"><label for="cb-seo-term-description"><?php esc_html_e( 'Meta description', 'core-blueprint-seo' ); ?></label></th>
			<td>
				<textarea class="large-text" id="cb-seo-term-description" name="cb_seo_description" rows="4"><?php echo esc_textarea( $meta['description'] ); ?></textarea>
				<p class="description"><?php esc_html_e( 'Optional. Leave empty to use the taxonomy template.', 'core-blueprint-seo' ); ?></p>
			</td>
		</tr>
		<tr class="form-field term-cb-seo-robots-wrap">
			<th scope="row"><?php esc_html_e( 'Search engine directives', 'core-blueprint-seo' ); ?></th>
			<td>
				<?php self::render_edit_checkbox( 'cb-seo-term-noindex', 'cb_seo_noindex', $meta['noindex'], __( 'Block this term archive from search engine indexes (noindex)', 'core-blueprint-seo' ) ); ?>
				<?php self::render_edit_checkbox( 'cb-seo-term-nofollow', 'cb_seo_nofollow', $meta['nofollow'], __( 'Do not ask search engines to follow links on this term archive (nofollow)', 'core-blueprint-seo' ) ); ?>
				<?php self::render_edit_checkbox( 'cb-seo-term-noimageindex', 'cb_seo_noimageindex', $meta['noimageindex'], __( 'Do not index images from this term archive (noimageindex)', 'core-blueprint-seo' ) ); ?>
				<?php self::render_edit_checkbox( 'cb-seo-term-noarchive', 'cb_seo_noarchive', $meta['noarchive'], __( 'Do not provide a cached copy of this term archive (noarchive)', 'core-blueprint-seo' ) ); ?>
				<?php self::render_edit_checkbox( 'cb-seo-term-nosnippet', 'cb_seo_nosnippet', $meta['nosnippet'], __( 'Do not show a text snippet for this term archive (nosnippet)', 'core-blueprint-seo' ) ); ?>
				<p class="description"><?php esc_html_e( 'Unchecked directives inherit the normal WordPress or global SEO policy. noindex also removes this term from the WordPress XML sitemap.', 'core-blueprint-seo' ); ?></p>
			</td>
		</tr>

		<tr class="form-field term-cb-seo-canonical-wrap">
			<th scope="row"><label for="cb-seo-term-canonical"><?php esc_html_e( 'Canonical URL', 'core-blueprint-seo' ); ?></label></th>
			<td>
				<input type="url" class="regular-text" id="cb-seo-term-canonical" name="cb_seo_canonical" value="<?php echo esc_attr( $meta['canonical'] ); ?>" placeholder="<?php esc_attr_e( 'Optional absolute HTTP(S) URL', 'core-blueprint-seo' ); ?>">
				<p class="description"><?php esc_html_e( 'Leave empty to keep the normal site behaviour. Core Blueprint only emits a term canonical when an explicit override is configured.', 'core-blueprint-seo' ); ?></p>
			</td>
		</tr>

		<tr class="form-field term-cb-seo-social-wrap">
			<th scope="row"><?php esc_html_e( 'Social appearance', 'core-blueprint-seo' ); ?></th>
			<td>
				<p><label for="cb-seo-term-social-title"><strong><?php esc_html_e( 'Social title', 'core-blueprint-seo' ); ?></strong></label></p>
				<input type="text" class="regular-text" id="cb-seo-term-social-title" name="cb_seo_social_title" value="<?php echo esc_attr( $meta['social_title'] ); ?>">
				<p><label for="cb-seo-term-social-description"><strong><?php esc_html_e( 'Social description', 'core-blueprint-seo' ); ?></strong></label></p>
				<textarea class="large-text" id="cb-seo-term-social-description" name="cb_seo_social_description" rows="3"><?php echo esc_textarea( $meta['social_description'] ); ?></textarea>
				<?php self::render_image_picker( 'cb_seo_social_image_id', $meta['social_image_id'] ); ?>
				<p class="description"><?php esc_html_e( 'Open Graph and X/Twitter overrides. Empty values inherit the resolved SEO metadata.', 'core-blueprint-seo' ); ?></p>
			</td>
		</tr>
		<?php
	}

	public static function save( int $term_id ): void {
		if ( ! isset( $_POST[ self::NONCE_NAME ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) ), self::NONCE_ACTION ) ) {
			return;
		}
		$term = get_term( $term_id );
		if ( ! $term instanceof WP_Term ) {
			return;
		}
		$taxonomy = get_taxonomy( $term->taxonomy );
		$capability = $taxonomy && isset( $taxonomy->cap->edit_terms ) ? (string) $taxonomy->cap->edit_terms : 'manage_categories';
		if ( ! current_user_can( $capability ) ) {
			return;
		}

		$before = Repository::term( $term_id );
		$changed = Repository::save_term(
			$term_id,
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

		$after = Repository::term( $term_id );
		if ( $before['title'] !== $after['title'] || $before['description'] !== $after['description'] ) {
			Audit::log( 'seo_term_metadata_updated', [ 'term_id' => $term_id, 'taxonomy' => $term->taxonomy ] );
		}
		if ( $before['canonical'] !== $after['canonical'] ) {
			Audit::log(
				'seo_term_canonical_updated',
				[
					'term_id'   => $term_id,
					'taxonomy'  => $term->taxonomy,
					'canonical' => $after['canonical'],
				]
			);
		}
		if ( $before['social_title'] !== $after['social_title'] || $before['social_description'] !== $after['social_description'] || $before['social_image_id'] !== $after['social_image_id'] ) {
			Audit::log(
				'seo_term_social_updated',
				[
					'term_id'  => $term_id,
					'taxonomy' => $term->taxonomy,
				]
			);
		}
		if ( Repository::robots_from_values( $before ) !== Repository::robots_from_values( $after ) ) {
			Audit::log(
				'seo_term_indexing_updated',
				[
					'term_id'  => $term_id,
					'taxonomy' => $term->taxonomy,
					'robots'   => Repository::robots_from_values( $after ),
				]
			);
		}
	}

	private static function render_image_picker( string $name, int $attachment_id ): void {
		$url = $attachment_id > 0 ? (string) wp_get_attachment_image_url( $attachment_id, 'medium' ) : '';
		?>
		<div class="cb-seo-image-field" data-cb-seo-image-field>
			<input type="hidden" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( (string) $attachment_id ); ?>" data-cb-seo-image-id>
			<div class="cb-seo-image-field__preview" data-cb-seo-image-preview<?php echo '' === $url ? ' hidden' : ''; ?>><?php if ( '' !== $url ) : ?><img src="<?php echo esc_url( $url ); ?>" alt=""><?php endif; ?></div>
			<p class="cb-seo-image-field__actions"><button type="button" class="button" data-cb-seo-image-select><?php esc_html_e( 'Choose image', 'core-blueprint-seo' ); ?></button> <button type="button" class="button-link-delete" data-cb-seo-image-remove<?php echo $attachment_id <= 0 ? ' hidden' : ''; ?>><?php esc_html_e( 'Remove image', 'core-blueprint-seo' ); ?></button></p>
		</div>
		<?php
	}

	private static function render_add_checkbox( string $id, string $name, string $label ): void {
		?>
		<p>
			<label for="<?php echo esc_attr( $id ); ?>">
				<input type="checkbox" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" value="1">
				<?php echo esc_html( $label ); ?>
			</label>
		</p>
		<?php
	}

	private static function render_edit_checkbox( string $id, string $name, bool $checked, string $label ): void {
		?>
		<p>
			<label for="<?php echo esc_attr( $id ); ?>">
				<input type="checkbox" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" value="1" <?php checked( $checked ); ?>>
				<?php echo esc_html( $label ); ?>
			</label>
		</p>
		<?php
	}
}
