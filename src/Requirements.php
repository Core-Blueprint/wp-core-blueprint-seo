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
		if ( ! defined( 'CB_CORE_VERSION' ) || ! defined( 'CB_CORE_API_VERSION' ) ) {
			$issues[] = 'base-missing';
			return $issues;
		}
		if ( version_compare( (string) CB_CORE_VERSION, CB_SEO_REQUIRED_BASE, '<' ) ) {
			$issues[] = 'base-version-incompatible';
		}
		if ( ! self::api_compatible( (string) CB_CORE_API_VERSION, CB_SEO_REQUIRED_API ) ) {
			$issues[] = 'base-api-incompatible';
		}

		return array_values( array_unique( $issues ) );
	}

	public static function runtime_ready(): bool {
		return [] === self::issues();
	}

	/** Canonical untranslated activation explanation. */
	public static function activation_message(): string {
		$issue = self::primary_issue();

		switch ( $issue ) {
			case 'php-version':
				return sprintf(
					'PHP %1$s or newer is required. This server runs PHP %2$s.',
					CB_SEO_MIN_PHP,
					PHP_VERSION
				);
			case 'base-missing':
				return 'Core Blueprint must be installed and active.';
			case 'base-version-incompatible':
				return sprintf(
					'Core Blueprint %1$s or newer is required. Available Base version: %2$s.',
					CB_SEO_REQUIRED_BASE,
					defined( 'CB_CORE_VERSION' ) ? (string) CB_CORE_VERSION : 'none'
				);
			case 'base-version-incompatible':
				return sprintf(
					/* translators: 1: required Core Blueprint Base version, 2: available Base version. */
					__( 'Core Blueprint %1$s or newer is required. Available Base version: %2$s.', 'core-blueprint-seo' ),
					CB_SEO_REQUIRED_BASE,
					defined( 'CB_CORE_VERSION' ) ? (string) CB_CORE_VERSION : __( 'none', 'core-blueprint-seo' )
				);
			case 'base-api-incompatible':
				return sprintf(
					'Core API %1$s or a newer compatible minor version is required. Available Core API: %2$s.',
					CB_SEO_REQUIRED_API,
					defined( 'CB_CORE_API_VERSION' ) ? (string) CB_CORE_API_VERSION : 'none'
				);
			default:
				return 'Ready';
		}
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

	public static function operator_message(): string {
		$issue = self::primary_issue();

		switch ( $issue ) {
			case 'php-version':
				return sprintf(
					/* translators: 1: required PHP version, 2: current PHP version. */
					__( 'PHP %1$s or newer is required. This server runs PHP %2$s.', 'core-blueprint-seo' ),
					CB_SEO_MIN_PHP,
					PHP_VERSION
				);
			case 'base-missing':
				return __( 'Core Blueprint must be installed and active.', 'core-blueprint-seo' );
			case 'base-api-incompatible':
				return sprintf(
					/* translators: 1: required Core API version, 2: available Core API version. */
					__( 'Core API %1$s or a newer compatible minor version is required. Available Core API: %2$s.', 'core-blueprint-seo' ),
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
			$errors[] = self::activation_message();
			return $errors;
		}

		if ( ! self::base_contracts_ready() ) {
			$errors[] = 'Required Core Blueprint Base contracts are unavailable.';
		}

		return $errors;
	}

	private static function primary_issue(): string {
		$issues = self::issues();
		return (string) ( $issues[0] ?? '' );
	}
}
