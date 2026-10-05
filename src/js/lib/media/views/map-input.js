import $ from 'jquery';
import {L} from 'osm-map';

import {AddLocationMarker} from 'leaflet/control-add-location-marker';
import {FitBounds} from 'leaflet/control-fit-bounds';
import {Provider} from 'leaflet/tile-layer-provider';

import { addCorners } from 'leaflet/corners';

import { MarkerData, MapData } from 'media/models';
import { MarkerEntry } from 'media/views/marker-entry';
import { uniqid } from 'misc/uniquid';
import { mapZoomLevel } from 'misc/geocode';

import {GeocoderFactory} from 'media/views/geocoderFactory' ;

const { options, i18n } = acf_osm_admin
const instances = []

class MapInput extends Backbone.View {

	static L = L

	static getByElement( mapDiv ) {
		return instances.find( inst => inst.el === mapDiv )
	}

	get $value() {
		return this.$el.siblings('input.osm-json:first');
	}

	get $markers() {
		return this.$el.siblings('.osm-markers:first');
	}

	get mapData() {
		const data = JSON.parse( this.$value.val() );
		data.lat = data.lat || this.$el.attr('data-map-lat');
		data.lng = data.lng || this.$el.attr('data-map-lng');
		data.zoom = data.zoom || this.$el.attr('data-map-zoom');
		return data;
	}

	get markers() {
		return this.model.get('markers');
	}

	get countMarkers() {
		return this.markers.length;
	}

	get hasMarkers() {
		return this.countMarkers > 0
	}

	get maxMarkers() {
		return this.config.max_markers === false
			? Infinity
			: this.config.max_markers
	}

	get canAddMarker() {
		return this.countMarkers < this.maxMarkers
	}

	constructor(conf) {
		super(conf)
		instances.push(this)
		this.L = L
	}
	destructor() {
		const idx = instances.indexOf(this)
		if ( idx > -1 ) {
			instances.splice(idx,1)
		}
	}

	preventDefault( e ) {
		e.preventDefault();
	}

	initialize(conf) {

		super.initialize(conf)

		addCorners(conf.map)
		this.config      = this.$el.data().editorConfig;
		this.map         = conf.map;
		// this.field       = conf.field;
		this.model       = new MapData(this.mapData);
		this.plingMarker = false;

		this.init_locator_add();

		this.init_locator();

		// !! only if a) in editor && b) markers allowed !!
		if ( this.config.max_markers !== 0 ) {
			this.init_fit_bounds();
		}

		if ( this.config.allow_providers ) {
			// prevent default layer creation
			this.el.addEventListener( 'acf-osm-map-create-layers', e => e.preventDefault() )

			this.initLayers();
		}

		this.el.addEventListener( 'acf-osm-map-create-markers', e => e.preventDefault() )

		// reset markers in case field was duplicated with a row
		this.$markers.html('')
		this.initMarkers();

		this.listenTo( this.model, 'change', this.updateValue );
		this.listenTo( this.markers, 'add', this.initMarker );
		this.listenTo( this.markers, 'add', this.updateValue );
		this.listenTo( this.markers, 'remove', this.updateValue );
		this.listenTo( this.markers, 'change', this.updateValue );
		//this.listenTo( this.model, 'change:layers', console.trace );

		// update on map view change
		this.map.on('zoomend', () => {
			this.model.set('zoom',this.map.getZoom());
		});
		this.map.on('moveend', () => {
			const latlng = this.map.getCenter();
			const lat    = this.model.get('lat');
			const lng    = this.model.get('lng');

			// Layout changes move the center without user interaction: setView() snaps the map to
			// whole pixels and invalidateSize() re-centers to the nearest pixel when the container
			// resizes (e.g. a scrollbar showing up while the block editor saves meta boxes). Each
			// adds up to half a pixel per axis. Writing that drift back would flag the post as
			// changed, so ignore moves below 1.5px. Compare unrounded projections: container
			// points are rounded to whole pixels and would turn a half-pixel drift into 1px.
			if ( isFinite( lat ) && isFinite( lng ) ) {
				const stored = this.map.project( [ lat, lng ] );
				const center = this.map.project( latlng );
				if ( Math.abs( stored.x - center.x ) < 1.5 && Math.abs( stored.y - center.y ) < 1.5 ) {
					return;
				}
			}

			this.model.set( { lat: latlng.lat, lng: latlng.lng } );
		});

		//
		const attributionControl = this.map.attributionControl
		attributionControl.remove()
		attributionControl.options.position = 'below'
		attributionControl.addTo( this.map )

		this.update_visible();

		this.update_map();

		this.init_numeric_position();

		// kb navigation might interfere with other kb listeners
		this.map.keyboard.disable();

		this.el.dispatchEvent( new CustomEvent( 'osm-editor/initialized', { detail: {  view: this } } ), { bubbles: true } )

		return this;
	}

	init_numeric_position() {
		// #29: numeric lat/lng/zoom inputs for the map, two-way bound to the model.
		// Only in the value editor (the field-group settings preview omits the flag).
		if ( ! this.config.numeric_position ) {
			return;
		}

		const $form = $(
			'<div class="acf-osm-position">'
			+ `<label><span>${i18n.lat}</span><input type="number" step="any" data-name="map-lat" /></label>`
			+ `<label><span>${i18n.lng}</span><input type="number" step="any" data-name="map-lng" /></label>`
			+ `<label><span>${i18n.zoom}</span><input type="number" min="0" max="22" step="1" data-name="map-zoom" /></label>`
			+ '</div>'
		).insertAfter( this.$el )

		const $lat  = $form.find('[data-name="map-lat"]')
		const $lng  = $form.find('[data-name="map-lng"]')
		const $zoom = $form.find('[data-name="map-zoom"]')

		const sync = () => {
			$lat.val( this.model.get('lat') )
			$lng.val( this.model.get('lng') )
			$zoom.val( this.model.get('zoom') )
		}
		sync()

		this.listenTo( this.model, 'change:lat change:lng change:zoom', sync )

		$lat.add( $lng ).on( 'change', () => {
			const lat = parseFloat( $lat.val() ), lng = parseFloat( $lng.val() )
			if ( ! isNaN( lat ) && ! isNaN( lng ) ) {
				// store the typed position as is: the map can only center on whole pixels
				// and ignores moves below 1.5px (see moveend)
				this.model.set( { lat, lng } )
				this.map.panTo( [ lat, lng ] )
			}
		} )
		$zoom.on( 'change', () => {
			const z = parseInt( $zoom.val(), 10 )
			if ( ! isNaN( z ) ) {
				this.map.setZoom( z )
			}
		} )
	}

	init_fit_bounds() {
		// 2do: externalize L.Control.FitBoundsControl
		const cb = () => {
			if ( this.countMarkers === 0 ) {
				return;
			}
			const llb = L.latLngBounds();
			this.markers.forEach( marker => {
				llb.extend(L.latLng(marker.get('lat'),marker.get('lng')))
			});
			this.map.fitBounds(llb);
		}
		this.fitBoundsControl = new FitBounds({
			position: 'bottomright',
			callback: () => cb()
		}).addTo(this.map);
	}

	init_locator_add() {

		if ( this.config.max_markers === false || this.config.max_markers > 0 ) {
			this.locatorAdd = new AddLocationMarker({
				position: 'bottomleft',
				linkTitle: i18n.add_marker_at_location,
				callback: () => {
					if ( this.canAddMarker ) {
						this.currentLocation && this.addMarkerByLatLng( this.currentLocation );
					}
					this.locator.stop();
				}
			}).addTo(this.map);
		}
	}

	init_locator() {
		this.currentLocation = false;

		this.locator = L.control.locate({
			position: 'bottomleft',
			icon: 'dashicons dashicons-location-alt',
			iconLoading: 'spinner is-active',
			flyTo: true,
			strings: {
				title: i18n.my_location
			},
			onLocationError: err => {}
		}).addTo(this.map);


		this.map.on('locationfound', e => {

			this.currentLocation = e.latlng;

			setTimeout(() => {
				if ( this.config.max_markers === false || this.config.max_markers > 0 ) {
					this.locator.stopFollowing();
				} else {
					this.locator.stop();
				}
				$(this.locator._icon).removeClass('dashicons-warning');
				//this.locatorAdd.addTo(this.map)
			},1);
		})
		this.map.on('locationerror',e => {
			this.currentLocation = false;
			this.locator.stop();
			setTimeout(() => {
				$(this.locator._icon).addClass('dashicons-warning');
			},1);
		})
	}

	updateValue() {
		// no literal < > & (like render_field() in PHP): ACF runs posted values through wp_kses for
		// users without unfiltered_html, which would break the JSON
		const value = JSON.stringify( this.model.toJSON() )
			.replace( /[<>&]/g, c => '\\u' + c.charCodeAt( 0 ).toString( 16 ).padStart( 4, '0' ) );
		// ACF flags the post as changed on every `change` event, so only fire it on a real change
		if ( value !== this.$value.val() ) {
			this.$value.val( value ).trigger('change');
		}
		//this.$el.trigger('change')
		this.updateMarkerState();
	}

	updateMarkerState() {
		this.$el.attr('data-has-markers', this.hasMarkers.toString() );
		this.$el.attr('data-can-add-marker', this.canAddMarker.toString() );
	}

	/**
	 *	Init marker from Model
	 *
	 *	@param Backbone.Model model
	 *	@param Backbone.Collection collection
	 */
	initMarker( model ) {

		// add marker to map
		/** @var leaflet marker */
		const marker = L.marker( { lat: model.get('lat'), lng: model.get('lng') }, {
				title: model.get('label'),
				icon: this.icon,
				draggable: ! this.config.markers_search_only
			})
			.bindTooltip( model.get('label') );

		if ( ! model.get('uuid') ) {
			model.set( 'uuid', uniqid('marker_') )
		}

		/** @var Backbone.View */
		const entry = new MarkerEntry({
			controller: this,
			marker: marker,
			model: model
		});

		this.map.once('layeradd',e => {
			// changes on model affect map
			marker
				.on('click', e => {
					model.destroy();
				})
				.on('dragend', e => {
					// update model lnglat on drag
					const latlng = marker.getLatLng();
					model.set( 'lat', latlng.lat );
					model.set( 'lng', latlng.lng );
					this.reverseGeocode( model );
					// geocode, get label, set model label...
				})

			entry.$el.appendTo( this.$markers );
		});

		model.on( 'destroy', () => {
			this.el.dispatchEvent( new CustomEvent( 'osm-editor/destroy-marker', { detail: {  model } } ), { bubbles: true } )
			marker.remove();
		});

		const changedlatLng = e => {
			this.el.dispatchEvent( new CustomEvent( 'osm-editor/update-marker-latlng', { detail: { model } } ), { bubbles: true } )
		}
		this.listenTo( model, 'change:lat', changedlatLng );
		this.listenTo( model, 'change:lng', changedlatLng );

		marker.addTo( this.map );

		if ( this.plingMarker ) {
			entry.pling();
		}
	}

	initMarkers(){

		this.initGeocode();

		// no markers allowed!
		if ( this.config.max_markers === 0 ) {
			return;
		}

		this.icon = new L.DivIcon({
			html: '',
			className: 'osm-marker-icon'
		});

		this.markers.forEach( model => {
			this.initMarker( model );
		} );

		// adding marker pointer action – disabled when markers are search-only (#91)
		if ( ! this.config.markers_search_only ) {
			if ( L.Browser.touch && L.Browser.mobile ) {
				this._add_marker_on_hold();
			} else {
				this._add_marker_on_dblclick();
			}
		}

		this.updateMarkerState();
	}

	_add_marker_on_dblclick() {

		this.map.on('dblclick', e => {
			const { latlng } = e;

			L.DomEvent.preventDefault(e);
			L.DomEvent.stopPropagation(e);

			this.addMarkerByLatLng( latlng );
		})
		.doubleClickZoom.disable();
		this.$el.addClass('add-marker-on-dblclick')
	}

	_add_marker_on_hold() {

		const _hold_timeout = 750
		const _hold_wait_to = {}
		const container     = this.map.getContainer()

		// TODO use https://developer.mozilla.org/en-US/docs/Web/API/Element/setPointerCapture

		// stop waiting for tap-hold
		const pointerend  = e => {
			if ( ! _hold_wait_to[ 'p'+e.pointerId ] ) {
				return
			}
			const { x, y } = _hold_wait_to[ 'p'+e.pointerId ].coord
			const delta    = Math.sqrt( Math.pow( e.clientX - x, 2 ) + Math.pow( e.clientY - y, 2 ) )
			if ( delta < 1 ) {
				return
			}

			clearTimeout( _hold_wait_to[ 'p'+e.pointerId ].to )
			container.removeEventListener( 'pointerup', pointerend )
			container.removeEventListener( 'pointermove', pointerend )
			delete _hold_wait_to[ 'p'+e.pointerId ]
		}
		container.addEventListener( 'pointerdown',e => {

			// wait for tap-hold
			_hold_wait_to[ 'p'+e.pointerId ] = {
				to: setTimeout(() => {
					var cp = this.map.mouseEventToContainerPoint(e);
					var lp = this.map.containerPointToLayerPoint(cp)

					this.addMarkerByLatLng( this.map.layerPointToLatLng(lp) )

					_hold_wait_to[ 'p'+e.pointerId ] = false;
				}, _hold_timeout ),
				coord: {
					x: e.clientX,
					y: e.clientY,
				},
			}

			container.addEventListener('pointerup', pointerend, { once: false, passive: false } )
			container.addEventListener('pointermove', pointerend, { once: false, passive: false } )

		}, { capture: true, passive: false } )

		this.$el.addClass('add-marker-on-taphold')
	}

	addMarkerByLatLng(latlng) {
		// no more markers
		if ( ! this.canAddMarker ) {
			return;
		}
		const model = new MarkerData({
			label: '',
			default_label: '',
			lat: latlng.lat,
			lng: latlng.lng,
			geocode: [],
		});

		this.plingMarker = true;
		const marker = this.markers.add( model ); // will call initMarker > will add marker to map
		this.reverseGeocode( model );

		this.el.dispatchEvent( new CustomEvent( 'osm-editor/create-marker', { detail: { model } } ), { bubbles: true } )
	}

	/**
	 *	Geocoding
	 *
	 *	@on map.layeradd, layer.dragend
	 */
	initGeocode() {

		let $above = this.$el.prev();
		if ( ! $above.is( '.acf-osm-above' ) ) {
			$above = $('<div class="acf-osm-above"></div>').insertBefore( this.$el );
		} else {
			$above.html('');
		}
		// add an extra control panel region for out search
		this.map._controlCorners['above'] = $above.get(0);

		const geocoderEngine = GeocoderFactory.createGeocoder( options );

		const geocoder_options = Object.assign({
			collapsed: false,
			position: 'above',
			placeholder: i18n.search,
			errorMessage: i18n.nothing_found,
			showResultIcons:true,
			suggestMinLength:3,
			suggestTimeout:250,
			queryMinLength:3,
			defaultMarkGeocode:false,
			// geocodingQueryParams: {'accept-language':'de_DE'},
			//geocoder: L.Control.Geocoder.nominatim( nominatim_options )
			geocoder: geocoderEngine
		}, options.geocoder );

		this.geocoder = L.Control.geocoder( geocoder_options )
			.on( 'markgeocode', e => {

				// search result click
				let model,
					previousGeocode = false;

				const latlng =  e.geocode.center,
					label = this.parseGeocodeResult( [ e.geocode ], latlng ),
					marker_data = {
						label: label,
						default_label: label,
						lat: latlng.lat,
						lng: latlng.lng,
						geocode: [ e.geocode ],
					}

				// getting rid of the modal – #35
				this.geocoder._clearResults();
				this.geocoder._input.value = '';

				// no markers - just adapt map view
				if ( this.config.max_markers === 0 ) {

					return this.map.fitBounds( e.geocode.bbox );

				}

				if ( this.canAddMarker ) {
					// infinite markers or markers still in range
					model = this.markers.add( marker_data );
					this.el.dispatchEvent( new CustomEvent( 'osm-editor/create-marker', { detail: {  model } } ), { bubbles: true } )

				} else if ( this.maxMarkers === 1 ) {
					// one marker only
					model = this.markers.at(0)
					previousGeocode = model.get('geocode')
					model.set( marker_data );
				}

				this.map.setView( latlng, this.map.getZoom() ); // keep zoom, might be confusing else

				this.el.dispatchEvent( new CustomEvent( 'osm-editor/marker-geocode-result', { detail: {  model, geocode: e.geocode, previousGeocode } } ), { bubbles: true } )

			})
			.addTo( this.map );

		// Issue #87 - <button>This is not a button</button>
		const iconButton = this.geocoder.getContainer().querySelector('.leaflet-control-geocoder-icon')
		L.DomEvent.on(
			iconButton,
			'click',
			function() {
				if (this._selection) {
					var index = parseInt(this._selection.getAttribute('data-result-index'), 10);

					this._geocodeResultSelected(this._results[index]);

					this._clearResults();
				} else {
					this._geocode();
				}
			},
			this.geocoder
		)
		// The geocoder control also searches on touchstart (or click) on its container. Leaflet 1.9 reports
		// touch support wherever pointer events exist, so one tap would send two search requests.
		L.DomEvent.on( iconButton, 'touchstart click', L.DomEvent.stopPropagation )
	}

	reverseGeocode( model ) {

		// the detail level is a geocoder setting (acf_osm_admin.options.geocoder_options), not a control option
		const latlng = { lat: model.get('lat'), lng: model.get('lng') },
			scale  = this.geocoder.options.scale ?? options.geocoder_options?.scale ?? '18';
		let zoom = 'auto' === scale
			? mapZoomLevel( this.map.getZoom() )
			: parseInt( scale, 10 );
		if ( ! Number.isFinite( zoom ) ) {
			zoom = 18;
		}

		this.geocoder.options.geocoder.reverse( latlng, this.map.options.crs.scale( zoom ) ).then(
			geocode => {
				const previousGeocode = model.get('geocode' )

				model.set('geocode', geocode );
				model.set('default_label', this.parseGeocodeResult( geocode, latlng ) );

				this.el.dispatchEvent( new CustomEvent( 'osm-editor/marker-geocode-result', { detail: {  model, geocode, previousGeocode }} ), { bubbles: true } )
			}
		);
	}

	parseGeocodeResult( results, latlng ) {

		var label = false;

		if ( ! results.length ) {
			label = latlng.lat + ', ' + latlng.lng;
		} else {
			$.each( results, ( i, result ) => {
				if( result.html )
					// `htmlTemplate` method and `html` property are available.
					label = result.html ;
				else if( result.name )
					// Default to `name` property
					label = result.name ;
				else
					label = 'Failed to decode geocoder result 😕';
			});
		}
		// trim
		return label;
	}

	/**
	 *	Layers
	 */
	initLayers() {

		const selectedLayers = this.model.get('layers') || []

		// Layers offered in the picker respect the global enable/disable settings,
		// but keep any layer the field already uses – even if its provider was
		// disabled globally afterwards – so existing selections still render and
		// aren't silently dropped on save. See #109.
		const offeredLayers  = this.config.restrict_providers || Object.values(acf_osm_admin.options.leaflet_layers)
		const allLayers      = [ ...new Set( [ ...offeredLayers, ...selectedLayers ] ) ]
			.map( providerKey => { // be forgiving with deceased providers
				try {
					return L.tileLayer.provider( providerKey )
				} catch ( err ) {}
			} )
			.filter( e => !! e )

		const baseLayers     = Object.fromEntries( allLayers.filter( l => ! l.overlay ).map( l => [ l.providerKey, l ] ) )
		const overlays       = Object.fromEntries( allLayers.filter( l => l.overlay ).map( l => [ l.providerKey, l ] ) )

		const activeLayers   = allLayers
			.filter( layer => selectedLayers.includes( layer.providerKey ) )

		// No valid base layer selected – e.g. the configured tile provider was
		// discontinued (Stamen, …) and no longer exists. Fall back to the
		// default OpenStreetMap layer so the editor map is not blank. See #134.
		if ( ! activeLayers.some( layer => ! layer.overlay ) ) {
			const fallback = baseLayers['OpenStreetMap.Mapnik'] || Object.values( baseLayers )[0]
			if ( fallback ) {
				activeLayers.unshift( fallback )
			}
		}

		activeLayers
			.sort( (a,b) => a.overlay - b.overlay ) // overlays on top
			.forEach( layer => layer.addTo( this.map ) )
		this.syncMaxZoom()

		// update model
		this._onLayersChanged = e => {

			if ( ! e.layer.providerKey ) {
				return;
			}
			var layers = [];

			this.map.eachLayer( layer => {
				if ( ! layer.providerKey ) {
					return;
				}

				if ( layer.overlay ) {
					layers.push( layer.providerKey )
				} else {
					layers.unshift( layer.providerKey )
				}
			});
			this.model.set( 'layers', layers );
			this.syncMaxZoom()
		};
		this.map.on( 'baselayerchange layeradd layerremove', this._onLayersChanged );

		this.layersControl = L.control.layers( baseLayers, overlays, {
			collapsed: true,
			hideSingleBase: true,
		}).addTo(this.map);
	}

	/**
	 *	Cap the zoom like the front end does (osm-map.js createLayers): at the lowest maxZoom of the map's
	 *	layers. Leaflet itself allows the highest one, where the other layers are gone.
	 */
	syncMaxZoom() {
		let maxZoom = Infinity
		this.map.eachLayer( layer => {
			if ( layer.providerKey && layer.options.maxZoom ) {
				maxZoom = Math.min( maxZoom, layer.options.maxZoom )
			}
		} )
		if ( isFinite( maxZoom ) ) {
			this.map.setMaxZoom( maxZoom )
		}
	}

	resetLayers() {
		// remove all map layers
		this.map.eachLayer( layer => {
			if ( layer.constructor === L.TileLayer.Provider ) {
				layer.remove();
			}
		})
		// only remove our own listener: without a handler off() would also drop Leaflet's,
		// e.g. the attribution control's layeradd listener (Leaflet 1.8+)
		if ( this._onLayersChanged ) {
			this.map.off( 'baselayerchange layeradd layerremove', this._onLayersChanged )
			this._onLayersChanged = null
		}
		// remove layer control
		!! this.layersControl && this.layersControl.remove()
	}

	update_visible() {
		// no change
		if ( this.visible === this.$el.is(':visible') ) {
			return this;
		}

		this.visible = this.$el.is(':visible');

		if ( this.visible ) {
			this.map.invalidateSize();
		}
		return this;
	}

	update_map() {
		var latlng = { lat: this.model.get('lat'), lng: this.model.get('lng') }
		this.map.setView(
			latlng,
			this.model.get('zoom')
		);
	}
}

document.addEventListener( 'acf-osm-map-init', e => {
	// setup map input element
	if ( e.target.matches('[data-editor-config]') ) {
		const { map } = e.detail;
		new MapInput( { el: e.target, map: map } );
	}
} )

export {MapInput}
