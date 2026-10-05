<?php

namespace ACFFieldOpenstreetmap\Field;

/**
 *	Pure value-sanitization helpers for the OpenStreetMap field.
 *
 *	Deliberately decoupled from the acf_field runtime (no $this, no ACF state):
 *	everything it needs is passed in, so it can be unit-tested in isolation and
 *	reused by the REST / WPGraphQL formatters.
 */
class MapValue {

	/**
	 *	Normalize a list of layer keys (drop empties/dupes, map legacy aliases).
	 *
	 *	@param mixed $layers
	 *	@return array
	 */
	public static function sanitize_layers( $layers ) {
		$layers = (array) $layers;

		$layers = array_map( function( $layer ) {
			if ( 'OpenStreetMap' === $layer ) {
				$layer = 'OpenStreetMap.Mapnik';
			}
			return $layer;
		}, $layers );
		$layers = array_filter( $layers );
		$layers = array_unique( $layers );
		$layers = array_values( $layers );
		return $layers;
	}

	/**
	 *	Sanitize lat/lng/zoom and convert legacy (<= 1.0.1) center_* properties.
	 *
	 *	@param array $value
	 *	@param array|null $default_latlng
	 *	@return array
	 */
	public static function sanitize_geodata( $value, $default_latlng = null ) {

		// convert settings from <= 1.0.1 > display only?
		if ( isset( $value['center_lat'] ) ) {
			if ( ( ! isset( $value['lat'] ) || empty( $value['lat'] ) ) && ! empty( $value['center_lat'] ) ) {
				$value['lat'] = $value['center_lat'];
			}
			unset( $value['center_lat'] );
		}

		if ( isset( $value['center_lng'] ) ) {
			if ( ( ! isset( $value['lng'] ) || empty( $value['lng'] ) ) && ! empty( $value['center_lng'] ) ) {
				$value['lng'] = $value['center_lng'];
			}
			unset( $value['center_lng'] );
		}

		// apply defaults
		if ( ! is_null( $default_latlng ) ) {
			$value = wp_parse_args( $value, $default_latlng );
		}

		// typecast values
		$value['lat'] = floatval( $value['lat'] );
		$value['lng'] = floatval( $value['lng'] );

		// maybe sanitize zoom
		if ( isset( $value['zoom'] )) {
			// boundaries
			$value['zoom'] = min( 22, max( 0, intval( $value['zoom'] ) ) );
		}

		return $value;
	}

	/**
	 *	Sanitize a field value.
	 *
	 *	@param mixed  $value          raw value (json string, object or array)
	 *	@param array  $field          an already-sanitized field array
	 *	@param array  $default_values the field-type default value template (key whitelist)
	 *	@param string $version        plugin version, stamped onto the value on update
	 *	@param string $context        ''|'display'|'update'
	 *	@return array Sanitized $value
	 */
	public static function sanitize( $value, $field, $default_values, $version, $context = '' ) {

		if ( is_string( $value ) ) {
			// try to json-decode
			$value = json_decode( $value, true );
			if ( is_null( $value ) ) {
				$value = [];
			}
		}

		$value = (array) $value;

		//
		// Markers
		//
		if ( ! isset( $value['markers']) || ! is_array( $value['markers'] ) ) {
			$value['markers'] = [];
		}

		// make sure its an indexed array of arrays (markers may come in as objects, e.g. from an object value)
		$value['markers'] = array_values( array_filter(
			array_map( function( $marker ) {
				return is_object( $marker ) ? (array) $marker : $marker;
			}, $value['markers'] ),
			'is_array'
		) );

		// Maybe get marker from ACF GoogleMaps data
		if ( 'display' === $context ) { // display + edit

			$value = self::sanitize_geodata( $value, [
				'lat'	=> $field['center_lat'],
				'lng'	=> $field['center_lng'],
				'zoom'	=> $field['zoom'],
			] );

			if ( ! empty( $value[ 'address' ] ) ) {

				// create marker from GM field address
				if ( $field['max_markers'] !== 0 && ! count( $value[ 'markers' ] ) ) {

					$value['markers'][] = [
						'label'	=> wp_kses_post( $value['address'] ),
						'default_label'	=> '',
						'lat'	=> $value['lat'],
						'lng'	=> $value['lng'],
					];
				}
			} else  {
				if ( count( $value['markers'] ) ) {
					// update address from first marker
					$value['address'] = $value['markers'][0]['label'] ?? '';
				} else {
					$value['address'] = '';
				}
			}
		}

		// typecast
		foreach ( $value['markers'] as &$marker ) {

			// typecast values
			$marker['lat'] = floatval( $marker['lat'] ?? 0 );
			$marker['lng'] = floatval( $marker['lng'] ?? 0 );

			$marker['label'] = wp_kses_post( $marker['label'] ?? '' );
			$marker['default_label'] = wp_kses_post( $marker['default_label'] ?? '' );
		}
		unset( $marker );

		// store data to be used by ACF GM Field
		if ( 'update' === $context ) {
			$value[ 'version' ]	= $version;

			if ( count( $value['markers'] ) ) {
				// update address from first marker
				$value['address'] = $value['markers'][0]['label'] ?? '';
			} else {
				$value['address'] = '';
			}
		}

		// Sanitize HTML from address
		$value[ 'address' ] = wp_kses_post( $value[ 'address' ] ?? '' );

		//
		// Layers
		//
		if ( ! isset( $value['layers'] ) || ! is_array( $value['layers'] ) || ! count( $value['layers'] ) || ! $field['allow_map_layers'] ) {
			$value['layers'] = $field['layers'];
		} else {
			// normalize layers
			$value['layers'] = self::sanitize_layers( $value['layers'] );
		}

		return array_intersect_key( $value, $default_values );
	}
}
