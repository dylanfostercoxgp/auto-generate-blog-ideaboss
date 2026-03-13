<?php
/**
 * GitHub-based auto-updater for Auto Generate Blog by ideaBoss.
 *
 * Checks the GitHub releases API for new versions and integrates
 * with the WordPress update system so admins get update notifications
 * in WP Admin → Plugins without needing to upload files manually.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AGB_Auto_Updater {

	private $github_user = 'dylanfostercoxgp';
	private $github_repo = 'auto-generate-blog-ideaboss';
	private $plugin_slug = 'auto-generate-blog-ideaboss';

	/** How long to cache the GitHub release check (in seconds). */
	const CACHE_DURATION = 43200; // 12 hours

	public function __construct() {
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_for_update' ) );
		add_filter( 'plugins_api',                           array( $this, 'plugin_info' ), 10, 3 );
		add_filter( 'upgrader_post_install',                 array( $this, 'post_install' ), 10, 3 );
		add_action( 'upgrader_process_complete',             array( $this, 'purge_cache' ), 10, 2 );
	}

	/* -----------------------------------------------------------------------
	 * Inject update info into WordPress's update transient
	 * -------------------------------------------------------------------- */

	public function check_for_update( $transient ) {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		$release = $this->get_latest_release();
		if ( ! $release || empty( $release['tag_name'] ) ) {
			return $transient;
		}

		$latest_version = ltrim( $release['tag_name'], 'v' );

		if ( version_compare( AGB_VERSION, $latest_version, '<' ) ) {
			$zip_url = $this->get_zip_url( $release );

			if ( ! empty( $zip_url ) ) {
				$transient->response[ AGB_PLUGIN_BASENAME ] = (object) array(
					'slug'        => $this->plugin_slug,
					'plugin'      => AGB_PLUGIN_BASENAME,
					'new_version' => $latest_version,
					'url'         => "https://github.com/{$this->github_user}/{$this->github_repo}",
					'package'     => $zip_url,
					'icons'       => array(),
					'banners'     => array(),
					'tested'      => '',
					'requires_php'=> '7.4',
					'compatibility'=> new stdClass(),
				);
			}
		} else {
			// Tell WP the plugin is up to date (prevents false "update available" notices)
			$transient->no_update[ AGB_PLUGIN_BASENAME ] = (object) array(
				'slug'         => $this->plugin_slug,
				'plugin'       => AGB_PLUGIN_BASENAME,
				'new_version'  => AGB_VERSION,
				'url'          => "https://github.com/{$this->github_user}/{$this->github_repo}",
				'package'      => '',
			);
		}

		return $transient;
	}

	/* -----------------------------------------------------------------------
	 * Provide plugin info in the "View version details" modal
	 * -------------------------------------------------------------------- */

	public function plugin_info( $res, $action, $args ) {
		if ( $action !== 'plugin_information' ) {
			return $res;
		}
		if ( ! isset( $args->slug ) || $args->slug !== $this->plugin_slug ) {
			return $res;
		}

		$release = $this->get_latest_release();
		if ( ! $release ) {
			return $res;
		}

		$latest_version = ltrim( $release['tag_name'], 'v' );
		$zip_url        = $this->get_zip_url( $release );
		$changelog      = isset( $release['body'] ) ? wp_kses_post( $release['body'] ) : '';

		$res                = new stdClass();
		$res->name          = 'Auto Generate Blog by ideaBoss';
		$res->slug          = $this->plugin_slug;
		$res->version       = $latest_version;
		$res->author        = '<a href="https://ideaboss.io" target="_blank">ideaBoss</a>';
		$res->homepage      = "https://github.com/{$this->github_user}/{$this->github_repo}";
		$res->requires      = '5.6';
		$res->tested        = '6.5';
		$res->requires_php  = '7.4';
		$res->download_link = $zip_url;
		$res->last_updated  = isset( $release['published_at'] ) ? $release['published_at'] : '';
		$res->sections      = array(
			'description' => '<p>Automate WordPress blog post creation using Claude AI — generate from a prompt or import and format articles from any URL.</p>',
			'changelog'   => $changelog ? '<pre>' . esc_html( $changelog ) . '</pre>' : '<p>See <a href="' . esc_url( $res->homepage ) . '/releases" target="_blank">GitHub Releases</a>.</p>',
		);

		return $res;
	}

	/* -----------------------------------------------------------------------
	 * After install: rename the unzipped folder to the correct plugin slug
	 * -------------------------------------------------------------------- */

	public function post_install( $response, $hook_extra, $result ) {
		if ( ! isset( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== AGB_PLUGIN_BASENAME ) {
			return $response;
		}

		global $wp_filesystem;

		$plugin_dir = WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . $this->plugin_slug;

		// Only move if the destination differs (GitHub zips include a hash prefix)
		if ( $result['destination'] !== $plugin_dir ) {
			$wp_filesystem->move( $result['destination'], $plugin_dir );
			$result['destination'] = $plugin_dir;
		}

		// Re-activate if it was active before the update
		if ( is_plugin_active( AGB_PLUGIN_BASENAME ) ) {
			activate_plugin( AGB_PLUGIN_BASENAME );
		}

		return $result;
	}

	/* -----------------------------------------------------------------------
	 * Purge cached release data after an update completes
	 * -------------------------------------------------------------------- */

	public function purge_cache( $upgrader, $options ) {
		if (
			isset( $options['action'] ) && $options['action'] === 'update' &&
			isset( $options['type'] ) && $options['type'] === 'plugin' &&
			isset( $options['plugins'] ) && in_array( AGB_PLUGIN_BASENAME, (array) $options['plugins'], true )
		) {
			delete_transient( 'agb_github_release' );
		}
	}

	/* -----------------------------------------------------------------------
	 * Fetch the latest GitHub release (cached for 12 hours)
	 * -------------------------------------------------------------------- */

	private function get_latest_release() {
		$cached = get_transient( 'agb_github_release' );
		if ( false !== $cached ) {
			return $cached;
		}

		$api_url  = "https://api.github.com/repos/{$this->github_user}/{$this->github_repo}/releases/latest";
		$response = wp_remote_get(
			$api_url,
			array(
				'timeout'    => 15,
				'user-agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . get_bloginfo( 'url' ),
				'headers'    => array(
					'Accept' => 'application/vnd.github.v3+json',
				),
			)
		);

		if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
			// Cache a failure for 1 hour to avoid hammering the API
			set_transient( 'agb_github_release', false, HOUR_IN_SECONDS );
			return false;
		}

		$release = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $release ) || json_last_error() !== JSON_ERROR_NONE ) {
			set_transient( 'agb_github_release', false, HOUR_IN_SECONDS );
			return false;
		}

		set_transient( 'agb_github_release', $release, self::CACHE_DURATION );
		return $release;
	}

	/* -----------------------------------------------------------------------
	 * Get the downloadable zip URL from a release object
	 * -------------------------------------------------------------------- */

	private function get_zip_url( $release ) {
		// Prefer attached assets (proper plugin zip)
		if ( ! empty( $release['assets'] ) ) {
			foreach ( $release['assets'] as $asset ) {
				if ( substr( $asset['name'], -4 ) === '.zip' ) {
					return $asset['browser_download_url'];
				}
			}
		}
		// Fall back to GitHub's automatic source zip
		return isset( $release['zipball_url'] ) ? $release['zipball_url'] : '';
	}
}
