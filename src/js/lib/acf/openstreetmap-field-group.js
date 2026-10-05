import { MapInput } from 'media/views';

//  prevent map initialization in clone fields
document.addEventListener( 'acf-osm-map-create', e => e.target.closest('[data-id="acfcloneindex"]') && e.preventDefault() )

// const resetMap = map => {
// 	map.eachLayer(function(layer) { layer.remove() } );
// }

acf.registerFieldType( acf.Field.extend({
	type: 'open_street_map',
	wait: 'load',
	events: {
		// 'input input[name$="[center_lat]"]':'changeLat',
		// 'input input[name$="[center_lng]"]':'changeLng',
		// 'input input[name$="[zoom]"]':'changeZoom',
	},
	$map: function() {
		return this.$('.leaflet-map')
	},
	$lat: function() {
		return this.$el.closest('.acf-field-type-settings').find('input[name$="[center_lat]"]');
	},
	$lng: function() {
		return this.$el.closest('.acf-field-type-settings').find('input[name$="[center_lng]"]');
	},
	$zoom: function() {
		return this.$el.closest('.acf-field-type-settings').find('input[name$="[zoom]"]');
	},
	$layers: function() {
		return this.$el.closest('.acf-field-type-settings').find('select[name$="[layers][]"]');
	},
	// $osmLayers: function() {
	// 	return this.$el.closest('.acf-field-type-settings').find('select[name$="[layers]"]');
	// },
	// $layers: function() {
	// 	 return 'osm' === this.$returnFormat().filter(':checked').val()
	// 	 	? this.$osmLayers()
	// 		: this.$leafletLayers()
	// },
	$returnFormat: function() {
		return this.$el.closest('.acf-field-settings').find('input[name$="[return_format]"]');
	},
	initialize: function() {
		// runs inside ACF's 'load' / 'append' action loop: an exception here would skip every
		// handler queued after it (other fields, other plugins)
		try {
			let mapDiv = this.$map().get(0)
			if ( ! mapDiv ) {
				return
			}
			if ( ! MapInput.getByElement( mapDiv ) ) {
				// ACF fires 'append' (field type changed, field duplicated) before osm-map.js's
				// MutationObserver creates the preview map
				if ( mapDiv.classList.contains('leaflet-container') ) { // clone of an initialized map
					this.$('.acf-osm-above,.acf-osm-below,.acf-osm-position').remove()
					const freshMapDiv = mapDiv.cloneNode( false )
					freshMapDiv.setAttribute( 'class', 'leaflet-map' )
					mapDiv.replaceWith( freshMapDiv )
					mapDiv = freshMapDiv
				}
				// creates the map and its MapInput synchronously
				mapDiv.dispatchEvent( new CustomEvent( 'acf-osm-map-added', { bubbles: true } ) )
			}

			this.editor = MapInput.getByElement( mapDiv )
			if ( ! this.editor ) {
				return
			}

			// ACF creates a new field instance each time the settings of a cached field type come back
			// (OSM -> other type -> OSM): bind only once per map editor
			if ( ! this.editor.fieldGroupListenersBound ) {
				this.editor.fieldGroupListenersBound = true
				this.bindListeners()
			}
			this.setMapLayers(true)
		} catch ( err ) {
			console.error( err )
		}
	},
	setMapLnglat: function(e) {
		const lat = parseFloat( this.$lat().val() )
		const lng = parseFloat( this.$lng().val() )
		// skip incomplete input ('', '52.', …)
		if ( ! Number.isFinite( lat ) || ! Number.isFinite( lng ) ) {
			return
		}
		this.editor.map.panTo( { lat, lng }, { animate: false, duration: 0 } );
	},
	setMapZoom: function(e) {
		const zoom = parseInt( this.$zoom().val(), 10 )
		if ( Number.isFinite( zoom ) ) {
			this.editor.map.setZoom( zoom )
		}
	},
	setMapLayers: function() {
		const isDirty = acf.unload.changed
		const layers = this.editor.model.get('layers')
		this.editor.config.restrict_providers = 'osm' === this.$returnFormat().filter(':checked').val()
			? Object.values(acf_osm_admin.options.osm_layers)
			: false

		this.editor.resetLayers()
		this.editor.model.set( 'layers', layers )
		this.editor.initLayers()

		// dont let layer change affect confirm dialog
		if ( ! isDirty ) {
			acf.unload.stopListening()
		}
	},
	bindListeners: function() {
		// set input from map
		this.editor.model
			.on( 'change:lat',  () => this.$lat().val( this.editor.model.get('lat') ).trigger('change') )
			.on( 'change:lng',  () => this.$lng().val( this.editor.model.get('lng') ).trigger('change') )
			.on( 'change:zoom', () => this.$zoom().val( this.editor.model.get('zoom') ).trigger('change') )
			.on( 'change:layers', () => this.$layers().val( this.editor.model.get('layers') ).trigger('change') )

		// set map from input
		this.$lat().on( 'input', () => this.setMapLnglat() )
		this.$lng().on( 'input', () => this.setMapLnglat() )
		this.$zoom().on( 'input', () => this.setMapZoom() )

		this.$returnFormat().on( 'change', () => this.setMapLayers() )

	}
}) )
