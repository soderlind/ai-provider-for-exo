<?php
namespace Exo\Settings;

class SettingsManager {

	private static ?self $instance = null;

	private function __construct() {}

	public static function instance(): self {
		return self::$instance ??= new self();
	}

	public function get_endpoint(): string {
		return $this->resolve(
			ConnectorSettings::OPTION_ENDPOINT,
			'EXO_ENDPOINT',
			'http://localhost:52415'
		);
	}

	public function get_real_api_key(): string {
		$key = ConnectorSettings::get_real_api_key();
		if ( ! empty( $key ) ) {
			return $key;
		}
		return $this->resolve_env( 'EXO_API_KEY' );
	}

	public function get_model_name(): string {
		return $this->resolve(
			ConnectorSettings::OPTION_MODEL_NAME,
			'EXO_MODEL',
			''
		);
	}

	private function resolve( string $option_name, string $env_name, string $default ): string {
		$value = get_option( $option_name, '' );
		if ( is_string( $value ) && '' !== $value ) {
			return $value;
		}

		$env = $this->resolve_env( $env_name );
		if ( '' !== $env ) {
			return $env;
		}

		return $default;
	}

	public function resolve_env( string $name ): string {
		$value = getenv( $name );
		if ( false !== $value && '' !== $value ) {
			return (string) $value;
		}

		if ( defined( $name ) ) {
			$const = constant( $name );
			if ( is_string( $const ) && '' !== $const ) {
				return $const;
			}
		}

		return '';
	}
}
