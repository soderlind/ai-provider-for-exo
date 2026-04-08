<?php
namespace Exo\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ConnectorSettings {

	public const OPTION_API_KEY    = 'connectors_ai_exo_api_key';
	public const OPTION_ENDPOINT   = 'connectors_ai_exo_endpoint';
	public const OPTION_MODEL_NAME = 'connectors_ai_exo_model_name';

	public static function register(): void {
		register_setting(
			'connectors',
			self::OPTION_API_KEY,
			[
				'type'              => 'string',
				'label'             => __( 'exo API Key', 'ai-provider-for-exo' ),
				'description'       => __( 'Optional API key for your exo cluster.', 'ai-provider-for-exo' ),
				'default'           => '',
				'show_in_rest'      => true,
				'sanitize_callback' => 'sanitize_text_field',
			]
		);
		add_filter(
			'option_' . self::OPTION_API_KEY,
			[ __CLASS__, 'mask_api_key' ]
		);

		register_setting(
			'connectors',
			self::OPTION_ENDPOINT,
			[
				'type'              => 'string',
				'label'             => __( 'Endpoint URL', 'ai-provider-for-exo' ),
				'description'       => __( 'exo API endpoint (default: http://localhost:52415).', 'ai-provider-for-exo' ),
				'default'           => 'http://localhost:52415',
				'show_in_rest'      => true,
				'sanitize_callback' => 'esc_url_raw',
			]
		);

		register_setting(
			'connectors',
			self::OPTION_MODEL_NAME,
			[
				'type'              => 'string',
				'label'             => __( 'Model Name', 'ai-provider-for-exo' ),
				'description'       => __( 'Model to use (leave empty to auto-detect from exo).', 'ai-provider-for-exo' ),
				'default'           => '',
				'show_in_rest'      => true,
				'sanitize_callback' => 'sanitize_text_field',
			]
		);
	}

	public static function mask_api_key( mixed $key ): string {
		if ( ! is_string( $key ) || strlen( $key ) <= 4 ) {
			return is_string( $key ) ? $key : '';
		}
		return str_repeat( "\u{2022}", min( strlen( $key ) - 4, 16 ) )
			. substr( $key, -4 );
	}

	public static function get_real_api_key(): string {
		remove_filter( 'option_' . self::OPTION_API_KEY, [ __CLASS__, 'mask_api_key' ] );
		$value = get_option( self::OPTION_API_KEY, '' );
		add_filter( 'option_' . self::OPTION_API_KEY, [ __CLASS__, 'mask_api_key' ] );

		return (string) $value;
	}
}
