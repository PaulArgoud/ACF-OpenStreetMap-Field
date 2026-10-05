import { MapInput } from 'media/views';

//  prevent map initialization in clone fields
document.addEventListener( 'acf-osm-map-create', e => e.target.closest('[data-id="acfcloneindex"]') && e.preventDefault() )

acf.registerFieldType( acf.Field.extend({
	type: 'open_street_map',
	$input: function() {
		return this.$('input.osm-json')
	},
	$map: function() {
		return this.$('.leaflet-map')
	},
	getMapValue: function() {
		const editor = this.get('osmEditor')
		if ( editor ) {
			// plain data, like the parsed input value (markers as an array, not a Backbone collection)
			return JSON.parse( JSON.stringify( editor.model ) )
		}
		return JSON.parse( this.$input().val() )
	},
	// getValue: function() {
	// 	return this.getMapValue();
	// },
	// setValue: function (val) {
	// 	if ( String !== val.constructor ) {
	// 		val = JSON.stringify(val);
	// 	}
	// 	return acf.val(this.$input(), val);
	// },
	countMarkers: function() {
		return this.getMapValue().markers?.length||0
	},
	setup: function($field) {
		const mapDiv = $field.get(0).querySelector('.leaflet-map')
		const editor = MapInput.getByElement( mapDiv )
		let newMapDiv = null
		// reset map (field duplicated with a repeater row or flexible content layout: a copy of an initialized map without editor)
		if ( mapDiv.matches('.leaflet-container') && ! editor ) {
			// remove custom leaflet outer control areas and the numeric position inputs
			$field.get(0).querySelectorAll('.leaflet-above,.leaflet-below,.acf-osm-position').forEach( el => el.remove() )
			// reset class
			// create fresh clone
			newMapDiv = mapDiv.cloneNode(false)
			newMapDiv.innerHTML = '';
			newMapDiv.setAttribute('class','leaflet-map')
			mapDiv.parentNode.replaceChild(newMapDiv, mapDiv);
		}
		acf.Field.prototype.setup.apply( this, [ $field ] )
		this.$map().get(0).addEventListener('osm-editor/initialized', e => {
			this.set('osmEditor',e.detail.view);
		})
		if ( newMapDiv ) {
			// init the clone once the listener above can pick up its editor
			newMapDiv.dispatchEvent( new CustomEvent('acf-osm-map-added', { bubbles: true } ))
		} else if ( editor ) {
			// map already initialized before this field
			this.set( 'osmEditor', editor )
		}
	},
	initialize: function(){
		const mapDiv = this.$map().get(0)
		mapDiv.addEventListener( 'osm-editor/create-marker', e => this.createMarker(e) )
		mapDiv.addEventListener( 'osm-editor/destroy-marker', e => this.destroyMarker(e) )
		mapDiv.addEventListener( 'osm-editor/update-marker-latlng', e => this.updateMarkerLatlng(e) )
		mapDiv.addEventListener( 'osm-editor/marker-geocode-result', e => this.geocodeResult(e) )
	},
	createMarker: function( e ) {
		const {  model } = e.detail
		acf.doAction('acf-osm/create-marker', model, this );
	},
	destroyMarker: function( e ) {
		const {  model } = e.detail
		acf.doAction('acf-osm/destroy-marker', model, this );
	},
	updateMarkerLatlng: function( e ) {
		const {  model } = e.detail
		acf.doAction('acf-osm/update-marker-latlng', model, this );
	},
	geocodeResult: function( e ) {
		const { model, geocode, previousGeocode } = e.detail
		acf.doAction('acf-osm/marker-geocode-result', model, this, geocode, previousGeocode );
	},
} ) );
