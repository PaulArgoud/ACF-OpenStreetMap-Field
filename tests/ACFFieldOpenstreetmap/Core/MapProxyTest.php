<?php

use ACFFieldOpenstreetmap\Core;
use ACFFieldOpenstreetmap\Settings;

class MapProxyTest extends SingletonTestCase {

	/**
	 * Install Proxy dir
	 * @covers ACFFieldOpenstreetmap\WPCLI\Commands\MapProxy::__construct
	 */
	public function test_construct() {
		$proxy = Core\MapProxy::instance();
		$this->assertHasFilter( 'acf_osm_leaflet_providers', [ $proxy, 'proxify_providers' ], 50 );

		$this->assertHasAction( 'update_option_acf_osm_provider_tokens', [ $proxy, 'setup_proxies' ] );
		$this->assertHasAction( 'update_option_acf_osm_providers', [ $proxy, 'setup_proxies' ] );
		$this->assertHasAction( 'update_option_acf_osm_proxy', [ $proxy, 'setup_proxies' ] );
	}

	/**
	 * Install Proxy dir
	 * @covers ACFFieldOpenstreetmap\WPCLI\Commands\MapProxy::install
	 */
	public function test_install() {

		$proxy = Core\MapProxy::instance();

		$proxy->setup_proxy_dir( false );
		$this->assertFileExists(ABSPATH . 'wp-content/maps/.htaccess');
		$this->assertFileExists(ABSPATH . 'wp-content/maps/index.php');
	}

	/**
	 * Uninstall Proxy dir
	 * @covers ACFFieldOpenstreetmap\WPCLI\Commands\MapProxy::uninstall
	 */
	public function test_uninstall() {
		// default settings
		$proxy = Core\MapProxy::instance();
		$proxy->reset_proxy_dir();

		$this->assertFalse(file_exists( ABSPATH . 'wp-content/maps/.htaccess' ), 'wp-content/maps/.htaccess still exists');
		$this->assertFalse(file_exists( ABSPATH . 'wp-content/maps/index.php' ), 'wp-content/maps/index.php still exists');
	}

	/**
	 * Configure local proxy
	 * @covers ACFFieldOpenstreetmap\WPCLI\Commands\MapProxy::configure
	 */
	public function test_configure() {

		// no network: the exposure check's request to the site itself fails, which counts as not exposed
		add_filter( 'pre_http_request', function() {
			return new \WP_Error( 'http_request_blocked', 'offline' );
		} );

		$upload_dir = wp_upload_dir( null, false );
		$proxy      = Core\MapProxy::instance();
		$proxy->setup_proxy_dir( false );

		// public configs of older versions
		file_put_contents( $upload_dir['basedir'] . '/acf-osm-proxy-config.json', '{}' );
		file_put_contents( $upload_dir['basedir'] . '/acf-osm-proxy-config.php', '<?php return [];' );
		file_put_contents( $proxy->get_proxy_dir() . 'acf-osm-proxy-config.json', '{}' );

		update_option( 'acf_osm_proxy', [ 'OpenStreetMap' => '1' ] ); // runs setup_proxies()

		// the config holds access tokens: never in uploads/, guarded against HTTP requests in the proxy dir
		$this->assertFileDoesNotExist( $upload_dir['basedir'] . '/acf-osm-proxy-config.json' );
		$this->assertFileDoesNotExist( $upload_dir['basedir'] . '/acf-osm-proxy-config.php' );
		$this->assertFileDoesNotExist( $proxy->get_proxy_dir() . 'acf-osm-proxy-config.json' );
		$this->assertStringContainsString( "define( 'ACF_OSM_PROXY_INDEX', 2 );", file_get_contents( $proxy->get_proxy_dir() . 'index.php' ) );
		$this->assertSame( ABSPATH . 'wp-content/acf-osm-proxy-config.php', $proxy->get_config_file() );
		$this->assertFileExists( $proxy->get_config_file() );

		$contents = file_get_contents( $proxy->get_config_file() );
		$this->assertStringStartsWith( Core\MapProxy::CONFIG_GUARD, $contents );

		// only the proxied providers
		$config = json_decode( substr( $contents, strlen( Core\MapProxy::CONFIG_GUARD ) ), true );
		$this->assertIsArray( $config );
		$this->assertArrayHasKey( 'OpenStreetMap', $config );
		$this->assertArrayHasKey( 'OpenStreetMap.Mapnik', $config );
		$this->assertSame( [ 'OpenStreetMap' ], array_unique( array_map( function( $key ) {
			return explode( '.', $key )[0];
		}, array_keys( $config ) ) ) );

		// nothing proxied: no config
		update_option( 'acf_osm_proxy', [] );
		$this->assertFileDoesNotExist( $proxy->get_config_file() );
	}

	/**
	 * Only access tokens are kept: nothing else can replace a part of the catalogue, like a tile url
	 */
	public function test_tokens_are_allowlisted() {

		$settings = Settings\SettingsOpenStreetMap::instance();
		$tokens   = $settings->sanitize_provider_tokens( [
			'Thunderforest' => [ 'options' => [ 'apikey' => ' my-key ', 'variant' => 'evil' ], 'url' => 'http://169.254.169.254/' ],
			'OpenStreetMap' => [ 'url' => 'http://169.254.169.254/' ],
			'NoSuchProvider' => [ 'options' => [ 'apikey' => 'x' ] ],
		] );
		$this->assertSame( [ 'Thunderforest' => [ 'options' => [ 'apikey' => 'my-key' ] ] ], $tokens );

		// tokens saved by older versions
		update_option( 'acf_osm_provider_tokens', [ 'OpenStreetMap' => [ 'url' => 'http://169.254.169.254/' ] ] );
		$providers = Core\LeafletProviders::instance()->get_providers( [ 'credentials' ], true );
		$this->assertStringStartsWith( 'https://', $providers['OpenStreetMap']['url'] );
		update_option( 'acf_osm_provider_tokens', [] );
	}
}
