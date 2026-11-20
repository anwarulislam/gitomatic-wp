<?php

namespace GitomaticWP\Providers;

/**
 * Class GiteaProvider
 *
 * @package Gitomatic_WP
 */
class GiteaProvider extends BaseProvider {

	/**
	 * Get provider repository handle
	 *
	 * @return string Repository handle
	 */
	public function get_pretty_name() {
		return 'Gitea';
	}

	/**
	 * Get provider repository zip URL
	 *
	 * @param  string $branch   Branch name, default is master.
	 * @return string           Repository URL to download zip
	 */
	public function get_zip_repo_url( $branch = 'master' ) {
		$branch = rawurlencode( $branch );
		$handle = $this->get_handle();
		$domain = $this->get_domain();

		return "https://{$domain}/api/v1/repos/{$handle}/archive/{$branch}.zip";
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
			'Authorization' => 'token ' . $token,
			'User-Agent'    => 'WordPress/GitomaticWP',
		);
	}

	/**
	 * Validate repository url
	 *
	 * @return WP_Error|boolean
	 */
	protected function validate_repo_url() {
		parent::validate_repo_url();

		// check if string is a valid url.
		if ( ! preg_match( '/^https?:\/\/[^\/]+\/[^\/]+\/[^\/]+$/', $this->get_repo_url() ) ) {
			return new \WP_Error( 'invalid', __( 'Repository url must be a valid Gitea repository url', 'gitomatic-wp' ) );
		}
	}
}
