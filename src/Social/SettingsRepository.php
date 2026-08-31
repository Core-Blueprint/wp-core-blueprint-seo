<?php
declare(strict_types=1);
/**
 * Site-wide social metadata settings.
 *
 * @package Core_Blueprint_SEO
 */

namespace CB\SEO\Social;

defined( 'ABSPATH' ) || exit;

final class SettingsRepository {
	/** @return array{enabled:bool,default_image_id:int} */
	public static function all(): array {
		$value = get_option( CB_SEO_SOCIAL_SETTINGS_OPT, [] );
		return self::sanitize( is_array( $value ) ? $value : [] );
	}

	/** @param array<string,mixed> $input
	 *  @return array{enabled:bool,default_image_id:int}
	 */
	public static function sanitize( array $input ): array {
		return [
			'enabled'          => ! empty( $input['enabled'] ),
			'default_image_id' => isset( $input['default_image_id'] ) ? absint( $input['default_image_id'] ) : 0,
		];
	}

	/** @param array<string,mixed> $input */
	public static function save( array $input ): bool {
		$next = self::sanitize( $input );
		if ( $next === self::all() ) {
			return false;
		}
		update_option( CB_SEO_SOCIAL_SETTINGS_OPT, $next, false );
		return true;
	}
}
