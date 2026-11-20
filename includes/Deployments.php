<?php
namespace GitomaticWP;

use GitomaticWP\Subpages\InstallPackage;

/** A saved deployment identifies a branch independently of its WordPress folder. */
class Deployments {
	public static function valid_folder( $folder ) {
		return is_string( $folder ) && preg_match( '/^[A-Za-z0-9_-][A-Za-z0-9._-]*$/D', $folder );
	}

	public static function installed_folders( $type ) {
		if ( 'theme' === $type ) {
			return array_keys( wp_get_themes() );
		}
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		$folders = array_map( 'dirname', array_keys( get_plugins() ) );
		return array_values( array_filter( array_unique( $folders ), array( __CLASS__, 'valid_folder' ) ) );
	}

	// WordPress unpacks archives in a shared working directory across destinations.
	public static function locked( $callback ) {
		global $wpdb;
		static $owned = false;
		if ( $owned ) {
			return $callback();
		}
		$key = 'gitomatic-wp-' . md5( ABSPATH );
		if ( '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 0)', $key ) ) ) {
			return new \WP_Error( 'deployment_busy', __( 'Another deployment is running. Try again when it finishes.', 'gitomatic-wp' ) );
		}
		$owned = true;
		try {
			return $callback();
		} finally {
			$owned = false;
			$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $key ) );
		}
	}

	public static function create( $package, $type, $mode, $source_id = '', $trusted = false ) {
		return self::locked( function () use ( $package, $type, $mode, $source_id, $trusted ) {
			$db = new DataManager();
			$folder = $package['slug'];
			if ( ! self::valid_folder( $folder ) || '' === $package['branch'] ) {
				return new \WP_Error( 'invalid_target', __( 'Choose a branch and a valid destination folder.', 'gitomatic-wp' ) );
			}
			$source = $source_id ? $db->get_package( $source_id, $type ) : false;
			if ( $source_id && ( ! $source || $source['repo_url'] !== $package['repo_url'] ) ) {
				return new \WP_Error( 'invalid_source', __( 'The original deployment is no longer available.', 'gitomatic-wp' ) );
			}
			$root = 'theme' === $type ? get_theme_root( $folder ) : WP_PLUGIN_DIR;
			$path = trailingslashit( $root ) . $folder;
			$list = 'theme' === $type ? $db->get_theme_list() : $db->get_plugin_list();
			$id = $folder;
			foreach ( $list as $record ) {
				if ( $folder !== $record['slug'] && DataManager::instance_id( $record ) === $id ) {
					$id = wp_generate_uuid4();
				}
				if ( $folder !== $record['slug'] ) {
					continue;
				}
				if ( 'shared' !== $mode || $record['repo_url'] !== $package['repo_url'] || $record['branch'] === $package['branch'] ) {
					return new \WP_Error( 'destination_taken', __( 'This deployment already exists or the folder belongs to another deployment. Choose another branch or folder.', 'gitomatic-wp' ) );
				}
				$id = wp_generate_uuid4();
			}
			if ( 'shared' === $mode ) {
				if ( ! $source || $source['slug'] !== $folder ) {
					return new \WP_Error( 'invalid_target', __( 'Choose an existing deployment to share its folder.', 'gitomatic-wp' ) );
				}
			} elseif ( 'existing' === $mode ) {
				if ( $source_id || ! in_array( $folder, self::installed_folders( $type ), true ) || is_link( $path ) ) {
					return new \WP_Error( 'invalid_target', __( 'Choose an installed WordPress package to connect.', 'gitomatic-wp' ) );
				}
			} elseif ( file_exists( $path ) || is_link( $path ) ) {
				return new \WP_Error( 'destination_taken', __( 'That folder already exists. Choose an unused folder.', 'gitomatic-wp' ) );
			}
			$package['instance_id'] = $id;
			$result = self::install( $package, $type, $trusted );
			if ( true === $result && ! $db->store_package_details( $package, $type ) ) {
				return new \WP_Error( 'save_failed', __( 'Files deployed, but WordPress could not save the deployment settings.', 'gitomatic-wp' ) );
			}
			return true === $result ? $package : $result;
		} );
	}

	public static function deploy( $package, $type, $trusted = false ) {
		return self::locked( function () use ( $package, $type, $trusted ) {
			$current = ( new DataManager() )->get_package( DataManager::instance_id( $package ), $type );
			if ( $current !== $package ) {
				return new \WP_Error( 'deployment_changed', __( 'Deployment settings changed. Refresh and try again.', 'gitomatic-wp' ) );
			}
			return self::install( $package, $type, $trusted );
		} );
	}

	private static function install( $package, $type, $trusted ) {
		try {
			$class = Helper::get_provider_class( $package['provider'] );
			$provider = new $class( $package['repo_url'] );
			$options = $package['is_private_repository'] ? $package['options'] : array();
			$url = $provider->get_zip_repo_url( $package['branch'], $options['access_token'] ?? '' );
			$options['branch'] = $package['branch'];
			$options['is_private_repository'] = $package['is_private_repository'];
			$options['wp_json_request'] = $trusted;
			$result = InstallPackage::install_package_from_zip_url( $url, $package['slug'], $type, $package['provider'], $options );
		} catch ( \Exception $error ) {
			$result = new \WP_Error( 'deployment_failed', $error->getMessage() );
		}
		$id = DataManager::instance_id( $package );
		update_option( 'gitomatic_wp_result_' . md5( $type . '/' . $id ), array(
			'time' => time(), 'success' => true === $result, 'branch' => $package['branch'],
		), false );
		if ( true === $result ) {
			update_option( 'gitomatic_wp_live_' . md5( $type . '/' . $package['slug'] ), $package['branch'], false );
		}
		return $result;
	}
}
