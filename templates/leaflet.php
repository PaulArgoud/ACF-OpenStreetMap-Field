<?php
/**
 *	Map Template Name: Leaflet JS
 */

// need $field & $value
$osm_map = $args['map'];
$field = $args['field'];
// features: multiple markers. lots of maps to choose from
$attr = [
	'class'				=> 'leaflet-map',
	'data-height'		=> $field['height'],
	'data-map'			=> 'leaflet',
	'data-map-lng'		=> $osm_map['lng'],
	'data-map-lat'		=> $osm_map['lat'],
	'data-map-zoom'		=> $osm_map['zoom'],
	'data-map-layers'	=> $osm_map['layers'],
	'data-map-markers'	=> $osm_map['markers'],
	'data-fit-bounds'	=> ! empty( $field['fit_bounds'] ) ? 1 : 0,
	'data-gesture-handling'	=> ! empty( $field['gesture_handling'] ) ? 1 : 0,
	'data-marker-icon'	=> $field['marker_icon_url'] ?? '',
];


?>
<div <?php echo acf_osm_esc_attrs( $attr ); ?>></div>
<?php

wp_enqueue_script( 'acf-osm-frontend' );
wp_enqueue_style( 'leaflet' );
