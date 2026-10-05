/**
 *
 */

import { L } from 'osm-map';
import { geoplateforme } from 'leaflet/geocoder-geoplateforme';

const { options, i18n } = acf_osm_admin

class GeocoderFactory {

    static createGeocoder(options) {

        /**
         * Do not forget to sync those values with \ACFFieldOpenstreetmap\Core\Core:GEOCODERS
         */
        switch (options.geocoder_name) {
            case 'nominatim':
                return GeocoderFactory.useNominatim(options.geocoder_options);
            case 'photon':
                return GeocoderFactory.usePhoton(options.geocoder_options);
            case 'opencage':
                return GeocoderFactory.useOpenCage(options.geocoder_options);
            case 'geoplateforme':
                return GeocoderFactory.useGeoplateforme(options.geocoder_options);
            //case 'openrouteservice':
            //    return GeocoderFactory.useOpenrouteservice(options);
        }
        return null;
    }

    /**
     * Label from address parts, in the formats of the acf_osm_address_format filter
     * @param {Object} address building, road, house_number, postcode, city, town, village, hamlet, state, country
     * @returns {String}
     */
    static formatAddress(address) {
        const templateConfig = {
                interpolate: /\{(.+?)\}/g
            },
            addr = _.defaults(address, {
                building: '',
                road: '',
                house_number: '',

                postcode: '',
                city: '',
                town: '',
                village: '',
                hamlet: '',

                state: '',
                country: '',
            });

        return [ 'street', 'city', 'country' ]
            .map(part => _.template(i18n.address_format[part], templateConfig)(addr))
            .map(el => el.replace(/\s+/g, ' ').trim())
            .filter(el => el !== '')
            .join(', ')
    }

    static useNominatim(options) {
        const nominatim_options = Object.assign({
            // geocodingQueryParams: {'accept-language':'it'},
            // reverseQueryParams: {'accept-language':'it'},
            htmlTemplate: result => GeocoderFactory.formatAddress(result.address)
        }, options);

        const gc = L.Control.Geocoder.nominatim(nominatim_options)
        return gc;

    }

    /**
     * https://www.liedman.net/leaflet-control-geocoder/docs/classes/geocoders.OpenCage.html#options
     * @param {*} options
     * @returns
     */
    static useOpenCage(options) {
        const oc_options = Object.assign({
        }, options);
        const gc = L.Control.Geocoder.opencage(oc_options);
        return gc;
    }

    static usePhoton(options) {
        const photon_options = Object.assign({
            htmlTemplate: result => GeocoderFactory.formatAddress(result.address)

        }, options);

        const gc = L.Control.Geocoder.photon(photon_options)
        return gc;
    }

    /**
     * Géoplateforme (IGN): French addresses
     * @param {*} options
     * @returns
     */
    static useGeoplateforme(options) {
        return geoplateforme(Object.assign({
            // zoom: the reverse geocoder detail level. House number from 17, street from 15, else the municipality
            nameTemplate: (properties, zoom) => GeocoderFactory.formatAddress({
                road: zoom >= 15
                    ? ( properties.street || ( [ 'street', 'locality' ].includes( properties.type ) ? properties.name : '' ) )
                    : '',
                house_number: zoom >= 17 ? ( properties.housenumber || '' ) : '',
                // a municipality with several postcodes only gets the first one (e.g. 69001 for Lyon)
                postcode: 'municipality' === properties.type ? '' : ( properties.postcode || '' ),
                city: properties.city || '',
                state: ( properties.context || '' ).split(',').pop().trim(), // région
                country: i18n.country_france,
            }),
        }, options));
    }

}

export { GeocoderFactory }
