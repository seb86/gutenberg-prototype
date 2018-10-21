<?php
/**
 * Plugin Name: Gutenberg Prototype
 * Plugin URI: https://github.com/seb86/gutenberg-prototype
 * Version: 1.0.0
 * Description: Run beta or release candidate versions of Gutenberg from GitHub.
 * Author: Sébastien Dumont
 * Author URI: https://sebastiendumont.com
 * GitHub Plugin URI: https://github.com/seb86/gutenberg-prototype
 *
 * Text Domain: gutenberg-prototype
 * Domain Path: /languages/
 *
 * Requires at least: 4.5
 * Tested up to: 4.9.8
 *
 * Copyright: © 2018 Sébastien Dumont
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Gutenberg_Prototype' ) ) {

	class Gutenberg_Prototype {

		/**
		 * Plugin Configuration
		 *
		 * @access private
		 */
		private $config = array();

		/**
		 * GitHub Data
		 *
		 * @access protected
		 * @static
		 */
		protected static $_instance = null;

		/**
		 * Plugin Version
		 *
		 * @access private
		 * @static
		 */
		private static $version = '1.0.0';

		/**
		 * Main Instance
		 *
		 * @access public
		 * @static
		 * @return Gutenberg_Prototype - Main instance
		 */
		public static function instance() {
			return self::$_instance = is_null( self::$_instance ) ? new self() : self::$_instance;
		}

		/**
		 * Cloning is forbidden.
		 *
		 * @access public
		 */
		public function __clone() {
			_doing_it_wrong( __FUNCTION__, __( 'Cloning this object is forbidden.', 'gutenberg-prototype' ), $this->version );
		}

		/**
		 * Unserializing instances of this class is forbidden.
		 *
		 * @access public
		 */
		public function __wakeup() {
			_doing_it_wrong( __FUNCTION__, __( 'Unserializing instances of this class is forbidden.', 'gutenberg-prototype' ), $this->version );
		}

		/**
		 * Constructor
		 *
		 * @access public
		 * @static
		 */
		public function __construct() {
			$this->config = array(
				'plugin_file'        => 'gutenberg/gutenberg.php',
				'slug'               => 'gutenberg',
				'proper_folder_name' => 'gutenberg',
				'api_url'            => 'https://api.github.com/repos/WordPress/gutenberg',
				'github_url'         => 'https://github.com/WordPress/gutenberg',
				'requires'           => '4.5',
				'tested'             => '4.9.8'
			);

			add_action( 'plugins_loaded', array( $this, 'flush_update_cache' ) );
			add_action( 'plugins_loaded', array( $this, 'check_gutenberg_installed' ) );
			add_action( 'init', array( $this, 'load_text_domain' ), 0 );
		} // END __construct()

		/**
		 * Run these filters once Gutenberg is installed and active.
		 *
		 * @access public
		 * @return void
		 */
		public function gutenberg_active() {
			add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'api_check' ) );
			add_filter( 'plugins_api_result', array( $this, 'get_plugin_info' ), 10, 3 );
			add_filter( 'upgrader_source_selection', array( $this, 'upgrader_source_selection' ), 10, 3 );

			// Auto update Gutenberg.
			add_filter( 'auto_update_plugin', 'auto_update_gutenberg', 100, 2 );
		} // END gutenberg_active()

		/**
		 * Load the plugin text domain once the plugin has initialized.
		 *
		 * @access public
		 * @return void
		 */
		public function load_text_domain() {
			load_plugin_textdomain( 'gutenberg-prototype', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
		} // END load_text_domain()

		/**
		 * Run once the plugin has loaded to flush the update cache.
		 *
		 * @access public
		 * @static
		 */
		public static function flush_update_cache() {
			delete_site_transient( 'update_plugins' ); // Clear all plugin update data
		} // END flush_update_cache()

		/**
		 * Checks if Gutenberg is installed before running filters for the WordPress updater.
		 *
		 * @access public
		 * @return bool|void
		 */
		public function check_gutenberg_installed() {
			if ( ! defined( 'GUTENBERG_VERSION' ) ) {
				add_action( 'admin_notices', array( $this, 'gutenberg_not_installed' ) );
				return false;
			}

			// Gutenberg is active.
			self::gutenberg_active();
		} // END check_gutenberg_installed()

		/**
		 * Gutenberg is Not Installed or Activated Notice.
		 *
		 * @access public
		 * @return void
		 */
		public function gutenberg_not_installed() {
			echo '<div class="notice notice-error">';
			
				echo '<p>' . sprintf( __( '%1$s requires %2$sGutenberg%3$s to be installed and activated in order to serve updates from GitHub.', 'gutenberg-prototype' ), esc_html__( 'Gutenberg Prototype', 'gutenberg-prototype' ), '<strong>', '</strong>' ) . '</p>';

				echo '<p>';

				if ( ! is_plugin_active( 'gutenberg/gutenberg.php' ) && current_user_can( 'activate_plugin', 'gutenberg/gutenberg.php' ) ) :

					echo '<a href="' . esc_url( wp_nonce_url( self_admin_url( 'plugins.php?action=activate&plugin=gutenberg/gutenberg.php&plugin_status=active' ), 'activate-plugin_gutenberg/gutenberg.php' ) ) . '" class="button button-primary">' . esc_html__( 'Activate Gutenberg', 'gutenberg-prototype' ) . '</a>';

				else:

					echo sprintf( __( '%1$s requires %2$sGutenberg%3$s to be installed and activated in order to serve updates.', 'gutenberg-prototype' ), esc_html__( 'Gutenberg Prototype', 'gutenberg-prototype' ), '<a href="' . esc_url( wp_nonce_url( self_admin_url( 'update.php?action=install-plugin&plugin=gutenberg' ), 'install-plugin_gutenberg' ) ) . '">', '</a>' );

					if ( current_user_can( 'install_plugins' ) ) {
						$url = wp_nonce_url( self_admin_url( 'update.php?action=install-plugin&plugin=gutenberg' ), 'install-plugin_gutenberg' );
					} else {
						$url = 'http://wordpress.org/plugins/gutenberg/';
					}

					echo '<a href="' . esc_url( $url ) . '" class="button button-primary">' . esc_html__( 'Install Gutenberg', 'gutenberg-prototype' ) . '</a>';

				endif;

				if ( current_user_can( 'deactivate_plugin', 'gutenberg-prototype/gutenberg-prototype.php' ) ) :
					echo '<a href="' . esc_url( wp_nonce_url( 'plugins.php?action=deactivate&plugin=gutenberg-prototype/gutenberg-prototype.php&plugin_status=inactive', 'deactivate-plugin_gutenberg-prototype/gutenberg-prototype.php' ) ) . '" class="button button-secondary">' . esc_html__( 'Turn off Gutenberg Prototype plugin', 'gutenberg-prototype' ) . '</a>';
				endif;

				echo '</p>';

			echo '</div>';
		} // END gutenberg_not_installed()

		/**
		 * Update the required plugin data arguments.
		 *
		 * @access public
		 * @return array
		 */
		public function set_update_args() {
			$latest_prerelease            = $this->get_latest_prerelease();
			$this->config['plugin_name']  = 'Gutenberg ' . $latest_prerelease;
			$this->config['new_version']  = str_replace( 'v', '', $latest_prerelease );
			$this->config['last_updated'] = $this->get_date();
			$this->config['changelog']    = $this->get_changelog();
			$this->config['zip_name']     = $latest_prerelease;
			$this->config['zip_url']      = 'https://github.com/WordPress/gutenberg/releases/download/' . $latest_prerelease . '/gutenberg.zip';
		} // END set_update_args()

		/**
		 * Check wether or not the transients need to be overruled
		 * and API needs to be called for every single page load.
		 *
		 * @access public
		 * @return bool overrule or not
		 */
		public function overrule_transients() {
			return ( defined( 'GUTENBERG_PROTOTYPE_FORCE_UPDATE' ) && GUTENBERG_PROTOTYPE_FORCE_UPDATE );
		} // END overrule_transients()

		/**
		 * Get New Version from GitHub.
		 *
		 * @access public
		 * @return int $tagged_version the version number
		 */
		public function get_latest_prerelease() {
			$tagged_version = get_site_transient( md5( $this->config['slug'] ) . '_latest_tag' );

			if ( $this->overrule_transients() || empty( $tagged_version ) ) {

				$raw_response = wp_remote_get( trailingslashit( $this->config['api_url'] ) . 'releases' );

				if ( is_wp_error( $raw_response ) ) {
					return false;
				}

				$releases       = json_decode( $raw_response['body'] );
				$tagged_version = false;

				if ( is_array( $releases ) ) {
					foreach ( $releases as $release ) {

						// If the release is a pre-release then return the tagged version.
						if ( $release->prerelease ) {
							$tagged_version = $release->tag_name;
							break;
						}
					}
				}

				// Refresh every 6 hours.
				if ( ! empty( $tagged_version ) ) {
					set_site_transient( md5( $this->config['slug'] ) . '_latest_tag', $tagged_version, 60 * 60 * 6 );
				}
			}

			return $tagged_version;
		} // END get_latest_prerelease()

		/**
		 * Get Published date of New Version from GitHub.
		 *
		 * @access public
		 * @return string $published_date of the latest prerelease
		 */
		public function get_latest_prerelease_date() {
			$published_date = get_site_transient( md5( $this->config['slug'] ) . '_latest_published_date' );

			if ( $this->overrule_transients() || empty( $published_date ) ) {

				$raw_response = wp_remote_get( trailingslashit( $this->config['api_url'] ) . 'releases' );

				if ( is_wp_error( $raw_response ) ) {
					return false;
				}

				$releases  = json_decode( $raw_response['body'] );
				$published_date = false;

				if ( is_array( $releases ) ) {
					foreach ( $releases as $release ) {

						// If the release is a pre-release then return the published date.
						if ( $release->prerelease ) {
							$published_date = $release->published_at;
							break;
						}
					}
				}

				// Refresh every 6 hours.
				if ( ! empty( $published_date ) ) {
					set_site_transient( md5( $this->config['slug'] ) . '_latest_published_date', $published_date, 60 * 60 * 6 );
				}
			}

			return $published_date;
		} // END get_latest_prerelease_date()

		/**
		 * Get Changelog of New Version from GitHub.
		 *
		 * @access public
		 * @return string $changelog of the latest prerelease
		 */
		public function get_latest_prerelease_changelog() {
			$changelog = get_site_transient( md5( $this->config['slug'] ) . '_latest_changelog' );

			if ( $this->overrule_transients() || empty( $changelog ) ) {

				$raw_response = wp_remote_get( trailingslashit( $this->config['api_url'] ) . 'releases' );

				if ( is_wp_error( $raw_response ) ) {
					return false;
				}

				$releases  = json_decode( $raw_response['body'] );
				$changelog = false;

				if ( is_array( $releases ) ) {
					foreach ( $releases as $release ) {

						// If the release is a pre-release then return the body.
						if ( $release->prerelease ) {
							if ( ! class_exists( 'Parsedown' ) ) {
								include_once( 'parsedown.php' );
							}
							$Parsedown = new Parsedown();

							$changelog = $Parsedown->text( $release->body );
							break;
						}
					}
				}

				// Refresh every 6 hours.
				if ( ! empty( $changelog ) ) {
					set_site_transient( md5( $this->config['slug'] ) . '_latest_changelog', $changelog, 60 * 60 * 6 );
				}
			}

			return $changelog;
		} // END get_latest_prerelease_changelog()

		/**
		 * Get GitHub Data from the specified repository.
		 *
		 * @access public
		 * @return array $github_data the data
		 */
		public function get_github_data() {
			if ( ! empty( $this->github_data ) ) {
				$github_data = $this->github_data;
			} else {
				$github_data = get_site_transient( md5( $this->config['slug'] ) . '_github_data' );

				if ( $this->overrule_transients() || ( ! isset( $github_data ) || ! $github_data || '' == $github_data ) ) {
					$github_data = wp_remote_get( $this->config['api_url'] );

					if ( is_wp_error( $github_data ) ) {
						return false;
					}

					$github_data = json_decode( $github_data['body'] );

					// refresh every 6 hours
					set_site_transient( md5( $this->config['slug'] ) . '_github_data', $github_data, 60 * 60 * 6 );
				}

				// Store the data in this class instance for future calls
				$this->github_data = $github_data;
			}

			return $github_data;
		} // END get_github_data()

		/**
		 * Get published date.
		 *
		 * @access public
		 * @return string $_date the date
		 */
		public function get_date() {
			$_date = $this->get_latest_prerelease_date();
			return ! empty( $_date ) ? date( 'Y-m-d', strtotime( $_date ) ) : false;
		} // END get_date()

		/**
		 * Get plugin changelog.
		 *
		 * @access public
		 * @return string $_changelog the changelog of the release
		 */
		public function get_changelog() {
			$_changelog = $this->get_latest_prerelease_changelog();
			return ! empty( $_changelog ) ? $_changelog : false;
		} // END get_changelog()

		/**
		 * Get plugin data from the currently installed version.
		 *
		 * @access public
		 * @return object $data the data
		 */
		public function get_plugin_data() {
			return get_plugin_data( WP_PLUGIN_DIR . '/' . $this->config['plugin_file'] );
		} // END get_plugin_data()

		/**
		 * Hook into the plugin update check and connect to GitHub.
		 *
		 * @access public
		 * @param  object $transient the plugin data transient
		 * @return object $transient updated plugin data transient
		 */
		public function api_check( $transient ) {
			// Check if the transient contains the 'checked' information
			// If not, just return its value without hacking it
			if ( empty( $transient->checked ) ) {
				return $transient;
			}

			// Clear our transient.
			delete_site_transient( md5( $this->config['slug'] ) . '_latest_tag' );
			delete_site_transient( md5( $this->config['slug'] ) . '_latest_changelog' );

			// Get plugin data from the currently installed version of Gutenberg.
			$plugin_data = $this->get_plugin_data();

			$version = $plugin_data['Version'];

			// Update tags.
			$this->set_update_args();

			// Check the version and decide if its new.
			$update = version_compare( $this->config['new_version'], $version, '>' );

			// If the version is not newer then return default.
			if ( ! $update ) {
				return $transient;
			}

			// Only set the updater to download if its a beta or pre-release version.
			if ( $this->is_beta_version( $this->config['new_version'] ) || $this->is_rc_version( $this->config['new_version'] ) ) {
				$response              = new stdClass;
				$response->plugin      = $this->config['slug'];
				//$response->version     = $plugin_data['Version'];
				$response->author      = $plugin_data['Author'];
				$response->homepage    = $plugin_data['PluginURI'];
				$response->new_version = $this->config['new_version'];
				$response->slug        = $this->config['slug'];
				$response->url         = $this->config['github_url'];
				$response->package     = $this->config['zip_url'];

				// If response is false, don't alter the transient.
				if ( false !== $response ) {
					$transient->response[ $this->config['plugin_file'] ] = $response;
				}
			}

			return $transient;
		} // END api_check()

		/**
		 * Filters the Plugin Installation API response results.
		 *
		 * @access  public
		 * @param   object|WP_Error $response Response object or WP_Error.
		 * @param   string          $action   The type of information being requested from the Plugin Installation API.
		 * @param   object          $args     Plugin API arguments.
		 * @return  object          $response The plugin results.
		 */
		public function get_plugin_info( $response, $action, $args ) {
			// Check if this call for the API is for the right plugin.
			if ( ! isset( $response->slug ) || $response->slug != $this->config['slug'] ) {
				return $false;
			}

			// Get plugin data from the currently installed version of Gutenberg.
			$plugin_data = $this->get_plugin_data();

			// Update tags.
			$this->set_update_args();

			// New Version
			$new_version = $this->config['new_version'];

			// Prepare warning!
			$warning = '';

			if ( $this->is_stable_version( $new_version ) ) {
				$warning = sprintf( __( '%1$s%3$sThis is a stable release%3$s%2$s', 'gutenberg-prototype' ), '<h1>', '</h1>', '<span>&#9888;</span>' );
			}

			if ( $this->is_beta_version( $new_version ) ) {
				$warning = sprintf( __( '%1$s%3$sThis is a beta release%3$s%2$s', 'gutenberg-prototype' ), '<h1>', '</h1>', '<span>&#9888;</span>' );
			}

			if ( $this->is_rc_version( $new_version ) ) {
				$warning = sprintf( __( '%1$s%3$sThis is a pre-release%3$s%2$s', 'gutenberg-prototype' ), '<h1>', '</h1>', '<span>&#9888;</span>' );
			}

			// If the new version is no different than the one installed then reset results.
			if ( version_compare( $response->version, $new_version, '=' ) ) {
				$response->name        = 'Gutenberg';
				$response->plugin_name = 'Gutenberg';
				$response->version     = $plugin_data['Version'];

				return $response;
			}

			// Update the results to return.
			$response->name            = $this->config['plugin_name'];
			$response->plugin_name     = $this->config['plugin_name'];
			$response->version         = $new_version;
			$response->author          = $plugin_data['Author'];
			$response->author_homepage = 'https://wordpress.org/gutenberg/';
			$response->homepage        = $plugin_data['PluginURI'];
			$response->requires        = $this->config['requires'];
			$response->tested          = $this->config['tested'];
			$response->last_updated    = $this->config['last_updated'];
			$response->download_link   = $this->config['zip_url'];
			$response->contributors    = array(
				'joen'       => 'https://profiles.wordpress.org/joen',
				'matveb'     => 'https://profiles.wordpress.org/matveb',
				'karmatosed' => 'https://profiles.wordpress.org/karmatosed',
				'sebd86'     => 'https://profiles.wordpress.org/sebd86',
			);

			// Add WordPress dot org banners for recognition.
			$response->banners         = array(
				'low'  => plugins_url( 'assets/banner-772x250.jpg', __FILE__ ),
				'high' => plugins_url( 'assets/banner-1544x500.jpg', __FILE__ ),
			);

			// Update the changelog section.
			$response->sections['changelog'] = $this->config['changelog'];

			// Apply warning to all sections if any.
			foreach ( $response->sections as $key => $section ) {
				$response->sections[ $key ] = $warning . $section;
			}

			// Return new response.
			return $response;
		} // END get_plugin_info()

		/**
		 * Rename the unzipped folder to be the same as the existing folder.
		 *
		 * @access public
		 * @global $wp_filesystem
		 * @param  string           $source        File source location.
		 * @param  string           $remote_source Remote file source location.
		 * @param  WP_Upgrader      $upgrader      WP_Upgrader Upgrader instance.
		 * @return string|WP_Error
		 */
		public function upgrader_source_selection( $source, $remote_source, $upgrader ) {
			global $wp_filesystem;

			if ( strstr( $source, '/gutenberg-' ) ) {
				$corrected_source = trailingslashit( $remote_source ) . $this->config['proper_folder_name'] . '/';

				$upgrader->skin->feedback( sprintf(
					__( 'Renaming %s to %s&#8230;', 'gutenberg-prototype' ), '<span class="code">' . basename($source) . '</span>', '<span class="code">' . $this->config['proper_folder_name'] . '</span>'
				) );

				if ( $wp_filesystem->move( $source, $corrected_source, true ) ) {
					$upgrader->skin->feedback( 'Directory successfully renamed.', 'gutenberg-prototype' );

					return $corrected_source;
				} else {
					return new WP_Error( 'rename-failed', __( 'Unable to rename the update to match the existing directory.', 'gutenberg-prototype' ) );
				}
			}

			return $source;
		} // END upgrader_source_selection()

		/**
		 * Return true if version string is a beta version.
		 *
		 * @access protected
		 * @static
		 * @param  string $version_str Version string.
		 * @return bool
		 */
		protected static function is_beta_version( $version_str ) {
			return strpos( $version_str, 'beta' ) !== false;
		} // END is_beta_version()

		/**
		 * Return true if version string is a Release Candidate.
		 *
		 * @access protected
		 * @static
		 * @param  string $version_str Version string.
		 * @return bool
		 */
		protected static function is_rc_version( $version_str ) {
			return strpos( $version_str, 'rc' ) !== false;
		} // END is_rc_version()

		/**
		 * Return true if version string is a stable version.
		 *
		 * @access protected
		 * @static
		 * @param  string $version_str Version string.
		 * @return bool
		 */
		protected static function is_stable_version( $version_str ) {
			return ! self::is_beta_version( $version_str ) && ! self::is_rc_version( $version_str );
		} // END is_stable_version()

	} // END class

} // END if class exists

return Gutenberg_Prototype::instance();
