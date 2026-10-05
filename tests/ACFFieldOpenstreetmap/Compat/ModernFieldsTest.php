<?php

use ACFFieldOpenstreetmap\Compat;
use ACFFieldOpenstreetmap\Settings;

class ModernFieldsTest extends WP_UnitTestCase {

	public function tear_down() {
		delete_option( Compat\ModernFields::OPTION );
		parent::tear_down();
	}

	/**
	 * @covers ACFFieldOpenstreetmap\Compat\ModernFields::tile_url
	 * @covers ACFFieldOpenstreetmap\Compat\ModernFields::attribution
	 * @covers ACFFieldOpenstreetmap\Compat\ModernFields::get_tile_layer
	 */
	public function test_filters() {
		$compat = Compat\ModernFields::instance();

		// early, so a site can still override the layer
		$this->assertSame( 5, has_filter( 'modern-fields/maps/osm/tile_url', [ $compat, 'tile_url' ] ) );
		$this->assertSame( 5, has_filter( 'modern-fields/maps/osm/attribution', [ $compat, 'attribution' ] ) );

		// nothing selected: the default of Modern Fields
		$this->assertSame( 'default-url', apply_filters( 'modern-fields/maps/osm/tile_url', 'default-url', 'osm' ) );
		$this->assertSame( 'default-attribution', apply_filters( 'modern-fields/maps/osm/attribution', 'default-attribution' ) );

		update_option( Compat\ModernFields::OPTION, [ 'layer' => 'CartoDB.Positron' ] );
		$url = apply_filters( 'modern-fields/maps/osm/tile_url', 'default-url', 'osm' );
		// the only placeholders left are those L.TileLayer fills in
		$this->assertSame( 'https://a.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', $url );
		$attribution = apply_filters( 'modern-fields/maps/osm/attribution', 'default-attribution' );
		$this->assertStringContainsString( 'carto', strtolower( $attribution ) );
		$this->assertStringContainsString( 'openstreetmap.org/copyright', $attribution );
		$this->assertStringNotContainsString( '{attribution.', $attribution );

		// an overlay or an unknown layer can't be the map
		foreach ( [ 'OpenSeaMap', 'CartoDB.PositronOnlyLabels', 'Nope', [ 'array' ] ] as $layer ) {
			update_option( Compat\ModernFields::OPTION, [ 'layer' => $layer ] );
			$this->assertSame( 'default-url', apply_filters( 'modern-fields/maps/osm/tile_url', 'default-url', 'osm' ) );
		}
		update_option( Compat\ModernFields::OPTION, 'garbage' );
		$this->assertSame( '', $compat->get_layer_key() );
	}

	/**
	 * @covers ACFFieldOpenstreetmap\Compat\ModernFields::get_layer_choices
	 */
	public function test_layer_choices() {
		$choices = Compat\ModernFields::instance()->get_layer_choices();

		$this->assertArrayHasKey( 'OpenStreetMap.Mapnik', $choices );
		$this->assertSame( 'OpenStreetMap.Mapnik', $choices['OpenStreetMap.Mapnik'] );
		// overlays are left out
		$this->assertArrayNotHasKey( 'OpenSeaMap', $choices );
		$this->assertArrayNotHasKey( 'CartoDB.PositronOnlyLabels', $choices );
		// the zoom range is shown when Modern Fields can zoom past it
		$this->assertStringContainsString( '17', $choices['OpenTopoMap'] );
	}

	/**
	 * Modern Fields can't pass tileSize / zoomOffset
	 *
	 * @covers ACFFieldOpenstreetmap\Compat\ModernFields::get_layer_choices
	 * @covers ACFFieldOpenstreetmap\Compat\ModernFields::get_tile_layer
	 */
	public function test_512px_tiles() {
		update_option( 'acf_osm_provider_tokens', [ 'MapTiler' => [ 'options' => [ 'key' => 'k' ] ] ] );

		$this->assertNotNull( ACFFieldOpenstreetmap\Core\LeafletProviders::instance()->get_tile_layer( 'MapTiler.Streets' ) );
		$this->assertArrayNotHasKey( 'MapTiler.Streets', Compat\ModernFields::instance()->get_layer_choices() );

		update_option( Compat\ModernFields::OPTION, [ 'layer' => 'MapTiler.Streets' ] );
		$this->assertSame( 'default-url', apply_filters( 'modern-fields/maps/osm/tile_url', 'default-url', 'osm' ) );

		delete_option( 'acf_osm_provider_tokens' );
	}

	/**
	 * @covers ACFFieldOpenstreetmap\Settings\SettingsOpenStreetMap::sanitize_modern_fields
	 */
	public function test_sanitize() {
		$settings = Settings\SettingsOpenStreetMap::instance();

		$this->assertSame( [ 'layer' => 'OpenStreetMap.Mapnik' ], $settings->sanitize_modern_fields( [ 'layer' => 'OpenStreetMap.Mapnik' ] ) );
		$this->assertSame( [ 'layer' => '' ], $settings->sanitize_modern_fields( [ 'layer' => 'OpenSeaMap' ] ) );
		$this->assertSame( [ 'layer' => '' ], $settings->sanitize_modern_fields( [ 'layer' => '<script>' ] ) );
		$this->assertSame( [ 'layer' => '' ], $settings->sanitize_modern_fields( null ) );
		$this->assertSame( [ 'layer' => '' ], $settings->sanitize_modern_fields( [ 'layer' => [ 'x' ] ] ) );
	}
}
