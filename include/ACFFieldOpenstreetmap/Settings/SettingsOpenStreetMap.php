<?php

namespace ACFFieldOpenstreetmap\Settings;

use ACFFieldOpenstreetmap\Compat;
use ACFFieldOpenstreetmap\Core;
use ACFFieldOpenstreetmap\Helper;

class SettingsOpenStreetMap extends Settings {

	use Traits\UIElements;
	use Traits\ProviderSettings;
	use Traits\GeocoderSettings;
	use Traits\ModernFieldsSettings;

	private $optionset = 'acf_osm';

	/**
	 *	@inheritdoc
	 */
	protected function __construct() {

		$core = Core\Core::instance();

		// TODO: add features
		// add_option( 'acf_osm_features', [
		// 	'acf_field'      => true,
		// 	'geo_json_feed'  => false,
		// 	'geo_post_types' => [
		// 		'post' => true,  // one post = one geojson object
		// 		'page' => false,
		// 	],
		// ], '', true );

		add_option( 'acf_osm_geocoder', $this->geocoder_defaults, '', true );

		add_option( 'acf_osm_provider_tokens', [], '', false );
		// Runs while plugins load, before 'init': only build the defaults (which reads the provider
		// catalogue) when the option is missing, so nothing primes the provider cache this early.
		if ( false === get_option( 'acf_osm_providers' ) ) {
			add_option( 'acf_osm_providers', $this->get_default_option_providers(), '', false );
		}
		add_option( 'acf_osm_proxy', [], '', false );

		add_action( 'admin_menu', [ $this, 'admin_menu' ] );
		add_action( "load-settings_page_acf_osm", [ $this, 'enqueue_assets' ] );
		add_action( 'admin_notices', [ $this, 'maybe_nginx_proxy_notice' ] );

		add_filter( 'plugin_action_links_'.$core->get_wp_plugin(), [ $this, 'plugin_actions_links' ], 20, 4 );

		parent::__construct();

	}

	/**
	 *	Warn on the settings page when the map proxy is enabled but the server
	 *	does not use .htaccess (e.g. Nginx), where the proxy rewrite won't apply.
	 *
	 *	@action admin_notices
	 */
	public function maybe_nginx_proxy_notice() {

		$screen = get_current_screen();
		if ( ! $screen || 'settings_page_acf_osm' !== $screen->id ) {
			return;
		}

		$proxy_enabled = apply_filters( 'acf_osm_force_proxy', false )
			|| (bool) array_filter( (array) get_option( 'acf_osm_proxy', [] ) );
		if ( ! $proxy_enabled ) {
			return;
		}

		$software = isset( $_SERVER['SERVER_SOFTWARE'] )
			? strtolower( sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ) )
			: '';

		// Apache / LiteSpeed honour the generated .htaccess; anything else (Nginx, …) does not.
		if ( '' === $software || str_contains( $software, 'apache' ) || str_contains( $software, 'litespeed' ) ) {
			return;
		}

		// every request in /wp-content/maps/ goes to its index.php, which the site's PHP location then runs
		$snippet = "location /wp-content/maps/ {\n\trewrite ^ /wp-content/maps/index.php last;\n}";
		?>
		<div class="notice notice-warning">
			<p><strong><?php esc_html_e( 'ACF OpenStreetMap Field — Map Proxy', 'acf-openstreetmap-field' ); ?></strong></p>
			<p><?php esc_html_e( 'The map proxy relies on an .htaccess rewrite, which your web server (Nginx or similar) does not use. Add the following to your server configuration so proxied tiles are served:', 'acf-openstreetmap-field' ); ?></p>
			<pre><code><?php echo esc_html( $snippet ); ?></code></pre>
			<p><?php esc_html_e( 'It relies on the usual PHP location of WordPress (location ~ \.php$). Do not add “^~” to it, and replace the rule suggested by versions 1.7.1 and 1.7.2 (location ^~ /wp-content/maps/ { try_files … }): it serves the directory as static files.', 'acf-openstreetmap-field' ); ?></p>
		</div>
		<?php
	}

	/**
	 *	@filter plugin_action_links_{$plugin_file}
	 */
	public function plugin_actions_links( $actions, $plugin_file, $plugin_data, $context ) {
		if ( isset( $plugin_data['Name'] ) && current_user_can( 'manage_options' ) ) {
			$actions['settings'] = sprintf(
				'<a href="%s" aria-label="%s">%s</a>',
				esc_url(
					add_query_arg(
						[ 'page' => $this->optionset ],
						admin_url( 'options-general.php' )
					)
				),
				/* translators: %s: Plugin name. */
				esc_attr( sprintf( _x( '%s Settings', 'plugin', 'acf-openstreetmap-field' ), $plugin_data['Name'] ) ),
				__( 'Settings', 'acf-openstreetmap-field' )
			);
		}
		return $actions;
	}

	/**
	 *	Add Settings page
	 *
	 *	@action admin_menu
	 */
	public function admin_menu() {
		$page_hook = add_options_page( __('OpenStreetMap Settings' , 'acf-openstreetmap-field' ),__('OpenStreetMap' , 'acf-openstreetmap-field'),'manage_options', $this->optionset, [ $this, 'settings_page' ] );
	}

	/**
	 *	Render Settings page
	 */
	public function settings_page() {

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.' ) );
		}

		$core = Core\Core::instance();
		$providers = Core\LeafletProviders::instance();

		?>
		<div class="wrap acf-osm-settings-wrap">
			<h2><?php esc_html_e('ACF OpenStreetMap', 'acf-openstreetmap-field') ?></h2>

			<form action="options.php" method="post">
				<?php settings_fields( $this->optionset ); ?>
				<?php /*
				<input class="screen-reader-text" id="acf-osm-tab-1" type="radio" name="acf_osm_view" checked />
				*/ ?>
				<input class="screen-reader-text" id="acf-osm-tab-2" type="radio" name="acf_osm_view" checked />
				<input class="screen-reader-text" id="acf-osm-tab-3" type="radio" name="acf_osm_view" />
				<?php if ( Compat\ModernFields::is_active() ) { ?>
					<input class="screen-reader-text" id="acf-osm-tab-4" type="radio" name="acf_osm_view" />
				<?php } ?>
				<h2 class="nav-tab-wrapper">
					<?php /*
					<label for="acf-osm-tab-1" class="nav-tab">
						<?php esc_html_e('Options', 'acf-openstreetmap-field') ?>
					</label>
					*/ ?>
					<label for="acf-osm-tab-2" type="button" class="nav-tab">
						<?php esc_html_e('Providers', 'acf-openstreetmap-field') ?>
					</label>
					<label for="acf-osm-tab-3" type="button" class="nav-tab">
						<?php esc_html_e('Geocoder', 'acf-openstreetmap-field') ?>
					</label>
					<?php if ( Compat\ModernFields::is_active() ) { ?>
						<label for="acf-osm-tab-4" type="button" class="nav-tab">
							<?php esc_html_e( 'Modern Fields', 'acf-openstreetmap-field' ); ?>
						</label>
					<?php } ?>
				</h2>
				<?php // Future
				/*
				<div class="tab-content acf-osm-tab-1">
					<h3><?php esc_html_e('Plugin Options', 'acf-openstreetmap-field') ?></h3>
					<div class="acf-osm-plugin-settings">
						<?php
						// enable ACF-Field
						// enable geodata for post type

						?>
					</div>
				</div>
				*/ ?>
				<div class="tab-content acf-osm-tab-2">
					<h3><?php esc_html_e('Map Tile Provider Settings', 'acf-openstreetmap-field') ?></h3>
					<?php $this->providers_description(); ?>
					<div class="acf-osm-settings">
						<div class="acf-osm-provider-settings">
						<?php

						$provider_settings = $providers->get_providers( [], true );

						foreach ( $provider_settings as $provider_key => $provider_data ) {

							$this->print_provider_setting( $provider_key, $provider_data );

						}

						?>
						</div>
						<div class="acf-osm-test-map-container">
							<div class="acf-osm-test-map">
								<div
									data-test="providers"
									data-map="leaflet"
									data-map-lat="53.55064"
									data-map-lng="10.00065"
									data-map-zoom="12"
									data-map-layers="<?php echo esc_attr( json_encode(['OpenStreetMap.Mapnik']) ); ?>"
									>
								</div>
							</div>
						</div>
					</div>

				</div>
				<div class="tab-content acf-osm-tab-3">
					<?php $this->print_geocoder_settings(); ?>
				</div>
				<?php if ( Compat\ModernFields::is_active() ) { ?>
					<div class="tab-content acf-osm-tab-4">
						<?php $this->print_modern_fields_settings(); ?>
					</div>
				<?php } ?>

				<?php
				submit_button( __('Save Settings' , 'acf-openstreetmap-field' ) );
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Enqueue settings Assets
	 *
	 *	@action load-settings_page_acf_osm
	 */
	public function enqueue_assets() {
		$core = Core\Core::instance();
		$core->register_assets();

		// registering in Core\Core does not work ... why?
		wp_enqueue_script( 'acf-osm-settings' );
		wp_enqueue_style( 'acf-osm-settings' );
	}

	/**
	 *	Setup options.
	 *
	 *	@action admin_init
	 */
	public function register_settings() {

		$this->register_settings_providers();
		$this->register_settings_geocoder();
		$this->register_settings_modern_fields();

	}

	/**
	 *	@return array Disable tile providers with bounds
	 */
	private function get_default_option_providers() {

		$providers = Core\LeafletProviders::instance();
		$is_https = str_starts_with( get_option('home'), 'https:' );
		$provider_settings = $providers->get_providers(['credentials']);
		$default_option = [];

		foreach ( $provider_settings as $provider_key => $provider_data ) {
			if ( $this->has_bounds( $provider_data ) || ( $is_https && $this->is_insecure( $provider_data ) ) ) {
				$default_option[ $provider_key ] = '0';
				continue;
			}
			if ( isset( $provider_data['variants'] ) ) {
				foreach ( $provider_data['variants'] as $variant_key => $variant_data ) {
					if ( $this->has_bounds( $variant_data ) || ( $is_https && $this->is_insecure( $variant_data ) )) {
						$default_option[ $provider_key ]['variants'][$variant_key] = '0';
					}
				}
			}
		}
		return $default_option;
	}
}
