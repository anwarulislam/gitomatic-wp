<?php

/**
 * Gitomatic WP
 *
 * @since             1.0.0
 * @package           Gitomatic_WP
 *
 * @wordpress-plugin
 * Plugin Name:       Gitomatic WP
 * Description:       This plugin can install and automatically update themes and plugins hosted on GitHub, Bitbucket, GitLab, or Gitea.
 * Version:           1.3.1
 * Author:            Anwarul Islam
 * Author URI:        https://anwar.bd/
 * License:           GPL-3.0
 * License URI:       http://www.gnu.org/licenses/gpl-3.0.txt
 * Text Domain:       gitomatic-wp
 * Domain Path:       /languages
 * Requires PHP:      7.0
 * Requires at least: 4.4
 */
// If this file is called directly, abort.
if ( !defined( 'WPINC' ) ) {
    die;
}
if ( !defined( 'GITOMATIC_WP_FILE' ) ) {
    define( 'GITOMATIC_WP_FILE', __FILE__ );
}
if ( !defined( 'GITOMATIC_WP_PATH' ) ) {
    define( 'GITOMATIC_WP_PATH', plugin_dir_path( __FILE__ ) );
}
if ( !defined( 'GITOMATIC_WP_URL' ) ) {
    define( 'GITOMATIC_WP_URL', plugin_dir_url( __FILE__ ) );
}
if ( !defined( 'GITOMATIC_WP_SLUG' ) ) {
    define( 'GITOMATIC_WP_SLUG', 'gitomatic_wp' );
    // = plugin slug (used for options)
}
if ( !defined( 'ABSPATH' ) ) {
    exit;
}
require_once __DIR__ . '/vendor/autoload.php';
use GitomaticWP\Helper;
use GitomaticWP\Admin;
use GitomaticWP\ApiRequests\PackageUpdate;
use GitomaticWP\CLI\GitomaticCommand;
if ( !class_exists( 'GitomaticWPInit' ) ) {
    /**
     * Class GitomaticWPInit
     */
    class GitomaticWPInit {
        /**
         * GitomaticWPInit constructor.
         */
        public function __construct() {
            if ( is_admin() ) {
                add_action( 'plugins_loaded', array($this, 'admin_init') );
                add_action( 'plugins_loaded', array($this, 'load_textdomain') );
            }
            // Init api requests.
            add_action( 'plugins_loaded', array($this, 'api_requests_init') );
            register_activation_hook( GITOMATIC_WP_FILE, array($this, 'plugin_activation') );
        }

        /**
         * Set default settings.
         *
         * @return void
         */
        public function plugin_activation() {
            if ( !Helper::get_api_secret() ) {
                Helper::generate_api_secret();
            }
            flush_rewrite_rules();
            // Flush rewrite rules to ensure the new endpoints are registered.
        }

        /**
         * Load and initialize wp-admin side classes
         */
        public function admin_init() {
            new Admin();
        }

        /**
         * Load and initialize api requests classes
         */
        public function api_requests_init() {
            // init theme update webhook endpoint.
            new PackageUpdate();
        }

        /**
         * Load plugin textdomain.
         *
         * @return void
         */
        public function load_textdomain() {
            load_plugin_textdomain( 'gitomatic-wp', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
        }

    }

    new GitomaticWPInit();
}
// Register WP-CLI commands when running under WP-CLI.
if ( defined( 'WP_CLI' ) && WP_CLI ) {
    WP_CLI::add_command( 'gitomatic-wp', GitomaticCommand::class );
}