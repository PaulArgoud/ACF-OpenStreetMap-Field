import {L} from 'leaflet/no-conflict';
import 'leaflet/tile-layer-provider';
import 'leaflet-gesture-handling'; // registers the optional `gestureHandling` map handler (#70)

(function( arg ){

	const leafletMapSelector = '[data-map="leaflet"]';

	const camelCase = str => {
		return str.replace( /[-_\s.]+(.)?/g, (_, c) => c ? c.toUpperCase() : '' ).replace(/\s+/g, '');
	}

	const isVisible = el => {
		return !! ( el.offsetWidth || el.offsetHeight || el.getClientRects().length )
	}

	const parseDataAttributes = el => {
		const data = {}
		Array.from(el.attributes).forEach( attr => {
			let prop, val
			if ( attr.name.indexOf('data-') === 0 ) {
				prop = camelCase( attr.name.substr(5) )

				try {
					val = JSON.parse(attr.value)
				} catch (err) {
					val = attr.value
				}
				data[ prop ] = val
			}
		})
		return data;
	}

	/**
	 *	@param HTMLElement el Map Div
	 *	@return boolean
	 */
	const acfLeaflet = el => {

		if ( !! el.acfOsmMap ) {
			return false
		}

		let map, maxzoom,
			initEvt;

		const data = parseDataAttributes( el ),
			mapInit = {
				scrollWheelZoom: false,
				center: [ bulletproofParseFloat(data.mapLat), bulletproofParseFloat(data.mapLng) ],
				zoom: data.mapZoom,
				worldCopyJump: true,
				gestureHandling: !! data.gestureHandling, // #70 (opt-in via field setting)
			},
			createEvt = new CustomEvent( 'acf-osm-map-create', {
				bubbles: true,
				cancelable: true,
				detail: {
					mapInit: mapInit,
					L: L
				},
			});

		el.dispatchEvent( createEvt )

		// allow to skip map creation
		if ( createEvt.defaultPrevented ) {
			return;
		}

		el.style.height = data.height + 'px';

		map = L.map( el, createEvt.detail.mapInit ); // map init might have been mutated by event listeners
		el.acfOsmMap = map;
		// $(this).data( 'acf-osm-map', map );

		initEvt = new CustomEvent( 'acf-osm-map-init', {
			detail: {
				map: map,
				L: L
			},
			cancelable: true,
			bubbles: true
		})
		el.dispatchEvent( initEvt )

		// allow to skip initialization
		if ( initEvt.defaultPrevented ) {
			return;
		}

		createLayers.apply( el, [ data, map ] );

		createMarkers.apply( el, [ data, map ] );

		// #135: optionally zoom/center the map to fit all markers (opt-in).
		maybeFitBounds( data, map );

		// reload maps when they become visible
		el.addEventListener( 'acf-osm-show', e => {
			// dispatched on every attribute change in the page: only when the size actually changed
			const size = map.getSize()
			if ( size.x !== el.clientWidth || size.y !== el.clientHeight ) {
				map.invalidateSize();
			}
		} )

		// Follow container size changes that come without any attribute change, e.g. the WP 7.1 block
		// editor's meta box pane settling or its settings sidebar closing. Keeps the center (pan),
		// so the editor sees at most a sub-pixel move.
		if ( 'ResizeObserver' in window ) {
			let frame = 0
			const resizeObserver = new ResizeObserver( () => {
				cancelAnimationFrame( frame )
				frame = requestAnimationFrame( () => {
					const size = map.getSize()
					if ( el.offsetWidth && el.offsetHeight // skip hidden maps
						&& ( size.x !== el.clientWidth || size.y !== el.clientHeight )
					) {
						map.invalidateSize( { debounceMoveend: true } )
					}
				} )
			} )
			resizeObserver.observe( el )
			map.on( 'unload', () => {
				cancelAnimationFrame( frame )
				resizeObserver.disconnect()
			} )
		}

		// finished!
		el.dispatchEvent( new CustomEvent( 'acf-osm-map-created', {
			bubbles: true,
			detail: {
				map: map,
				L: L
			}
		 } ) )
		 return true
	}

	const leafletAll = el => {
		el.querySelectorAll( leafletMapSelector ).forEach( el => maybeAcfLeaflet(el) )
	}
	const maybeAcfLeaflet = el => {

		if ( ! acfLeaflet( el ) ) {
			el.dispatchEvent( new CustomEvent( 'acf-osm-show', {
				detail: { L: L }
			} ) );
		}
	}

	// observe if new maps are added to the dom
	if ( !! MutationObserver ) {
		//
		const visibilityObserver = new MutationObserver( function(entries,observer) {
			entries
				.filter( entry => !! entry.target.querySelector(leafletMapSelector) )
				.forEach( entry => {
					entry.target.querySelectorAll( leafletMapSelector ).forEach( el => {
						if ( el.getBoundingClientRect().width > 0 ) {
							el.dispatchEvent( new CustomEvent( 'acf-osm-show', {
								detail: { L: L }
							} ) );
						}
					} )
				})
		})

		// observe added maps
		const domObserver = new MutationObserver( function(entries,observer) {
			entries.forEach( entry => {
				leafletAll(entry.target)
			})
		});
		window.addEventListener('DOMContentLoaded', e => {
			// no body yet in the block editor's canvas iframe (WP 7.1+) when this runs
			const root = document.body || document.documentElement
			leafletAll(root)
			domObserver.observe( root, { subtree: true, childList: true } );
			visibilityObserver.observe( root, { subtree: true, attributes: true } );
		})
	}

	// #64
	const bulletproofParseFloat = value => {
		// already a number
		if ( 'number' === typeof value ) {
			return value;
		}
		if ( 'string' === typeof value ) {
			// some messed around with value
			if ( value.indexOf('.') === -1 && value.indexOf(',') !== -1 ) {
				value = value.split(',').join('.')
			}
			return parseFloat( value )
		}
		return NaN
	}


	// L.TileLayer.Provider.providers = arg.providers;
	const options = arg.options;

	function createMarkers( data, map ) {
		// const self = this, // @var DIV element
			/*
			createEvt = $.Event({
				type: 'acf-osm-map-create-markers',
			}),
			/*/
		const createEvt = new CustomEvent('acf-osm-map-create-markers', {
				bubbles: true,
				cancelable: true,
				detail: {
					map: map,
					mapData: data,
					L: L
				}
			} ),
			//*/
			default_marker_config = {};

		this.dispatchEvent( createEvt )

		// allow to skip map creation
		if ( createEvt.defaultPrevented ) {
			return;
		}

		// markers ...
		if ( data.markerIcon ) {
			// #135: per-field custom marker icon URL (no-code). For fine-grained
			// control (size, anchor, retina, …) use the `acf_osm_marker_icon` filter.
			default_marker_config.icon = L.icon({ iconUrl: data.markerIcon });
		} else if ( arg.options.marker.html !== false ) {
			default_marker_config.icon = L.divIcon({
				html: arg.options.marker.html,
				className: arg.options.marker.className
			});
		} else if ( arg.options.marker.icon !== false ) {
			default_marker_config.icon = new L.icon( arg.options.marker.icon );
		}


		data.mapMarkers?.forEach( (markerData, i ) => {
			// add markers
			let marker;

			const createEvt = new CustomEvent( 'acf-osm-map-marker-create', {
				bubbles: true,
				cancelable: true,
				detail: {
					map: map,
					markerData: markerData,
					markerOptions: Object.assign( default_marker_config, {
						label: markerData.label // <= TODO: deprecate this!
					} ),
					L: L
				}
			} );
			this.dispatchEvent( createEvt )

			if ( createEvt.defaultPrevented ) {
				return;
			}

			marker = L.marker(
					L.latLng( bulletproofParseFloat( createEvt.detail.markerData.lat ), bulletproofParseFloat( createEvt.detail.markerData.lng ) ),
					createEvt.detail.markerOptions
				)
				.bindPopup( createEvt.detail.markerOptions.label )
				.addTo( map );

			this.dispatchEvent(new CustomEvent('acf-osm-map-marker-created',{
				bubbles: true,
				detail: { map, marker, L }
			}))
		} )
		// markers again


	}

	function createLayers( data, map ) {
		let maxzoom;

		const createEvt = new CustomEvent( 'acf-osm-map-create-layers', {
				bubbles: true,
				cancelable: true,
				detail: {
					map: map,
					mapData: data,
					L: L
				}
			});

		this.dispatchEvent( createEvt );

		// allow to skip map creation
		if ( createEvt.defaultPrevented ) {
			return;
		}

		maxzoom = 100;


		const layers = data.mapLayers
			.filter( providerKey => 'string' === typeof providerKey ) // string keys only
			.map( providerKey => { // be forgiving with deceased providers
				try {
					return L.tileLayer.provider( providerKey )
				} catch( err ) {}
			} )
			.filter(e=>!!e) //

		// No valid base layer left – e.g. the selected tile provider was
		// discontinued (Stamen, …). Fall back to the default OpenStreetMap
		// layer so the map shows tiles instead of staying blank. See #134.
		if ( ! layers.some( layer => ! layer.overlay ) ) {
			try {
				layers.unshift( L.tileLayer.provider( 'OpenStreetMap.Mapnik' ) )
			} catch( err ) {}
		}

		layers
			.sort( (a,b) => a.overlay - b.overlay ) // overlays always on top
			.forEach( layer => { // add

				layer.addTo(map)

				if ( !! layer.options.maxZoom ) {
					maxzoom = Math.min( layer.options.maxZoom, maxzoom )
				}

			} )

		map.setMaxZoom( maxzoom );
	}

	// #135: when enabled in the field settings, zoom/center the map so all
	// markers fit into view. Opt-in, so the editor's saved center/zoom stays
	// the default behaviour.
	function maybeFitBounds( data, map ) {

		if ( ! data.fitBounds || ! Array.isArray( data.mapMarkers ) || ! data.mapMarkers.length ) {
			return;
		}

		const latLngs = data.mapMarkers
			.map( marker => L.latLng( bulletproofParseFloat( marker.lat ), bulletproofParseFloat( marker.lng ) ) )
			.filter( latLng => ! isNaN( latLng.lat ) && ! isNaN( latLng.lng ) );

		if ( ! latLngs.length ) {
			return;
		}

		map.fitBounds( L.latLngBounds( latLngs ), {
			padding: [ 20, 20 ],
			maxZoom: data.mapZoom, // don't zoom in further than the configured zoom
		} );
	}

	window.addEventListener('DOMContentLoaded', e => {
		document.querySelectorAll( leafletMapSelector ).forEach( acfLeaflet )

		// domObserver.observe( document.body, { subtree: true, childList: true } );
	})
	document.addEventListener('acf-osm-map-added', e => {
		if ( e.target.matches( leafletMapSelector ) ) {
			acfLeaflet( e.target )
		} else {
			document.querySelectorAll( leafletMapSelector ).forEach( acfLeaflet )
		}
	})

})( acf_osm );

export {L}
