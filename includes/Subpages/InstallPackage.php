<?php

namespace GitomaticWP\Subpages;

use GitomaticWP\DataManager;
use GitomaticWP\Logger;
use GitomaticWP\Helper;
/**
 * Class InstallPackage
 *
 * @package Gitomatic_WP
 */
class InstallPackage {
    /**
     * Init package menu hook.
     */
    public function __construct() {
        add_action( ( is_multisite() ? 'network_admin_menu' : 'admin_menu' ), array($this, 'init_menu') );
    }

    /**
     * Initializes the menu.
     */
    public function init_menu() {
    }

    /**
     * Validates the package installation form.
     */
    private function validate_install_package_form() {
        if ( !isset( $_POST[GITOMATIC_WP_SLUG . '_install_package_submitted'] ) ) {
            return;
        }
        // Vefiry form nonce.
        $nonce = ( isset( $_POST[GITOMATIC_WP_SLUG . '_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST[GITOMATIC_WP_SLUG . '_nonce'] ) ) : '' );
        if ( !wp_verify_nonce( $nonce, GITOMATIC_WP_SLUG . '_install_package_form' ) ) {
            return new \WP_Error('invalid', __( 'Invalid nonce', 'gitomatic-wp' ));
        }
        return true;
    }

    /**
     * Handles the package installation form.
     *
     * @return WP_Error|bool Returns WP_Error object for failure or true for success.
     */
    public function handle_package_install_form() {
        // Validate form.
        $validation_result = $this->validate_install_package_form();
        if ( $validation_result !== true ) {
            return $validation_result;
        }
        // phpcs:disable WordPress.Security.NonceVerification.Missing
        // Sanitize form data.
        $type = 'theme' === ( $_POST['package_type'] ?? '' ) ? 'theme' : 'plugin';
        $source_id = sanitize_text_field( wp_unslash( $_POST['source_id'] ?? '' ) );
        $source = $source_id ? ( new DataManager() )->get_package( $source_id, $type ) : false;
        $branch = sanitize_text_field( wp_unslash( $_POST['repository_branch'] ?? '' ) );
        $mode = 'shared' === ( $_POST['destination_mode'] ?? '' ) ? 'shared' : 'copy';
        $package = $source ?: array(
            'repo_url' => esc_url_raw( wp_unslash( $_POST['repository_url'] ?? '' ) ),
            'provider' => sanitize_key( $_POST['provider_type'] ?? '' ),
            'is_private_repository' => isset( $_POST['is_private_repository'] ),
            'options' => array(),
        );
        if ( ! isset( Helper::available_providers()[ $package['provider'] ] ) ) {
            return new \WP_Error( 'invalid_provider', __( 'Choose a repository provider.', 'gitomatic-wp' ) );
        }
        if ( ! $source && $package['is_private_repository'] ) {
            $keys = 'bitbucket' === $package['provider'] ? array( 'username', 'password' ) : array( 'access_token' );
            foreach ( $keys as $key ) {
                $package['options'][ $key ] = trim( wp_unslash( $_POST[ $key ] ?? '' ) );
                if ( '' === $package['options'][ $key ] ) {
                    return new \WP_Error( 'credentials_required', __( 'Enter credentials for the private repository.', 'gitomatic-wp' ) );
                }
            }
        }
        $package['branch'] = $branch;
        $package['slug'] = $source && 'shared' === $mode ? $source['slug'] : trim( wp_unslash( $_POST['folder'] ?? '' ) );
        if ( ! $source_id && isset( $_POST['use_existing'] ) ) {
            $mode = 'existing';
            $package['slug'] = trim( wp_unslash( $_POST[ 'existing_' . $type ] ?? '' ) );
        }
        $created = \GitomaticWP\Deployments::create( $package, $type, $mode, $source_id );
        $install_result = is_wp_error( $created ) ? $created : true;
        ( new Logger() )->log_event(
            true === $install_result ? 'deployment.succeeded' : 'deployment.failed',
            true === $install_result ? 'info' : 'error',
            true === $install_result ? __( 'Deployment added.', 'gitomatic-wp' ) : $install_result->get_error_message(),
            array( 'package_type' => $type, 'package_slug' => $package['slug'], 'instance_id' => is_array( $created ) ? $created['instance_id'] : '', 'branch' => $branch, 'trigger' => 'wp-admin' )
        );
        $success = $install_result === true;
        do_action( 'gitomatic_wp_after_package_install', $success );
        return $install_result;
    }

    /**
     * Initializes the page.
     */
    public function init_page() {
    }

    /**
     * Checks if the request is a WP JSON request.
     *
     * @param array $options Additional options for the installation.
     */
    private static function is_wp_json_request( $options = array() ) {
        return array_key_exists( 'wp_json_request', $options ) && $options['wp_json_request'] === true;
    }

    /**
     * Checks if the current user can proceed with the installation.
     *
     * @param string $package_type Type of the package (theme|plugin).
     */
    private static function current_user_can_proceed( $package_type ) {
        $type_in_plural = "{$package_type}s";
        return current_user_can( "install_{$type_in_plural}" );
    }

    /**
     * Validate that an extracted archive contains the requested WordPress package type.
     *
     * @param string|\WP_Error $source        Extracted package source path.
     * @param string           $remote_source Remote package source path.
     * @param \WP_Upgrader     $upgrader      WordPress upgrader instance.
     * @param array            $hook_extra    Extra upgrader arguments.
     * @return string|\WP_Error Source path when valid, otherwise a helpful error.
     */
    public static function validate_package_source(
        $source,
        $remote_source,
        $upgrader,
        $hook_extra
    ) {
        global $wp_filesystem;
        if ( is_wp_error( $source ) ) {
            return $source;
        }
        $type = ( isset( $hook_extra['type'] ) && 'theme' === $hook_extra['type'] ? 'theme' : 'plugin' );
        $working_directory = str_replace( $wp_filesystem->wp_content_dir(), trailingslashit( WP_CONTENT_DIR ), $source );
        if ( !is_dir( $working_directory ) ) {
            // Match WordPress core's confidence check for non-direct filesystems.
            return $source;
        }
        if ( 'theme' === $type ) {
            $style_file = trailingslashit( $working_directory ) . 'style.css';
            $theme_data = ( file_exists( $style_file ) ? get_file_data( $style_file, array(
                'Name' => 'Theme Name',
            ) ) : array() );
            if ( empty( $theme_data['Name'] ) ) {
                return new \WP_Error(
                    'package_type_mismatch',
                    // phpcs:ignore Generic.Files.LineLength.TooLong
                    __( 'This repository does not contain a valid WordPress theme. If it contains a plugin, choose Plugin as the deployment type.', 'gitomatic-wp' )
                );
            }
            return $source;
        }
        $folder = $hook_extra['gitomatic_wp_folder'] ?? '';
        $active = array_merge( get_option( 'active_plugins', array() ), array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) ) );
        foreach ( $active as $plugin ) {
            if ( dirname( $plugin ) === $folder ) {
                $entry = trailingslashit( $working_directory ) . basename( $plugin );
                if ( ! is_file( $entry ) || empty( get_plugin_data( $entry, false, false )['Name'] ) ) {
                    return new \WP_Error( 'missing_active_plugin', __( 'This branch is missing the active plugin entry file. Install it as a separate copy.', 'gitomatic-wp' ) );
                }
            }
        }
        $plugin_files = glob( trailingslashit( $working_directory ) . '*.php' );
        foreach ( (array) $plugin_files as $plugin_file ) {
            $plugin_data = get_plugin_data( $plugin_file, false, false );
            if ( !empty( $plugin_data['Name'] ) ) {
                return $source;
            }
        }
        return new \WP_Error(
            'package_type_mismatch',
            // phpcs:ignore Generic.Files.LineLength.TooLong
            __( 'This repository does not contain a valid WordPress plugin. If it contains a theme, choose Theme as the deployment type.', 'gitomatic-wp' )
        );
    }

    /**
     * Add package validation filters for the selected upgrader.
     *
     * @param \WP_Upgrader $package_upgrader WordPress package upgrader.
     * @return void
     */
    private static function add_package_validation_filters( $package_upgrader ) {
        add_filter(
            'upgrader_source_selection',
            array(__CLASS__, 'validate_package_source'),
            9,
            4
        );
        add_filter( 'upgrader_source_selection', array($package_upgrader, 'check_package') );
    }

    /**
     * Remove package validation filters for the selected upgrader.
     *
     * @param \WP_Upgrader $package_upgrader WordPress package upgrader.
     * @return void
     */
    private static function remove_package_validation_filters( $package_upgrader ) {
        remove_filter( 'upgrader_source_selection', array(__CLASS__, 'validate_package_source'), 9 );
        remove_filter( 'upgrader_source_selection', array($package_upgrader, 'check_package') );
    }

    /**
     * Installs a package from a zip file URL.
     *
     * @param string $package_zip_url The URL of the package zip file.
     * @param string $package_slug The slug of the package.
     * @param string $type Type of the package (theme|plugin).
     * @param string $provider_type Type of the provider (bitbucket|github|gitlab|gitea).
     * @param array  $options Additional options for the installation.
     *
     * @return WP_Error|bool Returns WP_Error object for failure or true for success.
     */
    public static function install_package_from_zip_url(
        $package_zip_url,
        $package_slug,
        $type,
        $provider_type,
        $options = array()
    ) {
        if ( empty( $provider_type ) ) {
            return new \WP_Error('invalid', __( 'Provider does not exist.', 'gitomatic-wp' ));
        }
        // Check if provider type is valid.
        if ( !in_array( $provider_type, array_keys( Helper::available_providers() ), true ) ) {
            return new \WP_Error('invalid', __( 'Invalid provider type', 'gitomatic-wp' ));
        }
        $type = ( $type === 'theme' ? 'theme' : 'plugin' );
        // Check for empty URL and slug.
        if ( empty( $package_zip_url ) || ! \GitomaticWP\Deployments::valid_folder( $package_slug ) ) {
            return new \WP_Error('invalid', __( 'Package zip URL and package slug must exist', 'gitomatic-wp' ));
        }
        $is_wp_json_request = self::is_wp_json_request( $options );
        // Check user capabilities.
        if ( !self::current_user_can_proceed( $type ) && !$is_wp_json_request ) {
            return new \WP_Error('invalid', __( 'User should have enough permissions', 'gitomatic-wp' ));
        }
        // Initialize WordPress filesystem.
        if ( !function_exists( 'WP_Filesystem' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        WP_Filesystem();
        // load file which loads most of the classes needed for package installation.
        if ( !function_exists( 'get_plugin_data' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        // Set up package installation parameters.
        if ( $type === 'theme' ) {
            $package_destination_dir = get_theme_root( $package_slug ) . DIRECTORY_SEPARATOR . $package_slug;
            $skin = new \GitomaticWP\ThemeInstallerSkin();
            // Create a new instance of the Theme_Upgrader class.
            $package_upgrader = new \Theme_Upgrader($skin);
        } else {
            $package_destination_dir = WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . $package_slug;
            $skin = new \GitomaticWP\PluginInstallerSkin();
            // Create a new instance of the Plugin_Upgrader class.
            $package_upgrader = new \Plugin_Upgrader($skin);
        }
        // Set upgrader arguments.
        $package_upgrader->generic_strings();
        /*
         * The low-level run() method does not enable the package checks that
         * Plugin_Upgrader::install() and Theme_Upgrader::install() normally use.
         * Add the matching check explicitly because this installer needs a custom
         * destination directory.
         */
        self::add_package_validation_filters( $package_upgrader );
        /*
         * For private repositories, attach authentication headers to the exact
         * package zip URL request only (download_url() fires http_request_args).
         * Provider redirect targets (e.g. codeload, signed CDN URLs) must not
         * receive credentials.
         */
        $auth_filter = null;
        if ( ! empty( $options['is_private_repository'] ) ) {
            $provider_class_name = Helper::get_provider_class( $provider_type );
            $headers = $provider_class_name::get_download_headers( $options );
            if ( ! empty( $headers ) ) {
                $target_host = wp_parse_url( $package_zip_url, PHP_URL_HOST );
                $auth_filter = function ( $args, $url ) use ( $package_zip_url, $target_host, $headers ) {
                    $req_host = wp_parse_url( $url, PHP_URL_HOST );
                    if ( $url === $package_zip_url || ( ! empty( $target_host ) && $req_host === $target_host ) ) {
                        $args['headers'] = array_merge( (array) $args['headers'], $headers );
                    }
                    return $args;
                };
                add_filter( 'http_request_args', $auth_filter, 10, 2 );
            }
        }
        /*
         * Capture the HTTP response during package download so we can report
         * precise reasons (HTTP 401, 404, rate limits, cURL errors) if it fails.
         */
        $last_http_response   = null;
        $http_response_filter = function ( $response, $args, $url ) use ( &$last_http_response ) {
            if ( is_wp_error( $response ) ) {
                $last_http_response = array(
                    'url'           => $url,
                    'error_message' => $response->get_error_message(),
                    'error_code'    => $response->get_error_code(),
                );
            } else {
                $code     = (int) wp_remote_retrieve_response_code( $response );
                $message  = wp_remote_retrieve_response_message( $response );
                $body     = '';
                $has_auth = ! empty( $args['headers']['Authorization'] ) || ! empty( $args['headers']['PRIVATE-TOKEN'] );
                if ( ! empty( $args['filename'] ) && file_exists( $args['filename'] ) ) {
                    $file_content = @file_get_contents( $args['filename'], false, null, 0, 2048 );
                    if ( false !== $file_content ) {
                        $body = $file_content;
                    }
                } else {
                    $body = wp_remote_retrieve_body( $response );
                }
                $last_http_response = array(
                    'url'           => $url,
                    'code'          => $code,
                    'message'       => $message,
                    'body'          => $body,
                    'authenticated' => $has_auth,
                );
            }
            return $response;
        };
        add_filter( 'http_response', $http_response_filter, 10, 3 );

        $extra = array( 'type' => $type, 'action' => 'install', 'gitomatic_wp_folder' => $package_slug );
        if ( is_dir( $package_destination_dir ) && method_exists( $package_upgrader, 'restore_temp_backup' ) ) {
            $extra['temp_backup'] = array(
                'slug' => $package_slug,
                'src'  => dirname( $package_destination_dir ),
                'dir'  => $type . 's',
            );
        }
        try {
            $result = $package_upgrader->run( array(
                'package'           => $package_zip_url,
                'destination'       => $package_destination_dir,
                'clear_destination' => true,
                'clear_working'     => true,
                'hook_extra'        => $extra,
            ) );
            // Complete core's rollback and cleanup while the deployment lock is held.
            if ( isset( $extra['temp_backup'] ) ) {
                if ( remove_action( 'shutdown', array( $package_upgrader, 'restore_temp_backup' ), 10 ) ) {
                    $restored = $package_upgrader->restore_temp_backup();
                    if ( is_wp_error( $restored ) ) {
                        remove_action( 'shutdown', array( $package_upgrader, 'delete_temp_backup' ), 100 );
                        return $restored;
                    }
                }
                if ( remove_action( 'shutdown', array( $package_upgrader, 'delete_temp_backup' ), 100 ) ) {
                    $package_upgrader->delete_temp_backup();
                }
            }
        } finally {
            remove_filter( 'http_response', $http_response_filter, 10 );
            if ( null !== $auth_filter ) {
                remove_filter( 'http_request_args', $auth_filter, 10, 2 );
            }
            self::remove_package_validation_filters( $package_upgrader );
            $package_upgrader->maintenance_mode( false );
        }
        if ( $result === false ) {
            if ( isset( $package_upgrader->skin ) && method_exists( $package_upgrader->skin, 'get_errors' ) ) {
                $skin_errors = $package_upgrader->skin->get_errors();
                if ( is_wp_error( $skin_errors ) && $skin_errors->has_errors() ) {
                    return $skin_errors;
                }
            }
            return new \WP_Error('invalid', __( 'Some error occurred during package installation.', 'gitomatic-wp' ));
        } elseif ( is_wp_error( $result ) ) {
            return self::enhance_install_error( $result, $last_http_response, $provider_type, $options );
        }
        if ( 'theme' === $type ) {
            wp_clean_themes_cache( false );
        } else {
            wp_clean_plugins_cache( false );
        }
        return true;
    }

    /**
     * Enhance an installation WP_Error with rich HTTP / provider diagnostics.
     *
     * @param \WP_Error $error              Original WP_Error from upgrader.
     * @param array     $last_http_response Captured HTTP response details.
     * @param string    $provider_type      Provider slug (github, gitlab, etc.).
     * @param array     $options            Installation options (branch, etc.).
     * @return \WP_Error
     */
    private static function enhance_install_error( $error, $last_http_response, $provider_type, $options ) {
        if ( 'download_failed' !== $error->get_error_code() ) {
            return $error;
        }

        $error_data   = $error->get_error_data();
        $http_status  = null;
        $api_message  = '';
        $hint         = '';
        $has_auth     = false;
        $branch_name  = isset( $options['branch'] ) ? $options['branch'] : '';

        if ( ! empty( $last_http_response ) ) {
            if ( ! empty( $last_http_response['error_message'] ) ) {
                // cURL or network transport error.
                $api_message = $last_http_response['error_message'];
                $hint        = $api_message;
            } elseif ( ! empty( $last_http_response['code'] ) ) {
                $http_status = (int) $last_http_response['code'];
                $raw_body    = isset( $last_http_response['body'] ) ? $last_http_response['body'] : '';

                if ( ! empty( $raw_body ) ) {
                    $decoded = json_decode( $raw_body, true );
                    if ( is_array( $decoded ) && ! empty( $decoded['message'] ) ) {
                        $api_message = (string) $decoded['message'];
                    } elseif ( is_array( $decoded ) && ! empty( $decoded['error'] ) ) {
                        $api_message = is_string( $decoded['error'] ) ? $decoded['error'] : ( isset( $decoded['error']['message'] ) ? (string) $decoded['error']['message'] : '' );
                    }
                }

                if ( empty( $api_message ) && ! empty( $last_http_response['message'] ) ) {
                    $api_message = $last_http_response['message'];
                }

                $has_auth    = isset( $last_http_response['authenticated'] ) && $last_http_response['authenticated'];
                if ( 404 === $http_status ) {
                    $branch_hint = '' !== $branch_name ? sprintf( ' (branch: "%s")', $branch_name ) : '';
                    if ( $has_auth ) {
                        $hint = sprintf(
                            __( 'HTTP 404: Not Found%s. Check the repository, branch name, and read access for the saved credentials.', 'gitomatic-wp' ),
                            $branch_hint
                        );
                    } else {
                        $hint = sprintf(
                            __( 'HTTP 404: Not Found%s. The request was sent without an access token header. Check that "Is Private Repository" is enabled in package settings and the access token is saved.', 'gitomatic-wp' ),
                            $branch_hint
                        );
                    }
                } elseif ( 401 === $http_status ) {
                    $hint = __( 'HTTP 401: Unauthorized. The access token or credentials are invalid, expired, or missing.', 'gitomatic-wp' );
                } elseif ( 403 === $http_status ) {
                    $hint = __( 'HTTP 403: Forbidden. Token lacks required permissions/scopes, organization requires SSO authorization, or API rate limit was exceeded.', 'gitomatic-wp' );
                } elseif ( 429 === $http_status ) {
                    $hint = __( 'HTTP 429: Too Many Requests. Git provider API rate limit exceeded.', 'gitomatic-wp' );
                } else {
                    $hint = sprintf( __( 'HTTP %d response from provider.', 'gitomatic-wp' ), $http_status );
                }
            }
        }

        if ( empty( $hint ) && is_string( $error_data ) && '' !== $error_data ) {
            $hint = $error_data;
        }

        $combined_message = __( 'Download failed.', 'gitomatic-wp' );
        if ( ! empty( $hint ) ) {
            $combined_message = rtrim( $combined_message, '.' ) . ': ' . $hint;
            if ( ! empty( $api_message ) && false === strpos( $hint, $api_message ) ) {
                $combined_message .= ' (' . $api_message . ')';
            }
        }

        return new \WP_Error(
            'download_failed',
            $combined_message,
            array(
                'http_status'   => $http_status,
                'api_message'   => $api_message,
                'authenticated' => $has_auth,
                'raw_error'     => $error_data,
                'provider'      => $provider_type,
            )
        );
    }

}
