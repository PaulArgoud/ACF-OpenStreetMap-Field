<?php

class TemplatesTest extends WP_UnitTestCase {

	/**
	 * @param string $html
	 * @param string $attr
	 * @return mixed JSON decoded attribute value
	 */
	private function get_json_attr( $html, $attr ) {
		$this->assertMatchesRegularExpression( '/' . $attr . '="([^"]*)"/', $html );
		preg_match( '/' . $attr . '="([^"]*)"/', $html, $match );
		return json_decode( wp_specialchars_decode( $match[1], ENT_QUOTES ), true );
	}

	/**
	 * A Modern Fields / ACF Google Map value
	 *
	 * @covers ACFFieldOpenstreetmap\Core\Templates::get_map_html
	 */
	public function test_location_value() {
		$html = acf_osm_get_map( [
			'lat'      => '48.8566',
			'lng'      => '2.3522',
			'zoom'     => '15',
			'address'  => 'Rue <script>alert(1)</script> & Cie, Paris',
			'place_id' => 'abc',
			'city'     => 'Paris',
		] );

		$this->assertStringStartsWith( '<div ', $html );
		$this->assertStringContainsString( 'data-map="leaflet"', $html );
		$this->assertStringContainsString( 'data-map-lat="48.8566"', $html );
		$this->assertStringContainsString( 'data-map-lng="2.3522"', $html );
		$this->assertStringContainsString( 'data-map-zoom="15"', $html );
		$this->assertStringContainsString( 'data-height="400"', $html );
		$this->assertStringNotContainsString( '<script', $html );

		$markers = $this->get_json_attr( $html, 'data-map-markers' );
		$this->assertCount( 1, $markers );
		$this->assertSame( 48.8566, $markers[0]['lat'] );
		$this->assertStringContainsString( 'Paris', $markers[0]['label'] );
		$this->assertStringNotContainsString( '<script', $markers[0]['label'] );

		$this->assertSame( [ 'OpenStreetMap.Mapnik' ], $this->get_json_attr( $html, 'data-map-layers' ) );
	}

	/**
	 * @covers ACFFieldOpenstreetmap\Core\Templates::get_map_html
	 */
	public function test_args() {
		$value = [ 'lat' => 0, 'lng' => 0 ]; // 0 is a coordinate

		$html = acf_osm_get_map( $value );
		$this->assertStringContainsString( 'data-map-zoom="14"', $html );
		// no address: still a marker at the location
		$markers = $this->get_json_attr( $html, 'data-map-markers' );
		$this->assertCount( 1, $markers );
		$this->assertSame( '', $markers[0]['label'] );

		$html = acf_osm_get_map( $value, [ 'marker' => false, 'zoom' => 9, 'height' => '250px', 'layers' => [ 'OpenTopoMap', '' ] ] );
		$this->assertSame( [], $this->get_json_attr( $html, 'data-map-markers' ) );
		$this->assertStringContainsString( 'data-map-zoom="9"', $html );
		$this->assertStringContainsString( 'data-height="250"', $html );
		$this->assertSame( [ 'OpenTopoMap' ], $this->get_json_attr( $html, 'data-map-layers' ) );

		// iframe template
		$html = acf_osm_get_map( $value, [ 'template' => 'osm' ] );
		$this->assertStringStartsWith( '<iframe ', $html );

		// private or unknown templates fall back to leaflet
		foreach ( [ 'admin', '../leaflet', 'nope' ] as $template ) {
			$this->assertStringContainsString( 'data-map="leaflet"', acf_osm_get_map( $value, [ 'template' => $template ] ) );
		}
	}

	/**
	 * The value of an OpenStreetMap field
	 *
	 * @covers ACFFieldOpenstreetmap\Core\Templates::get_map_html
	 */
	public function test_field_value() {
		$value = [
			'lat'     => 53.55,
			'lng'     => 10,
			'zoom'    => 12,
			'layers'  => [ 'OpenStreetMap.DE' ],
			'markers' => [],
		];
		$html = acf_osm_get_map( wp_json_encode( $value ) );
		// no markers on purpose
		$this->assertSame( [], $this->get_json_attr( $html, 'data-map-markers' ) );
		$this->assertSame( [ 'OpenStreetMap.DE' ], $this->get_json_attr( $html, 'data-map-layers' ) );

		// the layers passed win
		$html = acf_osm_get_map( $value, [ 'layers' => [ 'OpenTopoMap' ] ] );
		$this->assertSame( [ 'OpenTopoMap' ], $this->get_json_attr( $html, 'data-map-layers' ) );
	}

	/**
	 * An address written with entities stays text in the popup
	 *
	 * @covers ACFFieldOpenstreetmap\Core\Templates::get_map_html
	 */
	public function test_entities() {
		$html    = acf_osm_get_map( [ 'lat' => 1, 'lng' => 2, 'address' => '&lt;img src=x onerror=alert(1)&gt;' ] );
		$markers = $this->get_json_attr( $html, 'data-map-markers' );
		$this->assertSame( '&lt;img src=x onerror=alert(1)&gt;', $markers[0]['label'] );
	}

	/**
	 * @covers ACFFieldOpenstreetmap\Core\Templates::get_map_html
	 */
	public function test_modern_fields_layer() {
		$value = [ 'lat' => 1, 'lng' => 2 ];

		update_option( 'acf_osm_modern_fields', [ 'layer' => 'OpenTopoMap' ] );
		$this->assertSame( [ 'OpenTopoMap' ], $this->get_json_attr( acf_osm_get_map( $value ), 'data-map-layers' ) );

		// not shown by Modern Fields either
		update_option( 'acf_osm_modern_fields', [ 'layer' => 'OpenSeaMap' ] );
		$this->assertSame( [ 'OpenStreetMap.Mapnik' ], $this->get_json_attr( acf_osm_get_map( $value ), 'data-map-layers' ) );

		delete_option( 'acf_osm_modern_fields' );
	}

	/**
	 * @covers ACFFieldOpenstreetmap\Core\Templates::get_map_html
	 */
	public function test_no_location() {
		foreach ( [ null, '', 'nope', [], [ 'address' => 'Paris' ], [ 'lat' => '', 'lng' => '' ], [ 'lat' => 'a', 'lng' => 1 ], [ 'lat' => '1e999', 'lng' => 1 ], [ 'lat' => 1, 'lng' => '-1e999' ] ] as $value ) {
			$this->assertSame( '', acf_osm_get_map( $value ) );
		}
	}
}
