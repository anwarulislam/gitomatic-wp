<?php

namespace DeployerForGit\Providers;

/**
 * Class GitlabProvider
 *
 * @package DeployerForGit\Providers
 */
class GitlabProvider extends BaseProvider {

	/**
	 * Get provider repository handle
	 *
	 * @return string Repository handle
	 */
	public function get_pretty_name() {
		return 'GitLab';
	}

	/**
	 * Get provider repository zip URL
	 *
	 * @param  string $branch   Branch name, default is master.
	 * @return string           Repository URL to download zip
	 */
	public function get_zip_repo_url( $branch = 'master' ) {
		$handle = $this->get_handle();
		$domain = $this->get_domain();
		$handle = rawurlencode( $handle );

		return "https://{$domain}/api/v4/projects/{$handle}/repository/archive.zip?sha={$branch}";
	}

	/**
	 * Get request headers needed to authenticate package downloads
	 *
	 * @param  array $options Repository options.
	 * @return array          Request headers
	 */
	public static function get_download_headers( $options = array() ) {
		$token = ( isset( $options['access_token'] ) ? $options['access_token'] : '' );
		if ( empty( $token ) ) {
			return array();
		}
		return array(
			'PRIVATE-TOKEN' => $token,
		);
	}

	// disable the custom validation because user may use custom gitlab instance or groups which adds portions like /group/subgroup/ etc..
	// protected function validate_repo_url() {
	// return parent::validate_repo_url();.

	// check if string has exact format in a URL like this: https://gitlab.com/owner/reponame .
	// if ( ! preg_match( '/^https?:\/\/[^\/]+(\/[^\/]+)+$/', $this->get_repo_url() ) ) {
	// return new \WP_Error( 'invalid', __( 'Repository url must be a valid GitLab repository url', 'deployer-for-git' ) );
	// }
	// }.
}
