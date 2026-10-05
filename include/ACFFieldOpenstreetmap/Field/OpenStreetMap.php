<?php

namespace ACFFieldOpenstreetmap\Field;

use ACFFieldOpenstreetmap\Core;
use ACFFieldOpenstreetmap\Helper;

class OpenStreetMap extends \acf_field {

	use Traits\FieldSettings;

	/** @var array Default field value (declared to avoid PHP 8.2 dynamic property deprecation) */
	public $default_values = [];

	/**
	 *  __construct
	 *
	 *  This function will setup the field type data
	 *
	 *  @type	function
	 *  @date	5/03/2014
	 *  @since	5.0.0
	 *
	 *  @param	n/a
	 *  @return	n/a
	 */
	function initialize() {

		/*
		 *  name (string) Single word, no spaces. Underscores allowed
		 */
		$this->name = 'open_street_map';
		/*
		 *  label (string) Multiple words, can include spaces, visible when selecting a field type
		 */
		$this->label = __("OpenStreetMap",'acf-openstreetmap-field');


		$this->show_in_rest = true;

		// format_value() output comes from templates that escape it themselves (esc_url, acf_esc_attr, …).
		// Without this, ACF 6.2.5+ runs the_field() output through wp_kses and strips the iFrame format's <iframe>.
		if ( property_exists( $this, 'supports' ) ) {
			$this->supports = array_merge( (array) $this->supports, [ 'escaping_html' => true ] );
		}

		/*
		 *  category (string) basic | content | choice | relational | advanced | layout | CUSTOM GROUP NAME
		 *  ACF 6.1 replaced the 'jquery' category by 'advanced'
		 */
		$this->category = version_compare( acf_get_setting( 'version' ), '6.1', '>=' ) ? 'advanced' : 'jquery';

		$this->default_values = [
			// hamburg
			'lat'		=> 53.55064,
			'lng'		=> 10.00065,
			'zoom'		=> 12,
			'layers'	=> [ 'OpenStreetMap.Mapnik' ],
			'markers'	=> [],
			// gm compatibility
			'address'	=> '',
			'version'	=> '',
		];
		/*
		 *  defaults (array) Array of default settings which are merged into the field object. These are used later in settings
		 */
		$this->defaults = [
			'center_lat'		=> $this->default_values['lat'],
			'center_lng'		=> $this->default_values['lng'],
			'zoom'				=> $this->default_values['zoom'],

			'height'			=> 400,
			'return_format'		=> 'leaflet',
			'allow_map_layers'	=> 1,
			'fit_bounds'		=> 0,
			'gesture_handling'	=> 0,
			'max_markers'		=> '',
			'markers_search_only'	=> 0,
			'marker_icon_url'	=> '',
			'layers'			=> $this->default_values['layers'],
		];

		/*
		 *  l10n (array) Array of strings that are used in JavaScript. This allows JS strings to be translated in PHP and loaded via:
		 *  var message = acf._e('FIELD_NAME', 'error');
		 */
		$this->l10n = [];

		add_action( 'print_media_templates', [ $this, 'print_media_templates' ] );
	}


	/*
	 *  render_field()
	 *
	 *  Create the HTML interface for your field
	 *
	 *  @param	$field (array) the $field being rendered
	 *
	 *  @type	action
	 *  @since	3.6
	 *  @date	23/01/13
	 *
	 *  @param	$field (array) the $field being edited
	 *  @return	n/a
	 */
	function render_field( $field ) {

		$core = Core\Core::instance();

		if ( is_null( $field['value'] ) ) {
			$field['value'] = $this->sanitize_value( [], $field, 'display' );
		}

		// json_encoded value. No literal < > & so ACF's wp_kses_post_deep() on $_POST for users
		// without unfiltered_html can't mangle the JSON (see MapInput.updateValue())
		acf_hidden_input([
			'id'		=> $field['id'],
			'name'		=> $field['name'],
			'value'		=> json_encode( $field['value'], JSON_HEX_TAG | JSON_HEX_AMP ),
			'class'		=> 'osm-json',
		]);

		$restrict_providers = isset($field['return_format']) && $field['return_format'] === 'osm'
			? array_values( Core\OSMProviders::instance()->get_layers() )
			: false;

		$max_markers = $field['max_markers'] === '' ? false : intval( $field['max_markers'] );

		if ( 'osm' === $field['return_format'] ) {
			if ( $max_markers === false ) { // no restriction > max one marker
				$max_markers = 1;
			}
			// only one marker max
			$max_markers = min( $max_markers, 1 );
		}
		$map_args = [
			'field' => $field + [
				'attr'	=> [
					'data-editor-config'	=> [
						'allow_providers'		=> $field['allow_map_layers'],
						'restrict_providers'	=> $restrict_providers,
						'max_markers'			=> $max_markers,
						'markers_search_only'	=> ! empty( $field['markers_search_only'] ),
						'numeric_position'		=> true, // value editor only (#29)
						'name_prefix'			=> $field['name'],
					],
				],
			],
			'map' => $field['value'],
		];
		get_template_part( 'osm-maps/admin', null, $map_args );


		if ( $max_markers !== 0 ) {
			?>
				<div class="markers-instruction">
					<p class="description">
						<span class="add-marker-instructions marker-on-dblclick can-add-marker">
							<?php esc_html_e('Double click to add Marker.', 'acf-openstreetmap-field' ); ?>
						</span>
						<span class="add-marker-instructions marker-on-taphold can-add-marker">
							<?php esc_html_e('Tap and hold to add Marker.', 'acf-openstreetmap-field' ); ?>
						</span>
						<?php if ( empty( $field['markers_search_only'] ) ) : ?>
						<span class="has-markers">
							<?php esc_html_e('Drag Marker to move.', 'acf-openstreetmap-field' ); ?>
						</span>
						<?php endif; ?>
					</p>
				</div>
			<?php

		}
		?>
		<div class="osm-markers">
		</div>
		<?php
	}

	/*
	 *  input_admin_enqueue_scripts()
	 *
	 *  This action is called in the admin_enqueue_scripts action on the edit screen where your field is created.
	 *  Use this action to add CSS + JavaScript to assist your render_field() action.
	 *
	 *  @type	action (admin_enqueue_scripts)
	 *  @since	3.6
	 *  @date	23/01/13
	 *
	 *  @param	n/a
	 *  @return	n/a
	 */
	function input_admin_enqueue_scripts() {

		wp_enqueue_media();

		wp_enqueue_script('acf-input-osm');

		// wp_enqueue_script('acf-osm-frontend');

		wp_enqueue_style('acf-input-osm');

		// wp_enqueue_style('leaflet');

		add_action( 'wp_footer', [ $this, 'maybe_print_media_templates' ], 11 );
	}

	/*
	 *  field_group_admin_enqueue_scripts()
	 *
	 *  This action is called in the admin_enqueue_scripts action on the edit screen where your field is edited.
	 *  Use this action to add CSS + JavaScript to assist your render_field_options() action.
	 *
	 *  @type	action (admin_enqueue_scripts)
	 *  @since	3.6
	 *  @date	23/01/13
	 *
	 *  @param	n/a
	 *  @return	n/a
	 */
	function field_group_admin_enqueue_scripts() {

		wp_enqueue_media();

		wp_dequeue_script('acf-input-osm');

		wp_enqueue_script('acf-field-group-osm');

		// wp_enqueue_script('acf-osm-frontend');

		wp_enqueue_style('acf-input-osm');

		wp_enqueue_style('acf-field-group-osm');

		wp_enqueue_style('leaflet');
	}

	/*
	 *  load_value()
	 *
	 *  This filter is applied to the $value after it is loaded from the db
	 *
	 *  @type	filter
	 *  @since	3.6
	 *  @date	23/01/13
	 *
	 *  @param	$value (mixed) the value found in the database
	 *  @param	$post_id (mixed) the $post_id from which the value was loaded
	 *  @param	$field (array) the field array holding all the field options
	 *  @return	$value
	 */
	function load_value( $value, $post_id, $field ) {

		// prepare data for display
		$value = $this->sanitize_value( $value, $field, 'display' );

		return $value;
	}

	/**
	 *	Sanitize a field value. Thin wrapper delegating to the decoupled
	 *	{@see MapValue} layer; the field is sanitized first so the value layer
	 *	stays free of ACF field-config concerns.
	 *
	 *	@param mixed  $value
	 *	@param array  $field
	 *	@param string $context edit|display|update
	 *	@return array Sanitized $value
	 */
	private function sanitize_value( $value, $field, $context = '' ) {

		return MapValue::sanitize(
			$value,
			$this->sanitize_field( $field ),
			$this->default_values,
			Core\Core::instance()->get_version(),
			$context
		);
	}

	/*
	 *  update_value()
	 *
	 *  This filter is applied to the $value before it is saved in the db
	 *
	 *  @type	filter
	 *  @since	3.6
	 *  @date	23/01/13
	 *
	 *  @param	$value (mixed) the value found in the database
	 *  @param	$post_id (mixed) the $post_id from which the value was loaded
	 *  @param	$field (array) the field array holding all the field options
	 *  @return	$value
	 */
	function update_value( $value, $post_id, $field ) {

		// sanitize data from UI!

		// normalize markers


		$value = $this->decode_json_value( $value );

		if ( ! is_array( $value ) ) {
			$value = $this->defaults;
		}

		$value = $this->sanitize_value( $value, $field, 'update' );



		return $value;
	}


	/*
	 *  format_value()
	 *
	 *  This filter is appied to the $value after it is loaded from the db and before it is returned to the template
	 *
	 *  @type	filter
	 *  @since	3.6
	 *  @date	23/01/13
	 *
	 *  @param	$value (mixed) the value which was loaded from the database
	 *  @param	$post_id (mixed) the $post_id from which the value was loaded
	 *  @param	$field (array) the field array holding all the field options
	 *  @param	$escape_html (bool) whether ACF expects escaped output (ACF 6.2.5+)
	 *
	 *  @return	$value (mixed) the modified value
	 */
	function format_value( $value, $post_id, $field, $escape_html = false ) {

		// bail early if no value
		if ( empty( $value ) ) {
			return $value;
		}

		$value = $this->sanitize_value( $value, $field, 'display' );

		if ( 'raw' === $field['return_format'] ) {

			// ensure backwards compatibility <= 1.0.1
			$value['center_lat'] = $value['lat'];
			$value['center_lng'] = $value['lng'];

			// the field declares escaping_html for its HTML formats, so ACF leaves the raw array to us
			if ( $escape_html ) {
				$value = map_deep( $value, function( $item ) {
					return is_string( $item ) ? acf_esc_html( $item ) : $item;
				} );
			}

		} else {

			if ( 'osm' === $field['return_format'] && has_filter( 'osm_map_iframe_template' ) ) {
				_deprecated_hook( 'osm_map_iframe_template', '1.3.0', 'theme overrides', 'The filter is no longer in effect.' );
			}

			ob_start();

			get_template_part( 'osm-maps/' . $field['return_format'], null, [
				'field' => $field,
				'map' => $value,
			] );

			$value = ob_get_clean();

		}

		// return
		return $value;
	}

	/**
	 * Apply basic formatting to prepare the value for default REST output.
	 *
	 * @param mixed      $value
	 * @param int|string $post_id
	 * @param array      $field
	 * @return array|mixed
	 */
	public function format_value_for_rest( $value, $post_id, array $field ) {

		if ( ! $value ) {
			return null;
		}

		return acf_format_numerics( $value );
	}

	/**
	 * REST API schema. The field reads as an object, so it must accept one on write too.
	 * A JSON string is still accepted for backwards compatibility.
	 *
	 * @param array $field
	 * @return array
	 */
	public function get_rest_schema( array $field ) {

		$number = [ 'type' => [ 'number', 'string' ] ];

		return [
			'type'       => [ 'object', 'string', 'null' ],
			'required'   => ! empty( $field['required'] ),
			'properties' => [
				'lat'     => $number,
				'lng'     => $number,
				'zoom'    => [ 'type' => [ 'integer', 'string' ] ],
				'layers'  => [
					'type'  => 'array',
					'items' => [ 'type' => 'string' ],
				],
				'markers' => [
					'type'  => 'array',
					'items' => [
						'type'       => 'object',
						'properties' => [
							'label'         => [ 'type' => 'string' ],
							'default_label' => [ 'type' => 'string' ],
							'lat'           => $number,
							'lng'           => $number,
						],
					],
				],
				'address' => [ 'type' => [ 'string', 'number' ] ],
				'version' => [ 'type' => [ 'string', 'number' ] ],
			],
		];
	}

	/**
	 *	Decode a JSON field value in any shape ACF hands over: unslashed JSON (REST API, ACF Blocks),
	 *	slashed JSON (classic $_POST) or an already decoded array (block validation on load).
	 *
	 *	@param mixed $value
	 *	@return mixed Decoded array, or $value if it isn't a string
	 */
	private function decode_json_value( $value ) {

		if ( ! is_string( $value ) ) {
			return $value;
		}

		$decoded = json_decode( $value, true );

		if ( ! is_array( $decoded ) ) {
			// slashed JSON objects are never valid JSON
			$decoded = json_decode( wp_unslash( $value ), true );
		}

		return $decoded;
	}


	//*/


	/*
	 *  validate_value()
	 *
	 *  This filter is used to perform validation on the value prior to saving.
	 *  All values are validated regardless of the field's required setting. This allows you to validate and return
	 *  messages to the user if the value is not correct
	 *
	 *  @type	filter
	 *  @date	11/02/2014
	 *  @since	5.0.0
	 *
	 *  @param	$valid (boolean) validation status based on the value and the field's required setting
	 *  @param	$value (mixed) the $_POST value
	 *  @param	$field (array) the field array holding all the field options
	 *  @param	$input (string) the corresponding input name for $_POST value
	 *  @return	$valid
	 */
	function validate_value( $valid, $value, $field, $input ){

		// bail early if not required or no markers allowed
		if( ! $field['required'] || ( '' !== $field['max_markers'] && 0 === intval( $field['max_markers'] ) ) ) {

			return $valid;

		}

		$value = $this->decode_json_value( $value );

		if ( ! is_array( $value ) || empty( $value['markers'] ) || ! is_array( $value['markers'] ) ) {

			return __('Please set a marker on the map.','acf-openstreetmap-field');

		}

		// return
		return $valid;
	}



	/*
	 *  load_field()
	 *
	 *  This filter is applied to the $field after it is loaded from the database
	 *
	 *  @type	filter
	 *  @date	23/01/2013
	 *  @since	3.6.0
	 *
	 *  @param	$field (array) the field array holding all the field options
	 *  @return	$field
	 */
	function load_field( $field ) {

		return $this->sanitize_field( $field, 'display' );
	}

	/*
	*  update_field()
	*
	*  This filter is applied to the $field before it is saved to the database
	*
	*  @type	filter
	*  @date	23/01/2013
	*  @since	3.6.0
	*
	*  @param	$field (array) the field array holding all the field options
	*  @return	$field
	*/
	function update_field( $field ) {

		return $this->sanitize_field( $field, 'update' );
	}


	/**
	 *	@param array $field
	 *	@param string $context
	 *	@return array Sanitized $field
	 */
	private function sanitize_field( $field, $context = '' ) {

		$field = wp_parse_args( $field, $this->defaults );

		// typecast and restrict values
		$field['center_lat'] = floatval( $field['center_lat'] );
		$field['center_lng'] = floatval( $field['center_lng'] );
		$field['zoom']       = min( 22, max( 1, intval( $field['zoom'] ) ) );

		// custom marker icon url
		$field['marker_icon_url'] = esc_url_raw( $field['marker_icon_url'] );

		// layers
		$field['layers']     = MapValue::sanitize_layers( $field['layers'] );

		return $field;
	}

	/**
	 *	@action wp_footer
	 */
	public function maybe_print_media_templates() {
		if ( ! did_action( 'print_media_templates' ) ) {
			$this->print_media_templates();
		}
	}

	/**
	 *	@action print_media_templates
	 */
	public function print_media_templates() {
		?>
		<script type="text/html" id="tmpl-osm-marker-input">
			<div class="locate">
				<a class="dashicons dashicons-location" data-name="locate-marker">
					<span class="screen-reader-text">
						<?php esc_html_e('Locate Marker','acf-openstreetmap-field'); ?>
					</span>
				</a>
			</div>
			<div class="input">
				<input type="text" data-name="label" />
				<span class="coords">
					<input type="number" step="any" data-name="lat" placeholder="<?php esc_attr_e('lat','acf-openstreetmap-field'); ?>" aria-label="<?php esc_attr_e('Latitude','acf-openstreetmap-field'); ?>" />
					<input type="number" step="any" data-name="lng" placeholder="<?php esc_attr_e('lng','acf-openstreetmap-field'); ?>" aria-label="<?php esc_attr_e('Longitude','acf-openstreetmap-field'); ?>" />
				</span>
			</div>
			<div class="tools">
				<a class="acf-icon -minus small light acf-js-tooltip" href="#" data-name="remove-marker" title="<?php esc_attr_e('Remove Marker', 'acf-openstreetmap-field'); ?>"></a>
			</div>
		</script>
		<?php
	}
}
