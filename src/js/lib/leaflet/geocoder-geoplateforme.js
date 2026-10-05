import { L } from 'osm-map';

/** @var {Object} size in meters of the area shown for a result, by result type (the API gives a point only) */
const RESULT_SIZE = {
	housenumber: 150,
	street: 1000,
	locality: 1000,
	municipality: 10000,
}

/**
 *	Address search of the French Géoplateforme (IGN): French addresses from the Base Adresse Nationale,
 *	no API key. Implements the IGeocoder interface of leaflet-control-geocoder.
 *
 *	@see https://cartes.gouv.fr/aide/fr/guides-utilisateur/utiliser-les-services-de-la-geoplateforme/
 */
class Geoplateforme {

	constructor( options ) {
		this.options = Object.assign( {
			serviceUrl: 'https://data.geopf.fr/geocodage/',
			geocodingQueryParams: {},
			reverseQueryParams: {},
			limit: 5,
			nameTemplate: null, // ( properties, zoom ) => string, plain text
		}, options )
	}

	geocode( query ) {
		// the API wants 3 to 200 characters, starting with a letter or a digit
		const q = query.replace( /^[^\p{L}\p{N}]+/u, '' ).slice( 0, 200 )
		if ( q.length < 3 ) {
			return Promise.resolve( [] )
		}
		return this.request( 'search', Object.assign( { q, limit: this.options.limit }, this.options.geocodingQueryParams ) )
	}

	suggest( query ) {
		return this.geocode( query )
	}

	/**
	 *	@param {L.LatLngLiteral} latLng
	 *	@param {number} scale Map scale as passed to the other geocoders. Its zoom level sets the detail of the label.
	 */
	reverse( latLng, scale ) {
		const zoom = Math.round( Math.log( scale / 256 ) / Math.log( 2 ) )
		return this.request( 'reverse', Object.assign( { lat: latLng.lat, lon: latLng.lng, limit: 1 }, this.options.reverseQueryParams ), zoom )
	}

	async request( endpoint, params, zoom = 18 ) {
		try {
			// serviceUrl may be relative or lack its trailing slash
			const url = new URL( endpoint, new URL( this.options.serviceUrl.replace( /\/?$/, '/' ), window.location.href ) )
			Object.entries( params ).forEach( ( [ key, value ] ) => url.searchParams.append( key, value ) )
			const response = await fetch( url.toString(), { headers: { Accept: 'application/json' } } )
			return this.parseResults( await response.json(), zoom )
		} catch ( err ) {
			// network error, rate limit (HTTP 429), …: no results instead of a pending search
			return []
		}
	}

	parseResults( data, zoom ) {
		return ( data?.features || [] ).map( feature => {
			const [ lng, lat ] = feature.geometry.coordinates
			const center = L.latLng( lat, lng )
			return {
				// text, not html: the control and the marker label show it as is
				name: ( this.options.nameTemplate && this.options.nameTemplate( feature.properties, zoom ) ) || feature.properties.label,
				center,
				bbox: center.toBounds( RESULT_SIZE[ feature.properties.type ] ?? 1000 ),
				properties: feature.properties,
			}
		} )
	}
}

const geoplateforme = options => new Geoplateforme( options )

export { Geoplateforme, geoplateforme }
