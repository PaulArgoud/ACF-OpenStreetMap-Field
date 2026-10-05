<?php

use ACFFieldOpenstreetmap\Core;

class LeafletProvidersTest extends WP_UnitTestCase {

	/**
	 * Legacy credentials stored for a provider that no longer exists in the
	 * catalogue (e.g. the old `HERE` app_id/app_key) must not be injected as a
	 * malformed provider. Regression test for #133.
	 *
	 * @covers ACFFieldOpenstreetmap\Core\LeafletProviders::get_providers
	 */
	public function test_legacy_token_does_not_leak() {
		update_option( 'acf_osm_provider_tokens', [
			'HERE' => [ 'app_id' => 'x', 'app_key' => 'y' ], // removed provider
		] );

		$providers = Core\LeafletProviders::instance()->get_providers( [ 'credentials' ] );

		$this->assertArrayNotHasKey( 'HERE', $providers, 'Legacy HERE token must not be injected as a provider (#133)' );
		$this->assertArrayHasKey( 'OpenStreetMap', $providers, 'Real providers must still be present' );

		// every returned provider must be a well-formed array (has options)
		foreach ( $providers as $key => $provider ) {
			$this->assertIsArray( $provider, "Provider {$key} should be an array" );
			$this->assertArrayHasKey( 'options', $provider, "Provider {$key} should expose options" );
		}

		delete_option( 'acf_osm_provider_tokens' );
	}

	/**
	 * Tokens for an existing provider are merged in.
	 *
	 * @covers ACFFieldOpenstreetmap\Core\LeafletProviders::get_providers
	 */
	public function test_known_provider_token_is_merged() {
		update_option( 'acf_osm_provider_tokens', [
			'Thunderforest' => [ 'options' => [ 'apikey' => 'abc123' ] ],
		] );

		$providers = Core\LeafletProviders::instance()->get_providers( [ 'credentials' ] );

		$this->assertArrayHasKey( 'Thunderforest', $providers );
		$this->assertSame( 'abc123', $providers['Thunderforest']['options']['apikey'] );

		delete_option( 'acf_osm_provider_tokens' );
	}

	/**
	 * @covers ACFFieldOpenstreetmap\Core\LeafletProviders::is_token_placeholder
	 */
	public function test_is_token_placeholder() {
		$this->assertTrue( Core\LeafletProviders::is_token_placeholder( '<your api key>' ) );
		$this->assertTrue( Core\LeafletProviders::is_token_placeholder( '<>' ) );

		$this->assertFalse( Core\LeafletProviders::is_token_placeholder( 'abc123' ) );
		$this->assertFalse( Core\LeafletProviders::is_token_placeholder( 'a<b>c' ) );
		$this->assertFalse( Core\LeafletProviders::is_token_placeholder( 42 ) );
		$this->assertFalse( Core\LeafletProviders::is_token_placeholder( null ) );
	}

	/**
	 * @covers ACFFieldOpenstreetmap\Core\LeafletProviders::get_tile_layer
	 */
	public function test_get_tile_layer() {
		$providers = Core\LeafletProviders::instance();

		$layer = $providers->get_tile_layer( 'OpenStreetMap.Mapnik' );
		$this->assertSame( 'https://tile.openstreetmap.org/{z}/{x}/{y}.png', $layer['url'] );
		$this->assertFalse( $layer['isOverlay'] );
		$this->assertStringContainsString( 'openstreetmap.org/copyright', $layer['options']['attribution'] );

		// variant options merged and substituted (GeoportailFrance is disabled by default: it has bounds)
		$layer = $providers->get_tile_layer( 'GeoportailFrance.orthos', [ 'credentials' ] );
		$this->assertStringContainsString( '&FORMAT=image/jpeg&LAYER=ORTHOIMAGERY.ORTHOPHOTOS&TILEMATRIX={z}&TILEROW={y}&TILECOL={x}', $layer['url'] );
		$this->assertSame( 19, $layer['options']['maxZoom'] );
		$this->assertArrayNotHasKey( 'variant', $layer['options'] );
		$this->assertTrue( $providers->get_tile_layer( 'GeoportailFrance.parcels', [ 'credentials' ] )['isOverlay'] );

		// Leaflet options also in the URL still apply
		$layer = $providers->get_tile_layer( 'NASAGIBS.ModisTerraAOD', [ 'credentials' ] );
		$this->assertStringContainsString( '/GoogleMapsCompatible_Level6/{z}/{y}/{x}.png', $layer['url'] );
		$this->assertSame( 6, $layer['options']['maxZoom'] );
		$this->assertTrue( $layer['isOverlay'] );

		// unknown
		$this->assertNull( $providers->get_tile_layer( 'Nope' ) );
		$this->assertNull( $providers->get_tile_layer( 'OpenStreetMap.Nope' ) );
		$this->assertNull( $providers->get_tile_layer( '' ) );

		// no access token
		$this->assertNull( $providers->get_tile_layer( 'Thunderforest.OpenCycleMap' ) );

		update_option( 'acf_osm_provider_tokens', [
			'Thunderforest' => [ 'options' => [ 'apikey' => 'abc123' ] ],
		] );
		$layer = $providers->get_tile_layer( 'Thunderforest.OpenCycleMap' );
		$this->assertSame( 'https://{s}.tile.thunderforest.com/cycle/{z}/{x}/{y}.png?apikey=abc123', $layer['url'] );
		$this->assertArrayNotHasKey( 'apikey', $layer['options'] );

		// proxied: the token stays on the server
		add_filter( 'acf_osm_force_proxy', '__return_true' );
		$cache = new ReflectionProperty( Core\LeafletProviders::class, 'providers_cache' );
		$cache->setAccessible( true );
		$cache->setValue( $providers, [] );

		$layer = $providers->get_tile_layer( 'Thunderforest.OpenCycleMap' );
		$this->assertSame( content_url( 'maps/Thunderforest.OpenCycleMap/{z}/{x}/{y}' ), $layer['url'] );
		$this->assertStringNotContainsString( 'abc123', wp_json_encode( $layer ) );

		remove_filter( 'acf_osm_force_proxy', '__return_true' );
		$cache->setValue( $providers, [] );
		delete_option( 'acf_osm_provider_tokens' );

		// a placeholder used twice
		$add_provider = function( $providers ) {
			$providers['Twice'] = [
				'url'     => 'https://{variant}.example.org/{z}/{x}/{y}.png?style={variant}',
				'options' => [ 'variant' => 'v' ],
			];
			return $providers;
		};
		add_filter( 'acf_osm_leaflet_providers', $add_provider );
		$this->assertSame( 'https://v.example.org/{z}/{x}/{y}.png?style=v', $providers->get_tile_layer( 'Twice' )['url'] );
		remove_filter( 'acf_osm_leaflet_providers', $add_provider );
		$cache->setValue( $providers, [] );
	}
}
