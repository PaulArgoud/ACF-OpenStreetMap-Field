<?php

namespace ACFFieldOpenstreetmap\Core;

use ACFFieldOpenstreetmap\Compat;
use ACFFieldOpenstreetmap\Field\MapValue;

class Templates extends Singleton {

	private $templates = null;

	private $template_dirname = 'osm-maps';

	/**
	 *	@inheritdoc
	 */
	protected function __construct() {
		add_action( 'get_template_part', [ $this, 'get_template_part' ], 10, 4 );
	}

	/**
	 *	@action get_template_part
	 */
	public function get_template_part( $slug, $name = null, $templates = [], $args = [] ) {

		if ( ! str_contains( $slug, $this->template_dirname ) ) {
			return;
		}

		$template = str_replace( $this->template_dirname . '/', '', $slug );
		$name = (string) $name;

		$locate = [ "{$slug}.php" ];
		if ( '' !== $name ) {
			$locate[] = "{$slug}-{$name}.php";
		}

		// the theme can handle it!
		if ( locate_template( $locate, false ) ) {
			return;
		}

		// we'll have to handle it
		$core = Core::instance();
		$file = $core->get_plugin_dir() . 'templates/' . str_replace( $this->template_dirname . '/','',$slug) . '.php';

		if ( file_exists( $file ) ) {
			load_template( $file, false, $args );
		}
	}

	/**
	 *	Map of a location, with a map template.
	 *
	 *	@param mixed $value Value of a Modern Fields Map field or an ACF Google Map field (lat, lng, zoom,
	 *	                    address), or of an OpenStreetMap field (array or JSON)
	 *	@param array $args See acf_osm_get_map()
	 *	@return string HTML, empty if the value has no coordinates
	 */
	public function get_map_html( $value, $args = [] ) {

		if ( is_string( $value ) ) {
			$value = json_decode( $value, true );
		}
		$value = is_object( $value ) ? get_object_vars( $value ) : $value;

		// 0 is a valid coordinate, so is_numeric() and not empty(). Not '1e999' either: an infinite coordinate breaks the maps.
		if ( ! is_array( $value )
			|| ! is_numeric( $value['lat'] ?? null ) || ! is_finite( (float) $value['lat'] )
			|| ! is_numeric( $value['lng'] ?? null ) || ! is_finite( (float) $value['lng'] )
		) {
			return '';
		}

		$args = wp_parse_args( $args, [
			'template'         => 'leaflet',
			'height'           => 400,
			'zoom'             => null,
			'layers'           => null,
			'marker'           => true,
			'fit_bounds'       => false,
			'gesture_handling' => false,
			'marker_icon_url'  => '',
		] );

		if ( ! is_string( $args['template'] ) || ! isset( $this->get_templates()[ $args['template'] ] ) ) {
			$args['template'] = 'leaflet';
		}

		if ( is_numeric( $args['zoom'] ) ) {
			$value['zoom'] = $args['zoom'];
		} elseif ( ! is_numeric( $value['zoom'] ?? null ) ) {
			unset( $value['zoom'] ); // field default below
		}

		// the layers passed, else those of the value, else the Modern Fields layer (as long as Modern Fields shows it)
		$default_layers = Compat\ModernFields::instance()->get_tile_layer()
			? [ Compat\ModernFields::instance()->get_layer_key() ]
			: MapValue::DEFAULT_VALUES['layers'];

		// what the templates and MapValue::sanitize() read from an OpenStreetMap field
		$field = [
			'center_lat'       => $value['lat'],
			'center_lng'       => $value['lng'],
			'zoom'             => 14,
			'height'           => absint( $args['height'] ),
			'max_markers'      => $args['marker'] ? '' : 0,
			'allow_map_layers' => null === $args['layers'],
			'layers'           => MapValue::sanitize_layers( $args['layers'] ?? $default_layers ),
			'fit_bounds'       => (bool) $args['fit_bounds'],
			'gesture_handling' => (bool) $args['gesture_handling'],
			'marker_icon_url'  => is_string( $args['marker_icon_url'] ) ? esc_url_raw( $args['marker_icon_url'] ) : '',
			'return_format'    => $args['template'],
		];

		$map = MapValue::sanitize( $value, $field, MapValue::DEFAULT_VALUES, '', 'display' );

		if ( ! $args['marker'] ) {
			$map['markers'] = [];
		} elseif ( ! isset( $value['markers'] ) && ! $map['markers'] ) {
			// a location without address: MapValue::sanitize() only makes a marker of an address
			$map['markers'][] = [
				'label'         => '',
				'default_label' => '',
				'lat'           => $map['lat'],
				'lng'           => $map['lng'],
			];
		}

		ob_start();

		get_template_part( $this->template_dirname . '/' . $args['template'], null, [
			'field' => $field,
			'map'   => $map,
		] );

		return ob_get_clean();
	}

	/**
	 *	@return Array template slug
	 */
	public function get_templates() {
		if ( is_null( $this->templates ) ) {
			$this->templates = [];
			// scan ./templates/*.php
			// scan <theme>/osm-maps/*.php
			// return [ 'osm-maps/template-name' => 'Template Name',  ] // or Header Map Template Name: ...
			$core = Core::instance();
			$paths = [
				$core->get_plugin_dir() . '/templates/',
				get_template_directory() . '/osm-maps/',
				get_stylesheet_directory() . '/osm-maps/',
			];
			foreach ( array_unique( $paths ) as $path ) {
				$len = strlen( $path );
				foreach( glob( $path . '*.php' ) as $file ) {
					$template = substr( $file, $len, -4 );
					$file_data = get_file_data( $file, [ 'name' => 'Map Template Name', 'private' => 'Private' ] );
					$name = empty( $file_data['name'] ) ? ucwords($template) : $file_data['name'];
					$this->templates[$template] = [
						'file' => $file,
						'name' => $file_data['name'],
						'private' => boolval( $file_data['private'] ),
					];
				}
			}
		}
		return array_filter( $this->templates, function( $template ) {
			return ! $template['private'];
		});
	}
}
