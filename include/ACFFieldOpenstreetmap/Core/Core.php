<?php

namespace ACFFieldOpenstreetmap\Core;

if ( ! defined('ABSPATH') ) {
	die('FU!');
}
use ACFFieldOpenstreetmap\Compat;
use WP_Error;

class Core extends Plugin {

	private $leaflet_providers = null;

	/**
	 *	@inheritdoc
	 */
	protected function __construct( $file ) {

		add_action( 'acf/include_field_types', [ '\ACFFieldOpenstreetmap\Compat\ACF', 'instance'], 0 );

		add_action( 'init', [ '\ACFFieldOpenstreetmap\Compat\WPGraphQL', 'instance'] );

		add_action( 'init', [ '\ACFFieldOpenstreetmap\Core\Templates', 'instance'] );
		add_action( 'init', [ '\ACFFieldOpenstreetmap\Core\MapProxy', 'instance'] );

		add_action( 'wp_enqueue_scripts', [ $this, 'register_assets' ] );

		add_action( 'login_enqueue_scripts', [ $this, 'register_assets' ] );

		if ( is_admin() ) {
			add_action( 'admin_enqueue_scripts', [ $this, 'register_assets' ] );
		}

		add_action( 'enqueue_block_assets', [ $this, 'enqueue_editor_canvas_assets' ] );

		$args = func_get_args();
		parent::__construct( ...$args );
	}

	/**
	 *	@action wp_enqueue_scripts
	 */
	public function register_assets() {

		$leaflet_providers = LeafletProviders::instance();
		$osm_providers = OSMProviders::instance();
		/** @var LeafletGeocoders $leaflet_geocoders */
		$leaflet_geocoders = LeafletGeocoders::instance();

		$language = substr( get_locale(), 0, 2 );

		/* frontend */
		/**
		 *	Marker Icon HTML. Return false to use image icon (either leaflet default or return value of filter `acf_osm_marker_icon`)
		 *
		 *	@param $marker_icon_html string Additional Icon HTML.
		 */
		$marker_html = apply_filters( 'acf_osm_marker_html', false );

		if ( $marker_html !== false ) {
			$marker_html = wp_kses_post( $marker_html );
		}

		$osm_l10n = [
			'options'	=> [
				'marker'		=> [

					'html'		=> $marker_html,

					/**
					 *	HTML Marker Icon css class
					 *
					 *	@param $classname string Class name for HTML icon. Default 'acf-osm-marker-icon'
					 */
					'className'	=> sanitize_html_class( apply_filters('acf_osm_marker_classname', 'acf-osm-marker-icon' ) ),

					/**
					 *	Return leaflet icon options.
					 *
					 *	@see https://leafletjs.com/reference-1.3.2.html#icon
					 *
					 *	@param $icon_options false (leaflet default icon or HTML icon) or array(
					 *		'iconUrl'			=> image URL
					 *		'iconRetinaUrl'		=> image URL
					 *		'iconSize'			=> array( int, int )
					 *		'iconAnchor'		=> array( int, int )
					 *		'popupAnchor'		=> array( int, int )
					 *		'tooltipAnchor'		=> array( int, int )
					 *		'shadowUrl'			=> image URL
					 *		'shadowRetinaUrl'	=> image URL
					 *		'shadowSize'		=> array( int, int )
					 *		'shadowAnchor'		=> array( int, int )
					 *		'className'			=> string
				 	 *	)
					 */
					'icon'		=> apply_filters('acf_osm_marker_icon', false ),
				],

			],
			// Full credentialed catalogue – NOT filtered by the global enable/disable
			// settings – so a layer a field already uses keeps rendering even after its
			// provider was disabled globally. The enable/disable settings only govern
			// the layer picker (see LeafletProviders::get_layers()). See #109.
			'providers' => $leaflet_providers->get_providers( [ 'credentials' ] ),
		];
		$geocoders = $leaflet_geocoders->get_geocoders();
		$osm_settings = [
			'options' => [
				'layer_config'	=> $leaflet_providers->get_layer_config(), // settings only
			],
			'geocoders' => array_combine(
				array_keys( $geocoders ),
				array_map(
					function( $geocoder ) use ( $leaflet_geocoders ) {
						return $leaflet_geocoders->get_options( $geocoder );
					},
					array_keys( $geocoders )
				),
			),
		];

		/**
		 * Get Geocoder options from the Admin UI.
		 */
		$geocoder_settings = (array) get_option('acf_osm_geocoder');

		$geocoder_name = $geocoder_settings['engine'] ?? LeafletGeocoders::GEOCODER_DEFAULT;

		$osm_admin = [
			'options'	=> [
				'osm_layers'		=> $osm_providers->get_layers(), // flat list
				'leaflet_layers'	=> $leaflet_providers->get_layers(),  // flat list
				'accuracy'			=> 7, // used to round lat and lng float number @see models/gs-model/fixedFloatSetter()
				/**
				 *	Filter Leaflet control geocoder options.
				 *
				 *	@see https://www.liedman.net/leaflet-control-geocoder/docs/interfaces/geocodercontroloptions.html
				 *
				 *	@param $geocoder_options array(
				 *		'collapsed'				=> boolean
				 *		'defaultMarkGeocode'	=> boolean
				 *		'errorMessage'			=> string
				 *		'position'				=> string
				 *		'expand'				=> string ("touch" | "click" | "hover")
				 *		'iconLabel'				=> string
				 *		'placeholder'			=> string
				 *		'queryMinLength'		=> number
				 *		'showResultIcons'		=> boolean
				 *		'suggestMinLength'		=> number
				 *		'suggestTimeout'		=> number
				 *	)
				 */
				'geocoder_name' => $geocoder_name,
				'geocoder_options' => apply_filters( 'acf_osm_geocoder_options',  $leaflet_geocoders->get_options($geocoder_name) ),
			],
			'i18n'	=> [
				'search'		=> __( 'Search...', 'acf-openstreetmap-field' ),
				'nothing_found'	=> __( 'Nothing found...', 'acf-openstreetmap-field' ),
				'my_location'	=> __( 'My location', 'acf-openstreetmap-field' ),
				'add_marker_at_location'
					=> __( 'Add Marker at location', 'acf-openstreetmap-field' ),
				'fit_markers_in_view'
				 				=> __( 'Fit markers into view', 'acf-openstreetmap-field' ),
				/* translators: short label for the latitude coordinate input */
				'lat'			=> __( 'lat', 'acf-openstreetmap-field' ),
				/* translators: short label for the longitude coordinate input */
				'lng'			=> __( 'lng', 'acf-openstreetmap-field' ),
				/* translators: short label for the zoom level input */
				'zoom'			=> __( 'zoom', 'acf-openstreetmap-field' ),
				/* translators: country name in marker labels from the French Géoplateforme geocoder */
				'country_france'	=> __( 'France', 'acf-openstreetmap-field' ),
				/**
				 *	Filter the address formats used to build marker labels from
				 *	geocoding results. Useful to reorder or localise the parts.
				 *
				 *	Available placeholders: {building} {road} {house_number}
				 *	{postcode} {city} {town} {village} {hamlet} {state} {country}
				 *
				 *	@param array $address_format [
				 *		'street'  => (string) street level format,
				 *		'city'    => (string) city level format,
				 *		'country' => (string) country level format,
				 *	]
				 */
				'address_format'	=> apply_filters( 'acf_osm_address_format', [
					/* translators: address format for marker labels (street level). Available placeholders {building} {road} {house_number} {postcode} {city} {town} {village} {hamlet} {state} {country} */
					'street'	=> __( '{building} {road} {house_number}', 'acf-openstreetmap-field' ),
					/* translators: address format for marker labels (city level). Available placeholders {building} {road} {house_number} {postcode} {city} {town} {village} {hamlet} {state} {country} */
					'city'		=> __( '{postcode} {city} {town} {village} {hamlet}', 'acf-openstreetmap-field' ),
					/* translators: address format for marker labels (country level). Available placeholders {building} {road} {house_number} {postcode} {city} {town} {village} {hamlet} {state} {country} */
					'country'	=> __( '{state} {country}', 'acf-openstreetmap-field' ),
				] )
			],
		];

		// Shared Leaflet core chunk (see webpack splitChunks). Registered as a
		// dependency of every entry below so WordPress prints it first.
		wp_register_script( 'acf-osm-leaflet', $this->get_asset_url( 'assets/js/acf-osm-leaflet.js' ), [], $this->get_version(), [
			'in_footer' => true,
		] );

		/* frontend */
		wp_register_script( 'acf-osm-frontend', $this->get_asset_url( 'assets/js/acf-osm-frontend.js' ), [ 'acf-osm-leaflet' ], $this->get_version(), [
			'strategy'  => 'defer',
			'in_footer' => true,
		] );
		wp_localize_script( 'acf-osm-frontend', 'acf_osm', $osm_l10n );

		wp_register_style( 'leaflet', $this->get_asset_url( 'assets/css/acf-osm-leaflet.css' ), [], $this->get_version() );

		// block editor canvas
		wp_register_style( 'acf-osm-editor-canvas', $this->get_asset_url( 'assets/css/acf-osm-editor-canvas.css' ), [], $this->get_version() );

		/* backend */

		// field js
		wp_register_script( 'acf-input-osm', $this->get_asset_url('assets/js/acf-input-osm.js'), [ 'acf-osm-leaflet', 'acf-input', 'wp-backbone' ], $this->get_version(), [
			// 'strategy'  => 'defer',
			'in_footer' => true,
		] );
		wp_localize_script( 'acf-input-osm', 'acf_osm', $osm_l10n );
		wp_localize_script( 'acf-input-osm', 'acf_osm_admin', $osm_admin );
		// field css
		// with the canvas styles for block editors that are not iframed (WP < 7.1, ACF v2 blocks). In an iframed canvas WP
		// doesn't copy them over again, the canvas already has a stylesheet with that id (enqueue_editor_canvas_assets())
		wp_register_style( 'acf-input-osm', $this->get_asset_url( 'assets/css/acf-input-osm.css' ), [ 'leaflet', 'acf-input', 'dashicons', 'acf-osm-editor-canvas' ], $this->get_version() );


		// field group admin js
		wp_register_script( 'acf-field-group-osm', $this->get_asset_url('assets/js/acf-field-group-osm.js'), [ 'acf-osm-leaflet', 'acf-field-group', 'wp-backbone' ], $this->get_version(), [
			// 'strategy'  => 'defer',
			'in_footer' => true,
		] );

		// field group admin css
		wp_register_style( 'acf-field-group-osm', $this->get_asset_url( 'assets/css/acf-field-group-osm.css' ), [], $this->get_version() );
		wp_localize_script( 'acf-field-group-osm', 'acf_osm', $osm_l10n );
		wp_localize_script( 'acf-field-group-osm', 'acf_osm_admin', $osm_admin );


		// settings js
		wp_register_script( 'acf-osm-settings', $this->get_asset_url( 'assets/js/acf-osm-settings.js' ), [ 'acf-osm-leaflet', 'wp-backbone' ], $this->get_version() );
		wp_localize_script( 'acf-osm-settings', 'acf_osm', $osm_l10n );
		wp_localize_script( 'acf-osm-settings', 'acf_osm_settings', $osm_settings );
		wp_localize_script( 'acf-osm-settings', 'acf_osm_admin', $osm_admin );

		// settings css
		wp_register_style( 'acf-osm-settings', $this->get_asset_url( 'assets/css/acf-osm-settings.css' ), ['leaflet', 'acf-input-osm'], $this->get_version() );
	}

	/**
	 *	Load the frontend map assets into the iframed block editor canvas (always iframed since WP 7.1),
	 *	so maps in ACF block previews render.
	 *
	 *	@action enqueue_block_assets
	 */
	public function enqueue_editor_canvas_assets() {
		// Only while WP collects the canvas iframe assets: _wp_get_iframed_editor_assets() filters
		// should_load_block_editor_scripts_and_styles to false. Skips the frontend and the parent admin page.
		if ( ! is_admin()
			|| ! function_exists( 'wp_should_load_block_editor_scripts_and_styles' ) // WP < 5.6, no iframed canvas
			|| wp_should_load_block_editor_scripts_and_styles()
		) {
			return;
		}
		// the canvas assets are collected before admin_enqueue_scripts
		if ( ! wp_script_is( 'acf-osm-frontend', 'registered' ) ) {
			$this->register_assets();
		}
		// Always: the field styles in the parent page depend on it, and WP copies a parent stylesheet with
		// .wp-block rules into the canvas (with a warning) unless the canvas already has one with that id.
		wp_enqueue_style( 'acf-osm-editor-canvas' );

		// only ACF blocks can render a map in the canvas
		if ( ! function_exists( 'acf_get_block_types' ) || ! acf_get_block_types() ) {
			return;
		}
		wp_enqueue_script( 'acf-osm-frontend' );
		wp_enqueue_style( 'leaflet' );
	}

	/**
	 *	Get asset url for this plugin
	 *
	 *	@param	string	$asset	URL part relative to plugin class
	 *	@return wp_enqueue_editor
	 */
	public function get_asset_url( $asset ) {
		return plugins_url( $asset, $this->get_plugin_file() );
	}
}
