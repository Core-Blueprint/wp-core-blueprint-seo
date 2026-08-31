<?php
declare(strict_types=1);
/**
 * Site-wide structured data settings.
 *
 * @package Core_Blueprint_SEO
 */

namespace CB\SEO\Schema;

defined( 'ABSPATH' ) || exit;

final class SettingsRepository {
	/** @return array{enabled:bool,identity_type:string,identity_name:string,logo_id:int} */
	public static function all(): array {
		$value = get_option( CB_SEO_SCHEMA_SETTINGS_OPT, [] );
		return self::sanitize( is_array( $value ) ? $value : [] );
	}

	/** @param array<string,mixed> $input
	 *  @return array{enabled:bool,identity_type:string,identity_name:string,logo_id:int}
	 */
	public static function sanitize( array $input ): array {
		$type = isset( $input['identity_type'] ) ? sanitize_key( (string) $input['identity_type'] ) : 'organization';
		if ( ! in_array( $type, [ 'organization', 'person' ], true ) ) {
			$type = 'organization';
		}
		return [
			'enabled'       => ! empty( $input['enabled'] ),
			'identity_type' => $type,
			'identity_name' => isset( $input['identity_name'] ) ? sanitize_text_field( wp_unslash( (string) $input['identity_name'] ) ) : '',
			'logo_id'       => isset( $input['logo_id'] ) ? absint( $input['logo_id'] ) : 0,
		];
	}

	/** @param array<string,mixed> $input */
	public static function save( array $input ): bool {
		$next = self::sanitize( $input );
		if ( $next === self::all() ) {
			return false;
		}
		update_option( CB_SEO_SCHEMA_SETTINGS_OPT, $next, false );
		return true;
	}
}
