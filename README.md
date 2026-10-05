<div align="center">

<img src=".wporg/banner-1544x500.png" alt="ACF OpenStreetMap Field" width="100%">

# ACF OpenStreetMap Field

**Configurable OpenStreetMap / [Leaflet](https://leafletjs.com/) map field for [Advanced Custom Fields](https://www.advancedcustomfields.com/).**

[![Version](https://img.shields.io/github/v/tag/PaulArgoud/ACF-OpenStreetMap-Field?sort=semver&label=version&color=blue)](https://github.com/PaulArgoud/ACF-OpenStreetMap-Field/tags)
[![Tested up to](https://img.shields.io/badge/WordPress-up%20to%207.1-21759b?logo=wordpress&logoColor=white)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/php-8.0--8.5-777BB4?logo=php&logoColor=white)](composer.json)
[![ACF](https://img.shields.io/badge/ACF-5.7%2B-00a0d2)](https://www.advancedcustomfields.com/)
[![License: GPL v3](https://img.shields.io/badge/license-GPLv3-blue.svg)](LICENSE.txt)

</div>

---

Pick a tile provider, set the view, drop one or many markers — then output a ready-to-use interactive map, an OpenStreetMap.org iframe, or the raw coordinates, anywhere in your theme. No Google Maps API key, no billing account, no tracking by default.

> This is a maintained fork of [mcguffin/acf-openstreetmap-field](https://github.com/mcguffin/acf-openstreetmap-field), the original plugin by Jörn Lund.

## Table of contents
- [Features](#features)
- [Screenshots](#screenshots)
- [Requirements](#requirements)
- [Installation](#installation)
- [Usage](#usage)
- [Return formats](#return-formats)
- [Address search](#address-search)
- [Customization](#customization)
- [Map Proxy](#map-proxy)
- [Integrations](#integrations)
- [Development](#development)
- [Testing](#testing)
- [Changelog](#changelog)
- [License](#license)

## Features
- 🗺️ **Dozens of tile providers & overlays** via [Leaflet Providers](https://github.com/leaflet-extras/leaflet-providers) — choose base maps and overlays per field.
- 📍 **Single or multiple markers** — editable labels, geocoding (address search), numeric lat/lng/zoom inputs, and an optional *search-only* mode.
- 🧩 **Three return formats** — interactive Leaflet map, OpenStreetMap.org iframe, or raw data.
- 🎯 **Frontend UX** — optionally auto-fit markers in view and enable *gesture handling* so the map doesn't trap page scrolling on touch devices.
- 🎨 **Custom markers** — a no-code marker icon URL, plus full control through WordPress filters and JavaScript events.
- 🇫🇷 **Géoplateforme (IGN)** — French address search (Base Adresse Nationale, no API key) and IGN layers: Plan IGN, aerial photos, historical maps, cadastral parcels and other overlays.
- 🔒 **Map Proxy** — serve tiles from your own server to hide API keys and comply with privacy regulations such as the GDPR.
- 🔌 **Integrations** — WPGraphQL, WPML, Polylang and the ACF REST API.
- 🧱 **Block editor, widgets & frontend forms** ready, with overridable theme templates.

## Screenshots
<details>
<summary>Show screenshots</summary>

| ACF Field Group Editor | Editing the Field Value |
| :---: | :---: |
| ![Field group editor](.wporg/screenshot-1.png) | ![Editing the field value](.wporg/screenshot-2.png) |

| Display in the Frontend | Settings page |
| :---: | :---: |
| ![Frontend display](.wporg/screenshot-3.png) | ![Settings page](.wporg/screenshot-4.png) |

</details>

## Requirements
- WordPress 5.5+
- PHP 8.0 – 8.5
- [Advanced Custom Fields](https://www.advancedcustomfields.com/) 5.7+ (or ACF PRO, or Secure Custom Fields)

Tested with WordPress 7.1.2, ACF PRO 6.8.10 and Leaflet 1.9.4.

## Installation

The plugin on wordpress.org and the `mcguffin/acf-openstreetmap-field` package on Packagist are the original version, not this fork. Install this fork from GitHub:

**Upload** — download `acf-openstreetmap-field.zip` from the [latest release](https://github.com/PaulArgoud/ACF-OpenStreetMap-Field/releases/latest) and install it in WP Admin under *Plugins → Add New Plugin → Upload Plugin*.

**WP-CLI**
```shell
wp plugin install https://github.com/PaulArgoud/ACF-OpenStreetMap-Field/releases/latest/download/acf-openstreetmap-field.zip --activate
```

**Composer** — add the GitHub repository, then require the package:
```shell
composer config repositories.acf-openstreetmap-field vcs https://github.com/PaulArgoud/ACF-OpenStreetMap-Field
composer require paulargoud/acf-openstreetmap-field
```

## Usage
Add an **OpenStreetMap** field to a field group, then output it in your theme.

Leaflet and iframe return formats print a ready-made map — just echo the field:

```php
<?php the_field( 'my_map' ); ?>
```

With the **Raw data** return format you get the structured value:

```php
<?php
$map = get_field( 'my_map' );

printf( 'Center: %F, %F (zoom %d)', $map['lat'], $map['lng'], $map['zoom'] );

foreach ( $map['markers'] as $marker ) {
    printf( '%s — %F, %F', esc_html( $marker['label'] ), $marker['lat'], $marker['lng'] );
}
```

More developer-centric documentation lives in the [wiki of the original project](https://github.com/mcguffin/acf-openstreetmap-field/wiki).

## Return formats
| Format | `get_field()` returns | Notes |
| --- | --- | --- |
| **Leaflet JS** | Interactive map markup | Many map styles, multiple markers, overlays |
| **iFrame (OpenStreetMap.org)** | `<iframe>` markup | Four styles, one marker |
| **Raw data** | `array` | `lat`, `lng`, `zoom`, `layers`, `markers`, `address` |

## Address search
Search an address to add a marker; new and dragged markers get their address automatically. Choose the geocoder under *Settings › OpenStreetMap*:

| Geocoder | Coverage | API key |
| --- | --- | --- |
| **Nominatim** (default) | Worldwide, OpenStreetMap | No |
| **Photon** | Worldwide, OpenStreetMap | Optional |
| **OpenCage** | Worldwide | Required |
| **Géoplateforme (IGN, France)** | French addresses (Base Adresse Nationale), mainland and overseas | No |

The *Reverse Geocoder Detail Level* setting sets how detailed the automatic marker labels are (house number, street or town). Label formats can be changed with the `acf_osm_address_format` filter.

The *Geoportail France* tile provider adds the matching IGN maps from the [Géoplateforme](https://cartes.gouv.fr/): Plan IGN, aerial photos (also infrared and 1950–1965), the État-major and Cassini historical maps, and overlays for cadastral parcels, administrative limits, roads, railways, hydrography and contour lines.

## Customization
**Theme templates** — the map markup comes from templates you can override in your theme (`osm-maps/leaflet.php`, `osm-maps/osm.php`). An override must escape everything it prints: the field tells ACF that it escapes its own output.

**Custom marker icon (PHP)** — return some HTML to render a `divIcon`:

```php
add_filter( 'acf_osm_marker_html', function () {
    return '<span class="my-marker"></span>';
} );
```

**Per-marker tweaks (JavaScript)** — adjust each marker as it is created:

```js
document.addEventListener( 'acf-osm-map-marker-create', ( e ) => {
    const { markerOptions, L } = e.detail;
    markerOptions.icon = L.icon( { iconUrl: '/path/to/icon.png', iconSize: [ 32, 32 ] } );
} );
```

See the [HTML Marker Icon](https://github.com/mcguffin/acf-openstreetmap-field/wiki/HTML-Marker-Icon) page in the original project's wiki for the full list of filters and JS events.

## Map Proxy
Load tiles through your own server to hide credentials and avoid sending visitor data to third-party tile servers. Enable it per provider under *Settings › OpenStreetMap*.

The proxy lives in `wp-content/maps/`. Apache and LiteSpeed use the `.htaccess` file the plugin writes there. On Nginx, add this block next to WordPress' usual `location ~ \.php$` block (without `^~`):

```nginx
location /wp-content/maps/ {
    rewrite ^ /wp-content/maps/index.php last;
}
```

The proxy configuration holds the access tokens of the proxied providers. It is stored in `wp-content/acf-osm-proxy-config.php`, so the web server must run PHP files in `wp-content/`: the plugin checks it when the settings are saved.

> [!IMPORTANT]
> **Upgrading from 1.7.1 or 1.7.2:** these versions wrote every saved access token to a file anyone could download (`wp-content/uploads/acf-osm-proxy-config.json`), even with the proxy off. The update deletes it; renew those tokens anyway. On Nginx, replace the rule they suggested (`location ^~ /wp-content/maps/ { try_files … }`) with the one above.

More details in [The Map Proxy](https://github.com/mcguffin/acf-openstreetmap-field/wiki/The-Map-Proxy) page of the original project's wiki.

## Integrations
- **WPGraphQL** — exposes a structured `AcfOpenStreetMap` type (requires [WPGraphQL](https://www.wpgraphql.com/) and [WPGraphQL for ACF](https://acf.wpgraphql.com/)).
- **WPML / Polylang** — map values are copied / synced across translations instead of being treated as translatable strings.
- **ACF REST API** — field values are available through the WordPress REST API, and can be written back in the same shape.
- **Géoplateforme (IGN)** — French address search and IGN maps, see [Address search](#address-search).

## Development
```shell
git clone https://github.com/PaulArgoud/ACF-OpenStreetMap-Field.git acf-openstreetmap-field
cd acf-openstreetmap-field
npm install
npm run dev
```

Useful npm scripts:

| Script | Description |
| --- | --- |
| `npm run dev` | Watch and rebuild CSS & JS sources |
| `npm run build` | Build CSS & JS for production |
| `npm run dev-test` | Create test fields in wp-admin and watch sources |
| `npm run uitest` | Create test fields in wp-admin |
| `npm run audit` | Run the phpcs audit |
| `npm run providers` | Report tile-provider drift vs upstream `leaflet-providers` |
| `npm run i18n` | Generate the `.pot` file |
| `npm run test` | Run unit tests against PHP 8.0 and 8.5 |

The built files in `assets/` are committed, and CI fails if a fresh build differs from them. Rebuild with the commands CI uses (`npm run build` adds source maps, which CI doesn't):
```shell
npx sass ./src/scss/:./assets/css/ --load-path=node_modules/ --style=compressed --no-source-map
npx webpack build --output-path ./assets/js --config ./webpack.config.js --mode production
```

## Testing
**In WP-Admin** — add the field to several places for manual testing:
```shell
npm run dev-test
```

**Unit tests** run in [@wordpress/env](https://www.npmjs.com/package/@wordpress/env) (a Docker container, so [Docker Desktop](https://docs.docker.com/desktop/) is required) with WordPress 7.1.2 and Secure Custom Fields, against PHP 8.0 (legacy) and 8.5 (edge):
```shell
npm run test            # both
npm run test:edge       # PHP 8.5 only
npm run test:legacy     # PHP 8.0 only
```

Help is welcome with unit tests covering all PHP code and with unit-testing the JS.

## Changelog
See [CHANGELOG.md](CHANGELOG.md) for the full release history.

## License
[GPLv3 or later](LICENSE.txt) &copy; the plugin contributors. Originally created by [Jörn Lund](https://github.com/mcguffin) ([mcguffin/acf-openstreetmap-field](https://github.com/mcguffin/acf-openstreetmap-field)).