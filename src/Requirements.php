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
	public static function api_compatible( string $available, string $required ): bool {
		if ( 1 !== preg_match( '/^(\d+)\.(\d+)$/', $available, $available_match ) ) {
			return false;
		}
		if ( 1 !== preg_match( '/^(\d+)\.(\d+)$/', $required, $required_match ) ) {
			return false;
		}

		return (int) $available_match[1] === (int) $required_match[1]
			&& (int) $available_match[2] >= (int) $required_match[2];
	}

	/** @return string[] Bootstrap v1 blockers only. */
	public static function issues(): array {
		$issues = [];

		if ( version_compare( PHP_VERSION, CB_SEO_MIN_PHP, '<' ) ) {
			$issues[] = 'php-version';
		}
		if ( ! defined( 'CB_CORE_API_VERSION' ) ) {
			$issues[] = 'base-missing';
			return $issues;
		}
		if ( ! self::api_compatible( (string) CB_CORE_API_VERSION, CB_SEO_REQUIRED_API ) ) {
			$issues[] = 'base-api-incompatible';
		}

		return array_values( array_unique( $issues ) );
	}

	public static function runtime_ready(): bool {
		return [] === self::issues();
	}

	/** Product-specific public Base services consumed by SEO. */
	public static function base_contracts_ready(): bool {
		$required_classes = [
			'\\CB\\Core\\Admin\\SettingsRegistry',
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

	/** Backward-compatible product readiness helper. */
	public static function base_ready(): bool {
		return self::runtime_ready() && self::base_contracts_ready();
	}

	public static function operator_message(): string {
		$issues = self::issues();
		$issue  = (string) ( $issues[0] ?? '' );

		switch ( $issue ) {
			case 'php-version':
				return sprintf(
					/* translators: 1: required PHP version, 2: current PHP version. */
					__( 'PHP %1$s or newer is required. This server runs PHP %2$s.', 'core-blueprint-seo' ),
					CB_SEO_MIN_PHP,
					PHP_VERSION
				);
			case 'base-missing':
				return __( 'An active Core Blueprint Base installation is required.', 'core-blueprint-seo' );
			case 'base-api-incompatible':
				return sprintf(
					/* translators: 1: required Core API version, 2: available Core API version. */
					__( 'Core API %1$s or a newer compatible minor version is required. This site provides %2$s.', 'core-blueprint-seo' ),
					CB_SEO_REQUIRED_API,
					defined( 'CB_CORE_API_VERSION' ) ? (string) CB_CORE_API_VERSION : __( 'none', 'core-blueprint-seo' )
				);
			default:
				return __( 'Ready', 'core-blueprint-seo' );
		}
	}

	/** Activation-time messages remain plain English before normal i18n lifecycle. */
	public static function unmet(): array {
		$errors = [];

		if ( ! self::runtime_ready() ) {
			$errors[] = sprintf(
				'Core Blueprint SEO requires PHP %s or newer and an active Core Blueprint Base installation compatible with Core API %s.',
				CB_SEO_MIN_PHP,
				CB_SEO_REQUIRED_API
			);
			return $errors;
		}

		if ( ! self::base_contracts_ready() ) {
			$errors[] = 'The active Core Blueprint Base installation does not expose the public services required by Core Blueprint SEO.';
		}

		return $errors;
	}
}
