<?php

class HtmlHelperTest extends WP_UnitTestCase {

	/**
	 * @covers ACFFieldOpenstreetmap\Helper\HtmlHelper::esc_attrs
	 */
	public function test_esc_attrs() {
		$attrs = [
			'class'     => ' a b ',
			'value'     => ' v ',
			'data-on'   => true,
			'data-off'  => false,
			'data-num'  => 1.5,
			'data-json' => [ 'label' => '<b>"x"</b>' ],
			'data-x"y'  => '"><script>',
		];
		$html = acf_osm_esc_attrs( $attrs );

		$this->assertStringStartsWith( 'class="a b" value=" v " data-on="1" data-off="0" data-num="1.5" data-json="', $html );
		$this->assertStringNotContainsString( '<', $html );
		$this->assertStringNotContainsString( '"x"', $html );

		// same as ACF for scalars
		if ( function_exists( 'acf_esc_attrs' ) ) {
			unset( $attrs['data-json'] );
			$this->assertSame( acf_esc_attrs( $attrs ), acf_osm_esc_attrs( $attrs ) );
		}
	}

	/**
	 * esc_attr() doesn't encode an entity again: the JSON must not contain any
	 *
	 * @covers ACFFieldOpenstreetmap\Helper\HtmlHelper::esc_attrs
	 */
	public function test_esc_attrs_entities() {
		$label = '&lt;img src=x onerror=alert(1)&gt; &amp; &#039;';
		$html  = acf_osm_esc_attrs( [ 'data-markers' => [ [ 'label' => $label ] ] ] );

		$this->assertStringNotContainsString( '&lt;', $html );
		// what the browser hands to the JS
		preg_match( '/data-markers="([^"]*)"/', $html, $match );
		$markers = json_decode( wp_specialchars_decode( $match[1], ENT_QUOTES ), true );
		$this->assertSame( $label, $markers[0]['label'] );
	}
}
