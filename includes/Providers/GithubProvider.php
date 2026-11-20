<?php

namespace GitomaticWP\Providers;

/**
 * Class GithubProvider
 *
 * @package Gitomatic_WP
 */
class GithubProvider extends BaseProvider {
    /**
     * Get provider repository handle
     *
     * @return string Repository handle
     */
    public function get_pretty_name() {
        return 'GitHub';
    }

    /**
     * Get provider repository zip URL
     *
     * @param  string $branch   Branch name, default is master.
     * @param  string $token    Access token.
     * @return string           Repository URL to download zip
     */
    public function get_zip_repo_url( $branch = 'master', $token = '' ) {
        $branch = rawurlencode( $branch );
        $handle = $this->get_handle();
        // If token is not empty, use the API zipball URL, because the github.com
        // archive URL does not reliably accept token authentication for private repos.
        if ( !empty( $token ) ) {
            return "https://api.github.com/repos/{$handle}/zipball/{$branch}";
        }
        return "https://github.com/{$handle}/archive/refs/heads/{$branch}.zip";
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
            'Authorization' => 'Bearer ' . $token,
            'User-Agent'    => 'WordPress/GitomaticWP',
            'Accept'        => 'application/vnd.github+json',
        );
    }

    /**
     * Validate repository url
     *
     * @return WP_Error|boolean
     */
    protected function validate_repo_url() {
        parent::validate_repo_url();
        // check if string has exact format in a URL like this: https://github.com/company/wordpress-theme .
        if ( !preg_match( '/^https:\\/\\/github.com\\/[a-zA-Z0-9._-]+\\/[a-zA-Z0-9._-]+$/', $this->get_repo_url() ) ) {
            return new \WP_Error('invalid', __( 'Repository url must be a valid GitHub repository url', 'gitomatic-wp' ));
        }
    }

}
