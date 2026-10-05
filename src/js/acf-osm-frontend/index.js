import {} from 'osm-map';

// In the block editor canvas (an iframe since WP 7.1) only this bundle runs. Leave the field editor of
// ACF blocks rendering their form there (block versions 1 and 2) alone: a plain map would look editable
// but not save anything.
document.addEventListener( 'acf-osm-map-create', e => {
	if ( e.target.matches( '[data-editor-config]' ) && 'undefined' === typeof acf_osm_admin ) {
		e.preventDefault()
	}
} )
