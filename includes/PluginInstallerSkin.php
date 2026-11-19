<?php
namespace DeployerForGit;

if ( ! class_exists( 'WP_Upgrader_Skin' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader-skin.php';
}

if ( ! class_exists( 'Plugin_Installer_Skin' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-plugin-installer-skin.php';
}

/**
 * Class PluginInstallerSkin
 *
 * @package DeployerForGit
 */
class PluginInstallerSkin extends \Plugin_Installer_Skin {

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
		if ( ! is_wp_error( $this->errors ) ) {
			$this->errors = new \WP_Error();
		}
		if ( is_wp_error( $errors ) ) {
			$this->errors->merge_from( $errors );
		} elseif ( is_string( $errors ) ) {
			$this->errors->add( 'error', $errors );
		}
	}

	/**
	 * Override after method with empty function.
	 */
	public function after() {}
}
