<?php

namespace ACFFieldOpenstreetmap\Settings\Traits;

use ACFFieldOpenstreetmap\Compat;

trait ModernFieldsSettings {

	/**
	 *	Output Modern Fields settings
	 */
	private function print_modern_fields_settings() {
		?>
		<h3><?php esc_html_e( 'Modern Fields', 'acf-openstreetmap-field' ); ?></h3>
		<p class="description">
			<?php esc_html_e( 'Modern Fields draws the map of its Map field with OpenStreetMap, by default when no Google Maps API key is set. Choose its layer among the providers enabled in the Providers tab. A provider with the map proxy enabled keeps its access key hidden.', 'acf-openstreetmap-field' ); ?>
		</p>
		<table class="form-table" role="presentation">
			<?php do_settings_fields( $this->optionset, 'modern-fields' ); ?>
		</table>
		<?php
	}

	/**
	 *	Setup Modern Fields options. Only while Modern Fields is active: options.php would empty
	 *	a registered option missing in the form.
	 */
	private function register_settings_modern_fields() {

		if ( ! Compat\ModernFields::is_active() ) {
			return;
		}

		register_setting( $this->optionset, Compat\ModernFields::OPTION, [
			'type'              => 'array',
			'sanitize_callback' => [ $this, 'sanitize_modern_fields' ],
			'default'           => [],
		] );

		add_settings_field(
			Compat\ModernFields::OPTION . '-layer',
			__( 'Map layer', 'acf-openstreetmap-field' ),
			[ $this, 'modern_fields_layer_ui' ],
			$this->optionset,
			'modern-fields'
		);
	}

	/**
	 *	Layer select
	 */
	public function modern_fields_layer_ui() {

		$modern_fields = Compat\ModernFields::instance();
		$name          = Compat\ModernFields::OPTION . '[layer]';

		if ( Compat\ModernFields::is_tile_url_constant_defined() ) {
			printf( '<input type="hidden" name="%1$s" value="%2$s" />', esc_attr( $name ), esc_attr( $modern_fields->get_layer_key() ) );
			printf(
				'<p>%s</p>',
				sprintf(
					/* translators: %s: constant name */
					esc_html__( 'Set by the %s constant in wp-config.php.', 'acf-openstreetmap-field' ),
					'<code>MODERN_FIELDS_OSM_TILE_URL</code>'
				)
			);
			return;
		}

		$choices = [ '' => __( 'Default of Modern Fields', 'acf-openstreetmap-field' ) ] + $modern_fields->get_layer_choices();

		echo $this->select_ui( $choices, $modern_fields->get_layer_key(), $name ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		printf(
			'<p class="description">%s</p>',
			esc_html( sprintf(
				/* translators: %d: max zoom */
				__( 'The map of Modern Fields zooms from 0 to %d. Out of the zoom range shown in brackets, it stays empty.', 'acf-openstreetmap-field' ),
				Compat\ModernFields::MAX_ZOOM
			) )
		);
	}

	/**
	 *	@param mixed $value
	 *	@return array
	 */
	public function sanitize_modern_fields( $value ) {
		$layer = is_array( $value ) && is_string( $value['layer'] ?? null ) ? sanitize_text_field( $value['layer'] ) : '';

		if ( '' !== $layer && ! Compat\ModernFields::is_tile_url_constant_defined() && ! isset( Compat\ModernFields::instance()->get_layer_choices()[ $layer ] ) ) {
			$layer = '';
		}

		return [ 'layer' => $layer ];
	}
}
