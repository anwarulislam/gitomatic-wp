<?php
namespace GitomaticWP;

if ( ! class_exists( 'WP_Upgrader_Skin' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader-skin.php';
}

if ( ! class_exists( 'Theme_Installer_Skin' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-theme-installer-skin.php';
}

/**
 * Class ThemeInstallerSkin
 *
 * @package GitomaticWP
 */
class ThemeInstallerSkin extends \Theme_Installer_Skin {

	private $deployment_errors;

	public function get_errors() {
		return $this->deployment_errors;
	}

	/**
	 * Override header method with empty function.
	 */
	public function header() {}

	/**
	 * Override footer method with empty function.
	 */
	public function footer() {}

	/**
	 * Override feedback method with empty function.
	 *
	 * @param string $feedback Message data.
	 * @param mixed  ...$args  Optional text replacements.
	 */
	public function feedback( $feedback, ...$args ) {}

	/**
	 * Store error without outputting HTML.
	 *
	 * @param string|\WP_Error $errors Errors.
	 */
	public function error( $errors ) {
		if ( ! is_wp_error( $this->deployment_errors ) ) {
			$this->deployment_errors = new \WP_Error();
		}
		if ( is_wp_error( $errors ) ) {
			$this->deployment_errors->merge_from( $errors );
		} elseif ( is_string( $errors ) ) {
			$this->deployment_errors->add( 'error', $errors );
		}
	}
}
