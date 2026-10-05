# Changelog

All notable changes to the [ACF OpenStreetMap Field](https://github.com/PaulArgoud/ACF-OpenStreetMap-Field) plugin are documented in this file.

This project is a fork of [mcguffin/acf-openstreetmap-field](https://github.com/mcguffin/acf-openstreetmap-field). Issue and pull request links (`#…`) of versions up to 1.7.0 point to that original repository.

## 1.7.3
 - Security: In 1.7.1 – 1.7.2 the map proxy configuration, with every access token saved in the plugin's provider settings, could be downloaded by anyone: any change to these settings (or `wp acf-osm map-proxy configure`) wrote it to `wp-content/uploads/acf-osm-proxy-config.json` (multisite: also `wp-content/uploads/sites/<id>/…`), even with no provider proxied, and the proxy relayed every provider with a token. The configuration now lives in `wp-content/acf-osm-proxy-config.php`, a PHP file that stops when requested over HTTP, and only holds the proxied providers; when the site can reach itself, saving the settings first checks that such a file can't be downloaded. The public files are deleted on update. **If the site ran 1.7.1 or 1.7.2, renew every access token saved in these settings, even if you never enabled the proxy** — copies may remain in backups and caches. On Nginx, replace the rule suggested by 1.7.1 – 1.7.2 (`location ^~ /wp-content/maps/ { try_files … }`): it never ran the proxy and serves that directory as plain files. The settings page shows the new rule.
 - Security: The provider settings only accept access tokens (a site administrator could make the proxy fetch any URL), the proxy only fetches http(s) URLs and serves anything but images as a download, and a failing tile request no longer exposes the upstream URL with its access token in a PHP warning.
 - Feature: Géoplateforme (IGN) address search — a new geocoder for French addresses (Base Adresse Nationale, mainland and overseas), without API key. Choose it under Settings › OpenStreetMap › Geocoder; it handles the address search, its suggestions and the address of new or dragged markers, and follows the “Reverse Geocoder Detail Level” setting.
 - Feature: More IGN Géoplateforme layers in the Geoportail France provider: infrared and 1950–1965 aerial photos, the État-major (1820–1866) and Cassini maps as base layers, and administrative limits, roads, railways, hydrography and contour lines as overlays.
 - Fix: The map proxy passes on the tile server's status (a 403 was answered with an empty 200) and accepts subdomain lists.
 - Fix: Geoportail France — cadastral parcels are now an overlay (selecting them used to replace the base map), they no longer request missing tiles at zoom 20, Plan IGN goes up to zoom 19, and the layers credit IGN, as the Etalab open licence requires.
 - Fix: Overlays are always drawn above the base layer, whatever order the layers were saved in (they could end up hidden under it on the front end), and the field editor no longer zooms beyond the layers' common maximum (the base map disappeared there).
 - Fix: Block editor — the post no longer stays “dirty” (unsaved-changes warning, `_acf_changed` edit) right after saving, and no longer becomes dirty just by loading the editor or revealing a hidden map. Layout changes (a scrollbar briefly resizing the meta box pane during save, a map shown from a hidden tab, …) re-center Leaflet maps to whole pixels, and the field wrote that sub-pixel drift back as a new map center. The editor now ignores center moves below 1.5px and only fires `change` on the field input when its value actually changed.
 - Fix: The numeric latitude / longitude inputs now store the typed coordinates exactly, instead of the nearest position the map can center on.
 - Fix: Settings › OpenStreetMap stopped with a fatal error (call to the non-existent `esc_html_ex()`) before its Save button, so no provider, access token or geocoder setting could be saved ([#7](https://github.com/PaulArgoud/ACF-OpenStreetMap-Field/pull/7), reported by [@Jonas-Schwarz](https://github.com/Jonas-Schwarz)). The Photon API key input also posted into the OpenCage key.
 - Fix: ACF blocks — posts with an ACF block containing a required OpenStreetMap field no longer fail to open in the block editor (HTTP 500 in `validate_value()`), and marker labels containing quotes are no longer lost when saved from an ACF block or through the REST API.
 - Fix: The iFrame return format no longer loses its map when printed with `the_field()`, `the_sub_field()` or the `[acf]` shortcode (ACF 6.2.5+ HTML escaping removed the `<iframe>`). The field now declares that it escapes its own output (the Raw format is escaped when ACF asks for escaped values); theme overrides of the `osm-maps/*.php` templates must escape theirs, as the bundled templates do.
 - Fix: Field group editor — without a Thunderforest API key the map preview threw “No such provider (Thunderforest)”, which disconnected it from the center, zoom and layer settings and interrupted ACF's initialization of the screen. Changing a field's type to OpenStreetMap or duplicating an OpenStreetMap field now sets up its preview map.
 - Fix: New and dragged markers get an address label again: the reverse geocoding request was sent with `zoom=NaN`, which Nominatim rejects.
 - Fix: WordPress 7.1 block editor — maps in ACF block previews now render in the editor canvas (always an iframe since WordPress 7.1); maps in meta boxes follow the size of their container (no more off-center view on load or grey strip after closing the settings sidebar); the editor no longer warns that the plugin's stylesheet “was added to the iframe incorrectly”; and the map can't be edited while the meta boxes are being saved.
 - Fix: Users without the `unfiltered_html` capability no longer wipe the map on save when a marker label contains a link: the field value no longer contains characters that WordPress' HTML filter rewrites.
 - Fix: REST API — the field schema now describes the object the API returns, so a value read from the API can be written back.
 - Fix: Duplicating a repeater row or flexible content layout no longer leaves a second, inactive set of latitude / longitude / zoom inputs, and the copy keeps its map editor.
 - Fix: Since 1.7.2, `acf_osm_leaflet_providers` filters from themes and later-loading plugins, and the map proxy, were ignored in wp-admin: the provider list was cached before they were registered.
 - Fix: Switching layers in the field group preview no longer removes the tile attribution (Leaflet 1.8+), and one tap on the address search button on a touch device sends one search request instead of two.
 - Fix: Saving the plugin options outside wp-admin (cron, REST, …) no longer causes a fatal error, the map proxy no longer uses `$http_response_header` (deprecated in PHP 8.5), and the settings page's geocoder preview no longer requires a 2025 browser.
 - Fix: Settings page — the “Test” preview of a layer with a minimum zoom (État-major, Cassini, …) no longer ends on a blank map.
 - Changed: The field type is listed under “Advanced” in the field type browser of ACF 6.1+ (it was in a stray “jquery” group).
 - Changed: Tested with WordPress 7.1.2 (jQuery 3.7.1 with jQuery Migrate, Backbone 1.6.1, Underscore 1.13.8), ACF PRO 6.8.10 and Leaflet 1.9.4. The 1.7.0 notes wrongly mentioned a bundled jQuery 4.0: WordPress 7.0 and 7.1 ship jQuery 3.7.1. CI now runs the tests against WordPress 7.1.2.
 - Changed: Repository, issue-tracker, changelog and Composer metadata now point to this fork ([PaulArgoud/ACF-OpenStreetMap-Field](https://github.com/PaulArgoud/ACF-OpenStreetMap-Field)); the Composer package is renamed to `paulargoud/acf-openstreetmap-field`.
 - Changed: The plugin header now declares an `Update URI`, so WordPress never offers the original plugin from wordpress.org (same `acf-openstreetmap-field` slug) as an update that would overwrite this fork.
 - Changed: Map proxy upkeep — when the settings page opens, a missing proxy configuration is rebuilt (or the reason it can't be is shown); deleting a site deletes its configuration; uninstalling removes every proxy file; `wp acf-osm map-proxy configure` and `install` finish a pending update first; and an update that can't delete the old public files says so in wp-admin.
 - Dev: Removed debug console output from the admin scripts (it printed the geocoder API key). CI now also fails when a build output is missing from the repository.

## 1.7.2
 - Fix: The settings page no longer registers an invalid section callback (a leftover reference to a removed method), and two settings strings (“Disable …” / “Enable Proxy for …”) used a misspelled text domain so they were never translatable — both corrected.
 - Performance: Leaflet core is now a single shared script cached across the admin and the frontend, instead of being inlined into all four bundles. Each context loads a small entry chunk plus the cached `acf-osm-leaflet` script.
 - Performance: The tile-provider catalogue is now memoized per request (and invalidated when the relevant settings change), so the repeated provider/layer lookups during asset registration no longer re-filter the full list each time; `has_access_token()` no longer re-reads the tokens option once per provider.
 - Dev: Refactored the OpenStreetMap field class — the value sanitization moved to a decoupled, unit-tested `Field\MapValue` class and the field-editor settings UI to a `Field\Traits\FieldSettings` trait (the field class went from ~860 to ~510 lines).
 - Dev: De-duplicated the map-proxy tileset config builder (`MapProxy::build_tileset_config`).
 - Dev: De-duplicated the access-token placeholder detection into `LeafletProviders::is_token_placeholder()` and removed dead code in `get_layers()`.
 - Dev: CI maintenance — `composer install` now retries to ride out transient Packagist outages, and the `actions/checkout` / `actions/setup-node` steps were bumped to v5 (Node 24 runtime).

## 1.7.1
 - Feature: Nginx support hint — when the map proxy is enabled on a server that doesn't use `.htaccess` (Nginx, …), the settings page shows the `location` block to add so proxied tiles are served.
 - Changed: The map proxy configuration is now stored as JSON (`acf-osm-proxy-config.json`) instead of a generated PHP file. Existing installs are migrated automatically on upgrade (the proxy `index.php` reads either format).
 - Changed: Added `uninstall.php` — deleting the plugin now removes its options and the generated `wp-content/maps/` proxy directory (multisite-aware).
 - Fix: The map proxy now uses a request timeout (no more hanging on a slow tile server) and no longer forwards the visitor's `Referer` header to the upstream tile server (privacy).
 - Dev: Added GitHub Actions CI (phpcs + PHPUnit on PHP 8.0 & 8.5 + an asset-build check), expanded the PHPUnit suite (field value layer + OpenStreetMap iframe/link URLs), and pinned Composer's `platform.php` to 8.0 so dev dependencies resolve for the minimum supported PHP version.
 - Dev: New `npm run providers` drift report comparing `etc/leaflet-providers.json` against the upstream `leaflet-providers` package (catches discontinued providers and moved tile URLs early).

## 1.7.0
 - Feature: WPGraphQL support. The field is exposed as a structured `AcfOpenStreetMap` GraphQL type (center, zoom, layers and markers) when [WPGraphQL](https://www.wpgraphql.com/) and [WPGraphQL for ACF](https://acf.wpgraphql.com/) are active ([#137](https://github.com/mcguffin/acf-openstreetmap-field/issues/137), thanks to [@agenceKanvas](https://github.com/agenceKanvas)).
 - Feature: New "Fit markers in view" field setting. When enabled, the frontend map automatically zooms and centers to fit all of its markers ([#135](https://github.com/mcguffin/acf-openstreetmap-field/issues/135)).
 - Feature: New "Custom marker icon URL" field setting to use a per-field marker image on the frontend, without code ([#135](https://github.com/mcguffin/acf-openstreetmap-field/issues/135), idea from [@nexiumbiz-debug](https://github.com/nexiumbiz-debug) — [#139](https://github.com/mcguffin/acf-openstreetmap-field/pull/139)).
 - Feature: New `acf_osm_address_format` filter to override the address formats used for marker labels (street / city / country) ([#128](https://github.com/mcguffin/acf-openstreetmap-field/issues/128), thanks to [@Cyrille37](https://github.com/Cyrille37) — [#129](https://github.com/mcguffin/acf-openstreetmap-field/pull/129)).
 - Feature: New "Add markers via search only" field setting that disables manual marker placement (double-click / tap-and-hold) and dragging, leaving the address search as the only way to add markers ([#91](https://github.com/mcguffin/acf-openstreetmap-field/issues/91)).
 - Feature: New "Gesture handling" field setting (opt-in) that requires ctrl/⌘ + scroll to zoom and two fingers to move the frontend map, so it no longer traps page scrolling on touch devices ([#70](https://github.com/mcguffin/acf-openstreetmap-field/issues/70)).
 - Feature: Numeric latitude / longitude / zoom inputs in the field editor, for the map position and for each marker — kept in sync with the map two ways ([#29](https://github.com/mcguffin/acf-openstreetmap-field/issues/29)).
 - Changed: WP-CLI commands are now namespaced under `wp acf-osm map-proxy …` (was `wp map-proxy …`) and gained a `status` subcommand (with `--format`). `uninstall` now asks for confirmation (`--yes` to skip). Fixed `configure` always reporting success even when saving the config failed.
 - Changed: Raised the minimum PHP version to 8.0. The plugin now targets PHP 8.0 – 8.5; support for PHP 7.x has been dropped.
 - Changed: Raised the minimum WordPress version to 5.5 and removed the obsolete pre-5.5 rendering fallbacks. All map output now goes through the overridable templates, which also removes the duplicated markup (and a latent bug in the dead editor fallback).
 - Changed: Internal cleanup — de-duplicated the recursive array filter, removed the unused ESLint/Babel build config and dependency, removed a stray debug `console.log`, and made the class autoloader leaner (see above).
 - Changed: Removed dead editor code (an empty `getDefaultProviders()` stub and the unused `layer_is_overlay()` method with its stale Stamen patterns), declared a PSR-4 `autoload` in `composer.json`, and added unit tests for the autoloader, `LeafletProviders::get_providers()` (#133) and `MapHelper`.
 - Changed: Tested up to WordPress 7.0. Verified compatibility with the bundled jQuery 4.0 and Backbone 1.6.1 (the admin scripts use no jQuery APIs removed in 4.0).
 - Changed: Verified compatibility with Advanced Custom Fields 6.8.4 (and Secure Custom Fields). The field uses no ACF AJAX handlers, so it is unaffected by the 6.8.4 per-field-type AJAX nonce change.
 - Changed: Verified compatibility with Leaflet 1.9.4 (already bundled). Removed the obsolete map `tap` option, which Leaflet dropped together with its Tap handler in 1.8.
 - Fix: Performance — the class autoloader now skips classes outside the plugin's namespace with an in-memory string check instead of an `is_dir()` filesystem stat. It no longer stats `include/<Vendor>/` for every foreign class on the SPL autoload stack (ElasticPress, MailPoet, Cloudflare, …), which previously caused repeated failed stats on each request. It also returns quietly for a missing class instead of throwing, so `class_exists()` checks resolve to `false` rather than fataling.
 - Fix: PHP warnings/deprecations when the geocoder settings (`acf_osm_geocoder` option) have not been saved yet — undefined `scale`/`engine` keys and a null array offset — are gone; the defaults are used on a fresh install.
 - Fix: Disabling tile providers in the global settings no longer breaks fields that already use them. The selected layer of a field now keeps rendering even if its provider was disabled afterwards; the enable/disable settings only govern the layer picker. Fixes blank maps that previously required enabling every provider ([#109](https://github.com/mcguffin/acf-openstreetmap-field/issues/109), [#113](https://github.com/mcguffin/acf-openstreetmap-field/issues/113)).
 - Fix: Legacy credentials stored for a removed tile provider (e.g. the old `HERE` `app_id`/`app_key`) were injected as malformed providers and broke map rendering in the backend. Such entries are now ignored ([#133](https://github.com/mcguffin/acf-openstreetmap-field/issues/133)).
 - Fix: Leaflet JS maps not loading when a configured tile provider has been discontinued (e.g. Stamen, Thunderforest). Invalid or removed providers are now skipped gracefully instead of throwing `Invalid provider`, and the map falls back to the default OpenStreetMap layer rather than rendering a blank grey map ([#134](https://github.com/mcguffin/acf-openstreetmap-field/issues/134)).
 - Fix: Fatal error (`strlen(): Argument #1 ($string) must be of type string, array given`) when translating an ACF block containing a map field with WPML. The field is now flagged as non-translatable so WPML no longer tries to register its array value as a translatable string ([#136](https://github.com/mcguffin/acf-openstreetmap-field/issues/136)).

## 1.6.2
 - Support proxy on multisite
 - Introduce proxy WP-CLI commands
 - Fix: Content-Type HTTP-Header for some providers

## 1.6.1
 - Fix PHP fatal during upgrade

## 1.6.0
 - Introduce Map Proxy
 - Slightly improve settings page
 - Update map providers
 - Fix: _load_textdomain_just_in_time notice
 - Fix: Add marker pointer events
 - Fix: Maps in WP Admin not inited

## 1.5.7
 - Fix: Backend Map broken
 - Fix: Geocoded result not stored in raw data

## 1.5.6
 - Fix: PHP notice version_compare

## 1.5.5
 - JS: use IntersectionObserver to detect whether a map has become visible
 - Fix: ACF field not inited in Flexible Content and repeaters
 - Fix: JS recursion
 - Fix: fit bounds not working
 - Fix: marker drag not triggered
 - Fix: marker unique-IDs not always created
 - Fix: Block editor issues

## 1.5.4
 - Fix: JS ReferenceError on move marker with max markers = 1

## 1.5.3
 - Fix: Disable provider settings not displaying

## 1.5.2
 - Fix: JS Error if some providers are disabled

## 1.5.1
 - Backend UI: Attribution below map
 - ACF Field: Introduce conditional logic
 - Fix: Some map controls not visible in Blockeditor sidebar
 - Fix: Marker instructions display
 - Providers: [Migrate Stamen to Stadia Maps](https://maps.stamen.com/stadia-partnership/)
 - Providers: Update Esri Ocean base map, OpenAIP, Opensnowmap, OpenWeathermap, OpenFireMap, NLS, OpenRailwayMap, Jawg, MapTiler, MtbMap, nlmaps
 - Providers: Remove HERE (Legacy), Hydda (service down)
 - JS: Rewritten ACF integration

## 1.5.0
 - Use Leaflet noConflict
 - Refactor JS
 - Geocoder: Address detail level is now controlled by map zoom
 - Geocoder: Provide filters for configuration overides
 - Fix: Make JS event `acf-osm-map-marker-created` bubbling
 - Fix: JS Crashes in ACF Blocks
 - Fix: Weird coordinates (worldCopyJump)

## 1.4.3
 - Fix: JS – acf hook `acf-osm/create-marker` undefined argument + not firing on geocode

## 1.4.2
 - Fix: JS Error on append repeater

## 1.4.1
 - JS: remove console.log
 - Fix: admin js broken after jquery removal

## 1.4.0
 - UI: Adapt to ACF 6 field group admin
 - JS API: do acf actions on marker events
 - JS Frontend: remove jQuery dependency
 - Data: add geocode results to raw data
 - Fix: search submit button did not submit
 - Fix: print template script only if input element is present
 - Fix: value sanitation. Shold now work with Frontend Admin for ACF

## 1.3.5
 - Fix: Admin Marker styling broken
 - Fix: PHP Fatal with suki theme
 - Fix: include leaflet control geocode assets

## 1.3.4
 - Fix: locate control API

## 1.3.3
 - Upgrade leafletjs, leaflet-control-geocoder, leaflet-providers, leaflet, leaflet.locatecontrol to latest releases
 - Remove HikeBike map provider
 - Support ACF Rest API integration (since ACF 5.11)
 - Fix: PHP 8 compatibility
 - Fix: iframes in block preview not editable
 - Fix: quote missing on html attribute in osm template
 - Test with WP 6.0

## 1.3.2
 - Fix: No such variant of OpenStreetMap (Mapnik)
 - Fix: Popups not opening in Safari
 - Quick and dirty Fix: invalid (localized) lat/lng object.

## 1.3.1
 - Fix: JS Event acf-osm-map-marker-create not applying marker options

## 1.3.0
 - Theme Overrides: Override map output in your theme
 - Breaking Change: Use native JS Events
 - Breaking Change: `osm_map_iframe_template` filter gone in WP 5.5
 - Fix: jQuery 3.x (WP 5.6) compatibility
 - Fix: Map not showing on login form
 - Fix: Providers not loaded if webroot owner is not www-user
 - Upgrade: Leaflet 1.7.1
 - Upgrade: Leaflet Providers 1.11.0
 - Upgrade: Leaflet Control Geocoder 2.1.0

## 1.2.2
 - Fix: Duplicated Row (ACF 5.9+)

## 1.2.1
 - Upgrade FreeMapSK, CyclOSM

## 1.2.0
 - Feature: Settings page allowing you to disable specific map tile providersw
 - Feature: Fit markers in view (backend)
 - Upgrade: leaflet-providers, leaflet-control-geocoder, leaflet.locatecontrol

## 1.1.9
 - UI: Add Settings link on plugins list table
 - Fix: hide map provider with unconfigured api key from layer selection
 - Upgrade: leaflet-control-geocoder, leaflet.locatecontrol, leaflet-providers
 - Security hardening

## 1.1.8
 - Feature: make marker address formats localizable.
 - JS: pass map init object along with acf-os-map-create event
 - UI: hide add marker at my location button if markers cant be added

## 1.1.7
 - Feature: Add locate me button to backend
 - Fix: Geocoder search result still visible after marker added to map.
 - Fix: Required field and max_markers = 0 never saved
 - Fix: HERE app code not included in api requests

## 1.1.6
 - Feature: Observe DOM for newly added maps
 - Feature: allow manipulation of layer config in JS
 - Fix: JS event 'acf-osm-map-marker-create' not triggered

## 1.1.5
 - JS: added event Listener for ajax-loaded maps. Use `$(my_map_div).trigger('acf-osm-map-added');` on each newly added map.
 - Upgrade LeafletJS to 1.6.0

## 1.1.4
 - Upgrade Leaflet Providers to 1.9.0
 - Upgrade Leaflet Control Geocode to 1.10.0
 - Fix: Redraw maps when they become visible

## 1.1.3
 - UI: Better formatting for automatic marker labels
 - Fix: Map controls zindex in Block-Editor
 - Fix: Adding markers not working on mobile devices

## 1.1.2
 - Fix: PHP Strict Standards message

## 1.1.1
 - Fix: Required Field behaviour – "required" means now "must hava a marker"

## 1.1.0
 - UI: Usability Improvements
 - Tested: Verfied Compatibility with Widgets, Block-Editor, Frontend Form
 - Stored data pretty much like google map field
 - Code: Refactored JS

## 1.0.1
 - Convert Values from ACF Googlemaps-Field

## 1.0.0
 - Initial Release
