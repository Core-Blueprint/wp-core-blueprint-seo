<?php
declare(strict_types=1);
/**
 * Core Blueprint admin-page wrapper for the SEO extension.
 *
 * @package Core_Blueprint_SEO
 */

namespace CB\SEO\Admin\Pages;

use CB\Core\Admin\Page;
use CB\SEO\Admin\SettingsPage;

defined( 'ABSPATH' ) || exit;

final class Seo implements Page {

	public function slug(): string {
		return 'core-blueprint-seo';
	}

	public function title(): string {
		return __( 'SEO', 'core-blueprint-seo' );
	}

	public function menu_title(): string {
		return __( 'SEO', 'core-blueprint-seo' );
	}

	public function capability(): string {
		return 'manage_options';
	}

	public function position(): ?int {
		return 110;
	}

	public function render(): void {
		SettingsPage::render();
	}
}
