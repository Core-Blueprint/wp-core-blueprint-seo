<?php
declare(strict_types=1);
/**
 * AI discovery settings.
 *
 * @package Core_Blueprint_SEO
 */

namespace CB\SEO\Discovery;

defined( 'ABSPATH' ) || exit;

final class SettingsRepository {
	/** @return array{enabled:bool,post_types:string[],include_descriptions:bool,max_items:int} */
	public static function defaults(): array {
		return [
			'enabled'              => false,
			'post_types'           => [ 'page', 'post' ],
			'include_descriptions' => true,
			'max_items'            => 250,
		];
	}

	/** @return array{enabled:bool,post_types:string[],include_descriptions:bool,max_items:int} */
	public static function all(): array {
		$value = get_option( CB_SEO_DISCOVERY_SETTINGS_OPT, self::defaults() );
		return self::sanitize( is_array( $value ) ? $value : self::defaults() );
	}

	/** @param array<string,mixed> $input
	 *  @return array{enabled:bool,post_types:string[],include_descriptions:bool,max_items:int}
	 */
	public static function sanitize( array $input ): array {
		$requested = isset( $input['post_types'] ) && is_array( $input['post_types'] ) ? $input['post_types'] : [];
		$post_types = [];
		foreach ( $requested as $post_type ) {
			$post_type = sanitize_key( (string) $post_type );
			if ( '' !== $post_type && 'attachment' !== $post_type ) {
				$post_types[] = $post_type;
			}
		}
		$post_types = array_values( array_unique( $post_types ) );

		$max_items = isset( $input['max_items'] ) ? absint( $input['max_items'] ) : 250;
		$max_items = max( 10, min( 1000, $max_items ) );

		return [
			'enabled'              => ! empty( $input['enabled'] ),
			'post_types'           => $post_types,
			'include_descriptions' => ! empty( $input['include_descriptions'] ),
			'max_items'            => $max_items,
		];
	}

	/** @param array<string,mixed> $input */
	public static function save( array $input ): bool {
		$next = self::sanitize( $input );
		if ( $next === self::all() ) {
			return false;
		}
		update_option( CB_SEO_DISCOVERY_SETTINGS_OPT, $next, false );
		return true;
	}
}
