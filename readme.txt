=== ACF OpenStreetMap Field ===
Contributors: podpirate
Donate link: https://donate.openstreetmap.org/
Tags: map acf openstreetmap leaflet
Requires at least: 5.5
Requires PHP: 8.0
Tested up to: 7.1
Stable tag: 1.7.4
License: GPLv3 or later
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Configurable OpenStreetMap / Leaflet map field for ACF — markers, many tile providers, address geocoding and a privacy-friendly map proxy.

== Description ==

Hassle-free OpenStreetMap maps for [ACF](https://www.advancedcustomfields.com/).

## Usage

#### In the Fieldgroup editor:

**Return Format:**

 - *Raw data* will return an array holding the field configuration.

 - *Leaflet JS* will return a fully functional leaflet map. Just include `<?php the_field('my_field_name'); ?>` in your Theme.
You can choose from a long list of map styles and it supports multiple markers.

 - *iFrame (OpenStreetMap.org)* Will return an iFrame HTML. Only four map styles are supported
– the ones you find on [OpenStreetMap](https://www.openstreetmap.org/) – and not more than one marker.

**Map Appearance:** Pan and zoom on the map and select from the Map layers to set the initial map position and style in the editor.

**Map Position:** If you're more like a numbers person here you can enter numeric values for the map position.

**Allow layer selection:** Allow the editors to select which map layers to show up in the frontend.

**Height:** Map height in the frontend and editor.

**Max. number of Markers**
 - *No value:* infinite markers
 - *0:* No markers
 - *Any other value:* Maximum number of markers. If the return format is *iFrame* there can only be one marker.

**Theme templates:** the map markup comes from templates you can override in your theme (`osm-maps/leaflet.php`, `osm-maps/osm.php`). An override must escape everything it prints: the field tells ACF that it escapes its own output. Escape attributes with `acf_osm_esc_attrs()`, not `acf_esc_attr()`: it keeps HTML entities in marker labels from turning into HTML, and works without ACF (see Modern Fields below).

## Address search

Search an address to add a marker; new and dragged markers get their address automatically. Choose the geocoder under *Settings › OpenStreetMap*:

 - *Nominatim* (default): OpenStreetMap's geocoder, worldwide.
 - *Photon*: worldwide, OpenStreetMap data.
 - *OpenCage*: worldwide, needs an API key.
 - *Géoplateforme (IGN, France)*: French addresses from the Base Adresse Nationale, mainland and overseas, no API key.

The *Reverse Geocoder Detail Level* setting sets how detailed the automatic marker labels are (house number, street or town).

## French maps (Géoplateforme)

The *Geoportail France* provider offers IGN maps from the [Géoplateforme](https://cartes.gouv.fr/), without API key: Plan IGN, aerial photos (also infrared and 1950–1965), the État-major and Cassini historical maps, and overlays for cadastral parcels, administrative limits, roads, railways, hydrography and contour lines.

## Modern Fields

With [Modern Fields](https://modern-fields.com) 1.5 or later:

 - *Settings › OpenStreetMap › Modern Fields* sets the layer of its Map field when it uses OpenStreetMap: a base layer enabled in this plugin, such as an IGN map once *Geoportail France* is enabled in the *Providers* tab (it is off by default). Layers with 512px tiles (MapBox, MapTiler) are left out. A provider with the map proxy enabled keeps its access key hidden. The Modern Fields map zooms up to 19, so a layer with a smaller zoom range stays empty beyond it. A `MODERN_FIELDS_OSM_TILE_URL` constant takes precedence.
 - Show a location on the front end with this plugin's map (Modern Fields itself only links to one): `<?php acf_osm_the_map( get_field( 'my_location' ) ); ?>`

`acf_osm_the_map()` and `acf_osm_get_map()` also take an ACF Google Map value or the raw value of an OpenStreetMap field (*Raw* return format, or `get_field( 'my_map', false, false )`), and work without ACF. Options: `acf_osm_the_map( $value, [ 'height' => 300, 'zoom' => 12, 'layers' => [ 'OpenTopoMap' ], 'marker' => false, 'template' => 'osm' ] )`.

## Map Proxy
The plugin comes with a proxy mechanism for map tiles. If enabled the browser loads the tiles from your server rather than directly from the tile provider.

Use the proxy to hide sensitive credentials in the tile URL or for compliance with local privacy regulations like the European GDPR.

The proxy lives in `wp-content/maps/`. Apache and LiteSpeed use the `.htaccess` file the plugin writes there. On Nginx, add this block to the server configuration, next to WordPress' usual `location ~ \.php$` block (the settings page shows it too; don't add `^~`):

    location /wp-content/maps/ {
        rewrite ^ /wp-content/maps/index.php last;
    }

The proxy configuration holds the access tokens of the proxied providers. It is stored in `wp-content/acf-osm-proxy-config.php`, so your web server must run PHP files in `wp-content/`: when you save the settings, the plugin checks that it does.

Find more details in the [wiki of the original project](https://github.com/mcguffin/acf-openstreetmap-field/wiki/The-Map-Proxy).

## Development

Please head over to the source code [on GitHub](https://github.com/PaulArgoud/ACF-OpenStreetMap-Field).

## Credits
- [Jörn Lund](https://github.com/mcguffin), who created the [original plugin](https://github.com/mcguffin/acf-openstreetmap-field) this fork is based on
- [ACF](https://www.advancedcustomfields.com/) for sure!
- The [IGN](https://www.ign.fr/) and its [Géoplateforme](https://cartes.gouv.fr/) for the French maps and address search
- The [OpenStreetMap](https://www.openstreetmap.org/) project
- [The Leaflet Project](https://leafletjs.com/)
- The maintainers and [contributors](https://github.com/leaflet-extras/leaflet-providers/graphs/contributors) of [Leaflet providers](https://github.com/leaflet-extras/leaflet-providers)
- The [very same](https://github.com/perliedman/leaflet-control-geocoder/graphs/contributors) for [Leaflet Control Geocode](https://github.com/perliedman/leaflet-control-geocoder)
- [Dominik Moritz](https://www.domoritz.de/) who delighted us with [Leaflet locate control](https://github.com/domoritz/leaflet-locatecontrol)
- Numerous individuals and organizations who provide wonderful Map related services free of charge. (You are credited in the map, I hope)
- The proxy feature was inspired by an article by Klaus Meffert, Dr. DSGVO Blog, [Link (German)](https://dr-dsgvo.de/datenschutzfreundliches-karten-plugin-fur-webseiten-statt-google-maps-neue-moglichkeiten)

== Installation ==

This fork is distributed on GitHub only; the plugin listed on wordpress.org is the original version.

Download `acf-openstreetmap-field.zip` from the [latest release](https://github.com/PaulArgoud/ACF-OpenStreetMap-Field/releases/latest) and install it in WP Admin under *Plugins → Add New Plugin → Upload Plugin*.


== Frequently asked questions ==

= I found a bug. Where should I post it? =

Please use the issues section in the [GitHub-Repository](https://github.com/PaulArgoud/ACF-OpenStreetMap-Field/issues).

This fork has no support forum on wordpress.org: the forum there belongs to the original plugin.

= I'd like to suggest a feature. Where should I post it? =

Please post an issue in the [GitHub-Repository](https://github.com/PaulArgoud/ACF-OpenStreetMap-Field/issues)

= I am a map tile provider. Please don't include our service in your plugin. =

The providers list is taken from [Leaflet providers](https://github.com/leaflet-extras/leaflet-providers), so requests for an unlisting should go there first.

If you want your service to remain in Leaflet Providers, you can post an issue in the plugin's [GitHub-Repository](https://github.com/PaulArgoud/ACF-OpenStreetMap-Field/issues).
Please provide me some way for me to verify, that you are acting on behalf of the Tile service provider your want to exclude.
(E.g. the providers website has a link to your github account.)

= I'm getting these "Insecure Content" warnings =

Some providers do not support https. If these warnings bother you, choose a different one or use the proxy feature.

= Why isn't the map loading? =

There is very likely an issue with the map tiles provider you've chosen. Some of them might have gone offline or have suspended their service. Choose another one.

= I used version 1.7.1 or 1.7.2. What should I do? =

Update the plugin, then renew every access token saved under *Settings › OpenStreetMap*, even if you never enabled the map proxy: these versions wrote them to a file anyone could download (`wp-content/uploads/acf-osm-proxy-config.json`). The update deletes that file; copies may remain in backups and caches. On Nginx, also replace the rule these versions suggested (`location ^~ /wp-content/maps/ { try_files … }`) with the one under *Map Proxy*.

= Proxied map tiles don't load on Nginx =

Nginx needs the `location` block shown under *Map Proxy*, without `^~`.

= I need to do some fancy JS magic with my map. =

Check out the [wiki of the original project](https://github.com/mcguffin/acf-openstreetmap-field/wiki). Some of the js events might come in handy for you.
For Documentation of the map object, please refer to [LeafletJS](https://leafletjs.com).

= Will you answer support requests via email? =

No.


== Screenshots ==

1. ACF Field Group Editor
2. Editing the Field Value
3. Display in the Frontend
4. Settings page. Configure API access keys and disable specific tile layers.

== Upgrade Notice ==

= 1.7.4 =
Security: fixes script injection through marker labels in front-end maps. If your theme overrides osm-maps/leaflet.php, replace acf_esc_attr() with acf_osm_esc_attrs() in it.

= 1.7.3 =
Security: if the site ran 1.7.1 or 1.7.2, renew every access token saved in the plugin settings, even without the map proxy. On Nginx, replace the old map proxy rule (see FAQ).

= 1.7.1 =
If you use the map proxy, its configuration is migrated automatically from a generated PHP file to JSON on upgrade — no action required.

= 1.7.0 =
This release requires PHP 8.0+ and WordPress 5.5+ (support for older versions has been dropped). The WP-CLI proxy commands moved from `wp map-proxy ...` to `wp acf-osm map-proxy ...`.

= 1.5.0 =
**Attention:** Version 1.5.0 may involve some breaking changes.

The global Leaflet object is no longer available.


== Changelog ==

The changelog has moved to [CHANGELOG.md](https://github.com/PaulArgoud/ACF-OpenStreetMap-Field/blob/master/CHANGELOG.md).
