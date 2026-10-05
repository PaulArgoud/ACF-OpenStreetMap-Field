<?php

namespace ACFFieldOpenstreetmap\Helper;

class HtmlHelper {

	/**
	 *	HTML attributes, escaped, like acf_esc_attrs() but without needing ACF:
	 *	the map templates also render values of other plugins (see acf_osm_get_map()).
	 *
	 *	Arrays and objects are JSON encoded with < > & ' " as unicode escapes: esc_attr() doesn't encode
	 *	an entity again, so a marker label `&lt;img …&gt;` would reach the JS (and the popup) as `<img …>`.
	 *
	 *	@param array $attrs [ name => string|number|bool|array|object ]
	 *	@return string
	 */
	public static function esc_attrs( $attrs ) {
		$html = '';

		foreach ( (array) $attrs as $name => $value ) {
			if ( is_string( $value ) && 'value' !== $name ) {
				$value = trim( $value );
			} elseif ( is_bool( $value ) ) {
				$value = $value ? 1 : 0;
			} elseif ( is_array( $value ) || is_object( $value ) ) {
				$value = wp_json_encode( $value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT );
			}
			$html .= sprintf( ' %s="%s"', esc_attr( $name ), esc_attr( $value ) );
		}

		return trim( $html );
	}
}
