<?php

namespace ACFFieldOpenstreetmap\Core;

use ACFFieldOpenstreetmap\Compat;

class MapProxy extends Singleton {

	/**
	 *	First line of the proxy config files. They hold access tokens and live in wp-content/, where PHP
	 *	runs: requested over HTTP, a config file stops right there. The proxy reads it as data
	 *	(include/proxy.php), never includes it. Not in wp-content/maps/: the Nginx rule suggested by
	 *	1.7.1 – 1.7.2 serves that directory as static files.
	 */
	const CONFIG_GUARD = "<?php exit; ?>\n";

	/** @var array provider keys to be proxied */
	private $proxies;

	/** @var bool|null whether a config file can be read over HTTP, checked once per request */
	private $config_exposed = null;

	/**
	 *	@inheritdoc
	 */
	protected function __construct() {

		add_filter( 'acf_osm_leaflet_providers', [ $this, 'proxify_providers' ], 50 );

		foreach ( [ 'acf_osm_provider_tokens', 'acf_osm_providers', 'acf_osm_proxy' ] as $option ) {
			add_action( "update_option_{$option}", [ $this, 'setup_proxies' ] );
			add_action( "add_option_{$option}", [ $this, 'setup_proxies' ] ); // first write, e.g. with WP-CLI
		}

		add_action( 'load-settings_page_acf_osm', [ $this, 'maybe_repair' ] );
		add_action( 'wp_uninitialize_site', [ $this, 'delete_site_config' ] );
	}

	/**
	 *	Rebuild a missing proxy config when the settings page opens, e.g. once the server runs PHP in wp-content/
	 *	again, or after a failed update. Saving unchanged settings doesn't trigger setup_proxies().
	 *
	 *	@action load-settings_page_acf_osm
	 */
	public function maybe_repair() {
		if ( ( apply_filters( 'acf_osm_force_proxy', false ) || $this->get_proxies() ) && ! file_exists( $this->get_config_file() ) ) {
			$this->setup_proxies();
		}
	}

	/**
	 *	A deleted site's config holds its access tokens, and the proxy would keep using them.
	 *
	 *	@action wp_uninitialize_site
	 *	@param \WP_Site $site
	 */
	public function delete_site_config( $site ) {
		$config_file = $this->get_config_file( (int) $site->blog_id );
		if ( file_exists( $config_file ) ) {
			wp_delete_file( $config_file );
		}
	}

	/**
	 *	@return array holding keys of proxied providers
	 */
	public function get_proxies() {
		if ( ! isset( $this->proxies ) ) {
			$this->proxies = array_keys( array_filter( (array) get_option( 'acf_osm_proxy' ) ) );
		}
		return $this->proxies;
	}

	/**
	 *	Absolute path to the proxy directory (wp-content/maps/).
	 *
	 *	@return string Trailing-slashed path.
	 */
	public function get_proxy_dir() {
		return trailingslashit( trailingslashit( WP_CONTENT_DIR ) . $this->get_proxy_path() );
	}

	/**
	 *	Absolute path to the proxy config of a site, in wp-content/ next to the proxy directory.
	 *
	 *	@param int|null $blog_id Defaults to the current site.
	 *	@return string
	 */
	public function get_config_file( $blog_id = null ) {
		$blog_id = $blog_id ?: get_current_blog_id();
		$file    = 'acf-osm-proxy-config';
		if ( is_multisite() && ! is_main_site( $blog_id ) ) {
			$file .= '-' . absint( $blog_id );
		}
		return trailingslashit( WP_CONTENT_DIR ) . $file . '.php';
	}

	/**
	 *	Whether the proxy directory has been installed.
	 *
	 *	@return boolean
	 */
	public function is_installed() {
		return file_exists( $this->get_proxy_dir() . 'index.php' );
	}

	/**
	 *	Apply proxy config to all providers
	 *	@filter acf_osm_leaflet_providers
	 */
	public function proxify_providers( $providers ) {

		$proxies = $this->get_proxies();
		$force   = apply_filters( 'acf_osm_force_proxy', false );

		foreach ( $providers as $provider_key => &$provider ) {
			if ( $force || in_array( $provider_key, $proxies ) ) {
				$provider = $this->proxify_provider( $provider_key, $provider );
			}
		}

		return $providers;
	}

	/**
	 *	Apply proxy config to provider
	 *
	 *	@param string $provider_key
	 *	@param array $provider
	 *	@return array
	 */
	private function proxify_provider( $provider_key, $provider ) {

		// make sure variant config is an array
		$provider = LeafletProviders::instance()->unify_provider_variants( $provider );

		$provider = $this->proxify_tileset( $provider, $provider_key );

		if ( isset( $provider['variants'] ) ) {
			foreach ( $provider['variants'] as $variant_key => $variant ) {
				if ( ! isset( $variant['url'] ) ) {
					$variant['url'] = $provider['url'];
				}
				$provider['variants'][$variant_key] = $this->proxify_tileset( $variant, $provider_key, $variant_key );
			}
		}
		return $provider;
	}

	/**
	 *	Apply proxy config to provider or variant
	 *
	 *	@param string $provider_key
	 *	@param array $provider
	 *	@return array
	 */
	private function proxify_tileset( $tileset, $provider_key, $variant_key = '' ) {

		// resolution capability?
		if ( str_contains( $tileset['url'], '{r}' ) ) {
			$url_params_part = '{z}/{x}/{y}{r}';
		} else {
			$url_params_part = '{z}/{x}/{y}';
		}

		// remove unneeded props from provider (and hide access tokens on the way)
		foreach ( array_keys( $tileset['options'] ) as $option ) {
			if ( str_contains( $tileset['url'], "{{$option}}" ) ) {
				unset( $tileset['options'][$option] );
			}
		}

		// reconfigure url
		$tileset['url'] = content_url( $this->get_proxy_path( $provider_key, $variant_key ) . $url_params_part );

		return $tileset;
	}

	/**
	 *	Setup proxy directory in wp-content/ and save proxy config in uploads.
	 *
	 *	@action update_option_acf_osm_provider_tokens
	 *	@action update_option_acf_osm_providers
	 *	@action update_option_acf_osm_proxy
	 */
	public function setup_proxies() {

		// Plugin updated, but no admin page visited since (WP-CLI, cron, …): migrate every site first,
		// so no site is left with the configs of an older version.
		Core::instance()->maybe_upgrade();

		// the proxy option may just have changed
		$this->proxies = null;

		$result = $this->setup_proxy_dir();
		if ( ! is_wp_error( $result ) ) {
			$result = $this->save_proxy_config();
		}
		// shown on the settings page, once per request (each changed option runs this)
		if ( is_wp_error( $result ) && function_exists( 'add_settings_error' )
			&& ! in_array( $result->get_error_code(), wp_list_pluck( get_settings_errors( 'acf_osm' ), 'code' ), true )
		) {
			add_settings_error( 'acf_osm', $result->get_error_code(), $result->get_error_message() );
		}
		if ( is_wp_error( $result ) && defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::warning( $result->get_error_message() );
		}
		return $result;
	}

	/**
	 *	1.7.1 – 1.7.2 wrote the proxy config, access tokens included, as a public JSON file in uploads/.
	 *	Regenerate the proxy directory and every site's config, which deletes those files.
	 *
	 *	@return true|\WP_Error
	 */
	public function upgrade() {

		$site_ids = is_multisite() ? get_sites( [ 'fields' => 'ids', 'number' => 0 ] ) : [ get_current_blog_id() ];
		$errors   = [];

		// The public configs first, of every site, whatever happens to the proxy directory.
		// Files that can't be deleted make the update fail: it is retried on the next admin request.
		foreach ( $site_ids as $site_id ) {
			if ( is_multisite() ) {
				switch_to_blog( $site_id );
			}
			$result = $this->delete_legacy_config();
			if ( is_multisite() ) {
				restore_current_blog();
			}
			if ( is_wp_error( $result ) ) {
				$errors[] = $result->get_error_message();
			}
		}

		// Then the proxy itself. If this fails, the proxy is off until the settings page is opened again,
		// which shows the error (see maybe_repair()).
		if ( ! is_wp_error( $this->setup_proxy_dir( true ) ) ) {
			foreach ( $site_ids as $site_id ) {
				if ( is_multisite() ) {
					switch_to_blog( $site_id );
				}
				$this->proxies = null;
				$result = $this->save_proxy_config();
				if ( is_multisite() ) {
					restore_current_blog();
				}
				// retried on the next admin request, once the server is fixed: regenerates every site
				if ( is_wp_error( $result ) && 'acf-osm-exposed' === $result->get_error_code() && ! in_array( $result->get_error_message(), $errors, true ) ) {
					$errors[] = $result->get_error_message();
				}
			}
		}
		$this->proxies = null;

		return $errors ? new \WP_Error( 'acf-osm', implode( ' ', $errors ) ) : true;
	}

	/**
	 *	WP_Filesystem() is only loaded in wp-admin, but the proxy config is also saved when the
	 *	options are updated elsewhere (cron, REST, front end, …).
	 *
	 *	@return bool
	 */
	private function init_filesystem() {
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		return WP_Filesystem();
	}

	/**
	 *	Save the proxy config of the current site in the proxy directory, and delete public
	 *	copies left by older versions.
	 *
	 *	@return true|\WP_Error
	 */
	public function save_proxy_config() {

		if ( ! $this->init_filesystem() ) {
			return new \WP_Error( 'acf-osm', __( 'No Filesystem', 'acf-openstreetmap-field' ) );
		}

		global $wp_filesystem;

		// public configs of older versions: also when the new config can't be written. Reported, but the
		// new config is still written (the current maps/index.php no longer reads the old ones).
		$legacy_result = $this->delete_legacy_config();

		$config_file = $this->get_config_file();

		if ( ! $wp_filesystem->is_writable( dirname( $config_file ) ) ) {
			return new \WP_Error( 'acf-osm', __( 'Filesystem not writable', 'acf-openstreetmap-field' ) );
		}

		$proxies = $this->get_proxies();
		// evaluated in the request saving the config: hook it unconditionally (e.g. in an mu-plugin)
		$force   = apply_filters( 'acf_osm_force_proxy', false );

		$proxy_config = [];
		foreach ( LeafletProviders::instance()->get_providers( ['credentials'], true ) as $provider_key => $provider ) {

			// only what the proxy serves: no access tokens of providers loaded directly by the browser,
			// and no open relay for them
			if ( ! $force && ! in_array( $provider_key, $proxies ) ) {
				continue;
			}

			$provider = LeafletProviders::instance()->unify_provider_variants( $provider );

			$subdomains = $provider['options']['subdomains'] ?? 'abc';

			$proxy_config[ $provider_key ] = $this->build_tileset_config(
				$provider['url'],
				[ $provider['options'] ],
				$subdomains
			);

			if ( isset( $provider['variants'] ) ) {
				foreach ( $provider['variants'] as $variant_key => $variant ) {

					$proxy_config["{$provider_key}.{$variant_key}"] = $this->build_tileset_config(
						$variant['url'] ?? $provider['url'],
						// variant placeholders first, then provider-level ones
						[ $variant['options'], $provider['options'] ],
						$variant['options']['subdomains'] ?? $subdomains
					);
				}
			}
		}

		if ( ! $proxy_config ) {
			if ( $wp_filesystem->exists( $config_file ) ) {
				$wp_filesystem->delete( $config_file );
			}
			return $legacy_result;
		}

		// checked with a harmless file, before any access token is written
		if ( $this->is_config_exposed( dirname( $config_file ) ) ) {
			// the whole directory is exposed: also the configs of the other sites
			foreach ( (array) glob( trailingslashit( dirname( $config_file ) ) . 'acf-osm-proxy-config*.php' ) as $file ) {
				$wp_filesystem->delete( $file );
			}
			return new \WP_Error( 'acf-osm-exposed', __( 'Proxied map layers can’t load: the map proxy configuration, which holds your access tokens, would be downloadable from your website. Make sure your web server runs PHP files in wp-content/ (also with an upper case .PHP extension), then open this settings page again.', 'acf-openstreetmap-field' ) );
		}

		// no < or > in the JSON: nothing in it can end the guard line
		$content = self::CONFIG_GUARD . wp_json_encode( $proxy_config, JSON_HEX_TAG | JSON_HEX_AMP );

		// written only when it differs: a tile request may read it while it is rewritten
		if ( $content !== $wp_filesystem->get_contents( $config_file ) && ! $wp_filesystem->put_contents( $config_file, $content ) ) {
			return new \WP_Error( 'acf-osm', __( 'Filesystem not writable', 'acf-openstreetmap-field' ) );
		}

		return $legacy_result;
	}

	/**
	 *	Whether the web server hands out a config file instead of running it. Checked once per request with a
	 *	harmless file holding a random marker, requested from the site itself. A failing request (no loopback,
	 *	authentication, …) counts as not exposed.
	 *
	 *	@param string $dir where the config files go
	 *	@return bool
	 */
	private function is_config_exposed( $dir ) {

		global $wp_filesystem;

		if ( null === $this->config_exposed ) {

			$this->config_exposed = false;

			$marker = wp_generate_password( 20, false );
			$probe  = 'acf-osm-proxy-probe-' . strtolower( wp_generate_password( 12, false ) ) . '.php';
			if ( ! $wp_filesystem->put_contents( trailingslashit( $dir ) . $probe, self::CONFIG_GUARD . wp_json_encode( [ 'probe' => $marker ] ) ) ) {
				return $this->config_exposed;
			}

			// also with an upper case extension: on a case-insensitive file system the server may run .php but serve .PHP
			foreach ( [ $probe, substr( $probe, 0, -3 ) . 'PHP' ] as $name ) {
				$response = wp_remote_get( content_url( $name ), [
					'timeout'   => 3,
					'sslverify' => false, // the site's own certificate may be self-signed
				] );
				if ( ! is_wp_error( $response ) && str_contains( wp_remote_retrieve_body( $response ), $marker ) ) {
					$this->config_exposed = true;
					break;
				}
			}

			$wp_filesystem->delete( trailingslashit( $dir ) . $probe );
		}
		return $this->config_exposed;
	}

	/**
	 *	Delete the configs older versions wrote for the current site: uploads/acf-osm-proxy-config.json
	 *	(1.7.1 – 1.7.2, public), uploads/acf-osm-proxy-config.php (< 1.7.1) and the multisite copies in
	 *	wp-content/maps/ (served as static files by the Nginx rule of 1.7.1 – 1.7.2).
	 *
	 *	@return true|\WP_Error
	 */
	private function delete_legacy_config() {

		if ( ! $this->init_filesystem() ) {
			return new \WP_Error( 'acf-osm', __( 'No Filesystem', 'acf-openstreetmap-field' ) );
		}

		global $wp_filesystem;

		$upload_dir = wp_upload_dir( null, false );
		$legacy     = [
			trailingslashit( $upload_dir['basedir'] ) . 'acf-osm-proxy-config.json',
			trailingslashit( $upload_dir['basedir'] ) . 'acf-osm-proxy-config.php',
			$this->get_proxy_dir() . 'acf-osm-proxy-config.json',
			$this->get_proxy_dir() . 'acf-osm-proxy-config.php',
		];
		foreach ( $legacy as $file ) {
			if ( $wp_filesystem->exists( $file ) && ! $wp_filesystem->delete( $file ) ) {
				/* translators: %s file path */
				return new \WP_Error( 'acf-osm', sprintf( __( 'Could not delete %s', 'acf-openstreetmap-field' ), $file ) );
			}
		}
		return true;
	}

	/**
	 *	Build a single proxy tileset config entry.
	 *
	 *	@param string $base_url     base tile URL
	 *	@param array  $option_sets  one or more option arrays whose placeholders are
	 *	                            substituted into the URL, applied in order
	 *	@param string $subdomains   resolved subdomains for {s} substitution at request time
	 *	@return array
	 */
	private function build_tileset_config( $base_url, array $option_sets, $subdomains ) {
		foreach ( $option_sets as $options ) {
			$base_url = $this->generate_url( $base_url, $options );
		}
		return [
			'base_url'   => $base_url,
			'subdomains' => $subdomains,
		];
	}

	/**
	 *	@param string $base_url
	 *	@param array $options
	 */
	private function generate_url( $base_url, $options ) {
		$url = $base_url;
		foreach ( $options as $option => $value ) {
			if ( is_scalar( $value ) ) {
				$url = str_replace( "{{$option}}", str_replace(' ', '%20', $value), $url );
			}
		}
		return $url;
	}

	/**
	 *	Setup proxy directory in wp-content/maps/
	 */
	public function setup_proxy_dir( $force = false ) {
		global $wp_filesystem;

		if ( ! $this->init_filesystem() ) {
			return new \WP_Error( 'acf-osm', __( 'No Filesystem', 'acf-openstreetmap-field' ) );
		}

		$proxy_path = trailingslashit( trailingslashit( WP_CONTENT_DIR ) . $this->get_proxy_path() ) ;

		wp_mkdir_p( $proxy_path );

		if ( ! $wp_filesystem->is_writable( $proxy_path ) ) {
			return new \WP_Error( 'acf-osm', __( 'Filesystem not writable', 'acf-openstreetmap-field' ) );
		}

		// written only when they differ: a tile request may come in while they are rewritten
		$content = '# Generously generated by ACF OpenStreetMap Field Plugin' . "\n";
		$content .= 'RewriteEngine On' . "\n";
		$content .= 'RewriteBase /wp-content/maps' . "\n";
		$content .= 'RewriteRule . index.php [L]' . "\n";

		if ( $content !== $wp_filesystem->get_contents( $proxy_path . '.htaccess' ) ) {
			$wp_filesystem->put_contents( $proxy_path . '.htaccess', $content );
		}

		// include/proxy.php reads the configs itself. The constant tells it that this index.php is current,
		// so it can skip the configs of older versions.
		$content = '<?php' . "\n";
		$content .= '/* Generously generated by ACF OpenStreetMap Field Plugin */' . "\n";
		$content .= "define( 'ACF_OSM_PROXY_INDEX', 2 );\n";
		$content .= sprintf(
			"include_once '%s/include/proxy.php';\n",
			untrailingslashit( Core::instance()->get_plugin_dir() )
		);

		// also replaces the index.php of older versions, and one pointing to a moved plugin
		if ( $content !== $wp_filesystem->get_contents( $proxy_path . 'index.php' ) ) {
			if ( ! $wp_filesystem->put_contents( $proxy_path . 'index.php', $content ) ) {
				return new \WP_Error( 'acf-osm', __( 'Filesystem not writable', 'acf-openstreetmap-field' ) );
			}
			wp_opcache_invalidate( $proxy_path . 'index.php', true );
		}

		// The main site's config is the base for every site (sites without own settings, acf_osm_force_proxy)
		if ( is_multisite() && ! is_main_site() && ( $force || ! $wp_filesystem->exists( $this->get_config_file( get_main_site_id() ) ) ) ) {

			switch_to_blog( get_main_site_id() );

			$this->proxies = null;
			$this->save_proxy_config();
			$this->proxies = null;

			restore_current_blog();
		}

		return true;
	}

	/**
	 *	Remove Proxy Directory
	 */
	public function reset_proxy_dir() {

		if ( ! $this->init_filesystem() ) {
			return new \WP_Error( 'acf-osm', __( 'No Filesystem', 'acf-openstreetmap-field' ) );
		}

		global $wp_filesystem;

		$proxy_path = trailingslashit( trailingslashit( WP_CONTENT_DIR ) . $this->get_proxy_path() ) ;

		if ( ! $wp_filesystem->is_writable( $proxy_path ) ) {
			return new \WP_Error( 'acf-osm', __( 'Filesystem not writable', 'acf-openstreetmap-field' ) );
		}

		return $wp_filesystem->rmdir( $proxy_path, true );
	}

	/**
	 *	@param string $provider_key
	 *	@param string $variant_key
	 *	@return string
	 */
	private function get_proxy_path( $provider_key = '', $variant_key = '' ) {
		$path = 'maps';
		if ( $provider_key ) {
			if ( is_multisite() && ! is_main_site() ) {
				$path .= sprintf('/sites/%d', get_current_blog_id() );
			}
			$path .= '/'  . $provider_key;
			if ( $variant_key ) {
				$path .= '.' . $variant_key;
			}
		}
		return trailingslashit( $path );
	}

}
