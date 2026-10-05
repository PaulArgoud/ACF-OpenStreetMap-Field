<?php

/**
 *	Get iFrame src from openstreetmap.org
 *
 *	@param Array $map Map Data from ACF field
 *	@return String
 */
 function acf_osm_get_iframe_url( $map ) {
 	return \ACFFieldOpenstreetmap\Core\OSMProviders::instance()->get_iframe_url( $map );
 }

/**
 *	Get link to openstreetmap.org
 *
 *	@param Array $map Map Data from ACF field
 *	@return String
 */
function acf_osm_get_link_url( $map ) {
	return \ACFFieldOpenstreetmap\Core\OSMProviders::instance()->get_link_url( $map );
}

/**
 *	HTML attributes, escaped, like acf_esc_attrs() but without ACF. Arrays and objects are JSON encoded.
 *
 *	@param Array $attrs
 *	@return String
 */
function acf_osm_esc_attrs( $attrs ) {
	return \ACFFieldOpenstreetmap\Helper\HtmlHelper::esc_attrs( $attrs );
}

/**
 *	Map of a location, e.g. a Modern Fields Map field or an ACF Google Map field. Needs no ACF.
 *
 *	@param mixed $value [ 'lat' => …, 'lng' => …, 'zoom' => …, 'address' => … ], or the raw value of an OpenStreetMap
 *	                    field (return format 'raw', or get_field( $name, $post_id, false ))
 *	@param Array $args [
 *		'template'         => (string) Map template: 'leaflet' (default), 'osm' or one of the theme (osm-maps/*.php)
 *		'height'           => (int) Pixels, default 400
 *		'zoom'             => (int) Default: the zoom of the value, else 14
 *		'layers'           => (array) Layer keys, e.g. [ 'OpenStreetMap.Mapnik' ]. Default: the layers of the value,
 *		                      else the layer chosen for Modern Fields in the settings, else OpenStreetMap
 *		'marker'           => (bool) Show the location, labelled with its address. Default true
 *		'fit_bounds'       => (bool) Default false
 *		'gesture_handling' => (bool) Default false
 *		'marker_icon_url'  => (string) Default ''
 *	]
 *	@return String HTML, empty if the value has no coordinates
 */
function acf_osm_get_map( $value, $args = [] ) {
	return \ACFFieldOpenstreetmap\Core\Templates::instance()->get_map_html( $value, $args );
}

/**
 *	Print the map of a location
 *
 *	@see acf_osm_get_map()
 */
function acf_osm_the_map( $value, $args = [] ) {
	echo acf_osm_get_map( $value, $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by the template
}
