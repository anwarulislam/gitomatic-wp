<?php
namespace GitomaticWP;

/** Fetch one page at a time so large repositories can load incrementally. */
class Branches {
	public static function lookup( $provider, $url, $options, $page = 1 ) {
		$class = Helper::get_provider_class( $provider );
		if ( ! isset( Helper::available_providers()[ $provider ] ) ) {
			return new \WP_Error( 'provider', __( 'Choose the repository provider.', 'gitomatic-wp' ) );
		}
		try {
			$repository = new $class( $url );
		} catch ( \InvalidArgumentException $error ) {
			return new \WP_Error( 'repository', $error->getMessage() );
		}
		$parts = wp_parse_url( $repository->get_repo_url() );
		$handle = preg_replace( '/\.git$/i', '', trim( $parts['path'] ?? '', '/' ) );
		$origin = 'https://' . $parts['host'] . ( isset( $parts['port'] ) ? ':' . $parts['port'] : '' );
		$size = 50;
		switch ( $provider ) {
			case 'github':
				$repo = 'https://api.github.com/repos/' . $handle;
				$list = "$repo/branches?per_page=$size&page=$page";
				break;
			case 'gitlab':
				$repo = $origin . '/api/v4/projects/' . rawurlencode( $handle );
				$list = "$repo/repository/branches?per_page=$size&page=$page";
				break;
			case 'bitbucket':
				$repo = 'https://api.bitbucket.org/2.0/repositories/' . $handle;
				$list = "$repo/refs/branches?pagelen=$size&page=$page";
				break;
			case 'gitea':
				$repo = $origin . '/api/v1/repos/' . $handle;
				$list = "$repo/branches?limit=$size&page=$page";
				break;
		}
		$args = array( 'headers' => $class::get_download_headers( $options ), 'redirection' => 0, 'timeout' => 10 );
		$default = '';
		if ( 1 === $page ) {
			$meta = self::request( $repo, $args );
			if ( is_wp_error( $meta ) ) {
				return $meta;
			}
			$default = $meta['default_branch'] ?? ( $meta['mainbranch']['name'] ?? '' );
		}
		$data = self::request( $list, $args );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		$branches = array_column( $data['values'] ?? $data, 'name' );
		return array( 'branches' => $branches, 'default' => $default, 'more' => 'bitbucket' === $provider ? ! empty( $data['next'] ) : count( $branches ) >= $size );
	}

	private static function request( $url, $args ) {
		$response = wp_safe_remote_get( $url, $args );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$status = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( 200 !== $status || ! is_array( $data ) ) {
			return new \WP_Error( 'branch_lookup', sprintf( __( 'Branch lookup failed (HTTP %d). Check the repository URL and credentials, or enter a branch manually.', 'gitomatic-wp' ), $status ) );
		}
		return $data;
	}

	public static function ajax() {
		check_ajax_referer( 'gitomatic_wp_branches', 'nonce' );
		$type = 'theme' === ( $_POST['package_type'] ?? '' ) ? 'theme' : 'plugin';
		if ( ! current_user_can( is_multisite() ? 'manage_network_options' : 'manage_options' ) || ! current_user_can( 'install_' . $type . 's' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'gitomatic-wp' ), 403 );
		}
		$id = sanitize_text_field( wp_unslash( $_POST['source_id'] ?? '' ) );
		if ( $id ) {
			$record = ( new DataManager() )->get_package( $id, $type );
			if ( ! $record ) {
				wp_send_json_error( __( 'Deployment no longer exists.', 'gitomatic-wp' ) );
			}
			$provider = $record['provider'];
			$url = $record['repo_url'];
			$options = $record['is_private_repository'] ? $record['options'] : array();
		} else {
			$provider = sanitize_key( $_POST['provider_type'] ?? '' );
			$url = esc_url_raw( wp_unslash( $_POST['repository_url'] ?? '' ) );
			$options = isset( $_POST['is_private_repository'] ) ? array_intersect_key( wp_unslash( $_POST ), array_flip( array( 'username', 'password', 'access_token' ) ) ) : array();
		}
		$result = self::lookup( $provider, $url, $options, max( 1, absint( $_POST['branch_page'] ?? 1 ) ) );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}
		wp_send_json_success( $result );
	}
}
