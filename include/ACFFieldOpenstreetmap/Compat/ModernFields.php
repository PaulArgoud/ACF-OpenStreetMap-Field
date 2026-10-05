<?php

namespace ACFFieldOpenstreetmap\Compat;

use ACFFieldOpenstreetmap\Core;

/**
 *	Compatibility with Modern Fields (1.5+): the OpenStreetMap map of its Map field shows a layer of
 *	the tile catalogue, chosen in the settings, through the map proxy when it is enabled for the provider.
 *
 *	Modern Fields creates the tile layer from a URL template and an attribution read from filters,
 *	the only part of its map open to other plugins.
 */
class ModernFields extends Core\Singleton {

	/** @var string Option name, [ 'layer' => 'Provider.variant' ] */
	const OPTION = 'acf_osm_modern_fields';

	/** @var int Max zoom of the Modern Fields map */
	const MAX_ZOOM = 19;

	/**
	 *	@inheritdoc
	 */
	protected function __construct() {
		// early: a site can still set another layer at the default priority
		add_filter( 'modern-fields/maps/osm/tile_url', [ $this, 'tile_url' ], 5 );
		add_filter( 'modern-fields/maps/osm/attribution', [ $this, 'attribution' ], 5 );
	}

	/**
	 *	Whether a Modern Fields version with filterable OpenStreetMap tiles is active
	 *
	 *	@return boolean
	 */
	public static function is_active() {
		return defined( 'MODERN_FIELDS_VERSION' ) && version_compare( MODERN_FIELDS_VERSION, '1.5.0', '>=' );
	}

	/**
	 *	Whether the site set the Modern Fields tile URL in wp-config.php, which has the last word
	 *
	 *	@return boolean
	 */
	public static function is_tile_url_constant_defined() {
		return defined( 'MODERN_FIELDS_OSM_TILE_URL' );
	}

	/**
	 *	@return string Key of the selected layer, '' for the default of Modern Fields
	 */
	public function get_layer_key() {
		$option = get_option( self::OPTION, [] );
		$layer  = is_array( $option ) ? ( $option['layer'] ?? '' ) : '';
		return is_string( $layer ) ? $layer : '';
	}

	/**
	 *	The selected layer, if it is still available
	 *
	 *	@return array|null See LeafletProviders::get_tile_layer()
	 */
	public function get_tile_layer() {
		// before wp_loaded the map proxy may not have rewritten the provider URLs yet, with their access tokens
		if ( ! did_action( 'wp_loaded' ) || self::is_tile_url_constant_defined() || '' === $this->get_layer_key() ) {
			return null;
		}
		$layer = Core\LeafletProviders::instance()->get_tile_layer( $this->get_layer_key() );
		return self::is_usable( $layer ) ? $layer : null;
	}

	/**
	 *	Whether Modern Fields can show a layer: a base layer on Leaflet's default tile grid,
	 *	it only passes the URL and the attribution to L.tileLayer()
	 *
	 *	@param array|null $layer See LeafletProviders::get_tile_layer()
	 *	@return boolean
	 */
	private static function is_usable( $layer ) {
		return $layer
			&& ! $layer['isOverlay']
			&& 256 === (int) ( $layer['options']['tileSize'] ?? 256 )
			&& 0 === (int) ( $layer['options']['zoomOffset'] ?? 0 );
	}

	/**
	 *	@filter modern-fields/maps/osm/tile_url
	 */
	public function tile_url( $url ) {
		$layer = $this->get_tile_layer();
		return $layer ? $layer['url'] : $url;
	}

	/**
	 *	@filter modern-fields/maps/osm/attribution
	 */
	public function attribution( $attribution ) {
		$layer = $this->get_tile_layer();
		return $layer ? $layer['options']['attribution'] : $attribution;
	}

	/**
	 *	Layers Modern Fields can show, among the enabled ones with their access token
	 *
	 *	@return array [ 'Provider.variant' => label ]
	 */
	public function get_layer_choices() {
		$providers = Core\LeafletProviders::instance();
		$choices   = [];

		foreach ( $providers->get_layers() as $layer_key ) {
			$layer = $providers->get_tile_layer( $layer_key );
			if ( ! self::is_usable( $layer ) ) {
				continue;
			}
			// past its zoom range the Modern Fields map stays empty
			$min_zoom = (int) ( $layer['options']['minZoom'] ?? 0 );
			$max_zoom = (int) ( $layer['options']['maxNativeZoom'] ?? $layer['options']['maxZoom'] ?? 18 ); // Leaflet default
			$choices[ $layer_key ] = $min_zoom > 0 || $max_zoom < self::MAX_ZOOM
				/* translators: 1: map layer, 2: min zoom, 3: max zoom */
				? sprintf( __( '%1$s (zoom %2$d–%3$d)', 'acf-openstreetmap-field' ), $layer_key, $min_zoom, min( $max_zoom, self::MAX_ZOOM ) )
				: $layer_key;
		}

		return $choices;
	}
}
