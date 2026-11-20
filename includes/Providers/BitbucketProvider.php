<?php

namespace GitomaticWP\Providers;

/**
 * Class BitbucketProvider
 *
 * @package GitomaticWP\Providers
 */
class BitbucketProvider extends BaseProvider {

	/**
	 * Get provider repository handle
	 *
	 * @return string Repository handle
	 */
	public function get_pretty_name() {
		return 'Bitbucket';
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

		return "https://bitbucket.org/{$handle}/get/{$branch}.zip";
	}

	/**
	 * Get request headers needed to authenticate package downloads
	 *
	 * @param  array $options Repository options.
	 * @return array          Request headers
	 */
	public static function get_download_headers( $options = array() ) {
		$username = ( isset( $options['username'] ) ? $options['username'] : '' );
		$password = ( isset( $options['password'] ) ? $options['password'] : '' );
		if ( empty( $username ) || empty( $password ) ) {
			return array();
		}
		return array(
			'Authorization' => 'Basic ' . base64_encode( $username . ':' . $password ),
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

		// check if string has exact format in a URL like this: https://bitbucket.org/owner/reponame .
		if ( ! preg_match( '/^https:\/\/bitbucket\.org\/[a-zA-Z0-9._-]+\/[a-zA-Z0-9._-]+$/', $this->get_repo_url() ) ) {
			return new \WP_Error( 'invalid', __( 'Repository url must be a valid Bitbucket repository url', 'gitomatic-wp' ) );
		}
	}
}
