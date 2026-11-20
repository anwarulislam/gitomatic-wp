<?php
namespace GitomaticWP\Subpages\InstallThemePage;

use GitomaticWP\DataManager;
use GitomaticWP\Logger;
use GitomaticWP\Subpages\InstallPackage;

/**
 * Class InstallTheme
 *
 * @package GitomaticWP\Subpages\InstallThemePage
 */
class InstallTheme extends InstallPackage {

	/**
	 * Initializes the menu.
	 */
	public function init_menu() {
		$menu_slug  = \GitomaticWP\Helper::menu_slug();
		$page_title = esc_attr__( 'Install Theme', 'gitomatic-wp' );
		$capability = is_multisite() ? 'manage_network_options' : 'manage_options';

		add_submenu_page(
			null,
			$page_title,
			$page_title,
			$capability,
			"{$menu_slug}-install-theme",
			array( $this, 'init_page' )
		);
	}

	/**
	 * Installs a theme from a zip file.
	 *
	 * @param string $package_zip_url The URL of the zip file.
	 * @param string $package_slug The slug of the package.
	 * @param string $package_provider The provider of the package (github|bitbucket|gitlab|gitea).
	 * @param array  $options The options.
	 * @return WP_Error|bool Returns WP_Error object for failure or true for success.
	 */
	public static function install_theme_from_zip_url( $package_zip_url, $package_slug, $package_provider, $options = array() ) {
		return parent::install_package_from_zip_url( $package_zip_url, $package_slug, 'theme', $package_provider, $options );
	}

	/**
	 * Initializes the page.
	 */
	public function init_page() {
		// Handle package installation form.
		$install_result = parent::handle_package_install_form();
		$package_type   = 'theme';

		include_once __DIR__ . '/../install-package-template.php';
	}
}
