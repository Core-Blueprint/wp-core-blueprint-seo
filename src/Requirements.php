<?php
declare(strict_types=1);
/**
 * Environment and Base-contract checks for Core Blueprint SEO.
 *
 * @package Core_Blueprint_SEO
 */

namespace CB\SEO;

defined( 'ABSPATH' ) || exit;

final class Requirements {

	public static function base_ready(): bool {
		if ( ! defined( 'CB_CORE_FILE' ) || ! defined( 'CB_CORE_API_VERSION' ) ) {
			return false;
		}

		if ( version_compare( (string) CB_CORE_API_VERSION, '1.0', '<' ) ) {
			return false;
		}

		$required_classes = [
			'\\CB\\Core\\Admin\\Page',
			'\\CB\\Core\\Admin\\PageRegistry',
			'\\CB\\Core\\ExtensionRegistry',
			'\\CB\\Core\\Modules\\ActivationRegistry',
			'\\CB\\Core\\Modules\\ModuleStateInterface',
			'\\CB\\Core\\UI\\Notice',
			'\\CB\\Core\\UI\\Card',
			'\\CB\\Core\\Log\\AuditLog',
		];

		foreach ( $required_classes as $class ) {
			if ( ! class_exists( $class ) && ! interface_exists( $class ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Activation-time messages deliberately remain plain English so activation
	 * never triggers translation loading before WordPress' init hook.
	 *
	 * @return string[]
	 */
	public static function unmet(): array {
		$errors = [];

		if ( version_compare( PHP_VERSION, '8.4.0', '<' ) ) {
			$errors[] = sprintf( 'PHP 8.4 or higher is required. This server runs PHP %s.', PHP_VERSION );
		}

		if ( ! defined( 'CB_CORE_FILE' ) || ! defined( 'CB_CORE_API_VERSION' ) ) {
			$errors[] = 'Core Blueprint Base with API 1.0 or newer must be installed and active.';
		} elseif ( version_compare( (string) CB_CORE_API_VERSION, '1.0', '<' ) ) {
			$errors[] = sprintf( 'Core Blueprint Base API 1.0 or newer is required. This site exposes API %s.', (string) CB_CORE_API_VERSION );
		} elseif ( ! self::base_ready() ) {
			$errors[] = 'The active Core Blueprint Base installation does not expose the required extension Foundation contracts.';
		}

		return $errors;
	}
}
