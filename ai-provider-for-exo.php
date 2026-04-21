<?php
/**
 * Plugin Name: AI Provider for exo
 * Plugin URI:  https://github.com/soderlind/ai-provider-for-exo
 * Description: Connect WordPress to exo — run frontier AI models locally on your device cluster.
 * Requires at least: 7.0
 * Requires PHP: 8.3
 * Version: 1.0.0
 * Author: Per Søderlind
 * Author URI: https://soderlind.no/
 * License: GPL-2.0-or-later
 * Text Domain: ai-provider-for-exo
 * Domain Path: /languages
 */

namespace Aiprfoex;

use WordPress\AiClient\AiClient;
use Aiprfoex\Provider\ExoProvider;
use Aiprfoex\Rest\DetectModelsController;
use Aiprfoex\Settings\ConnectorSettings;
use Aiprfoex\Settings\SettingsManager;

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

define( 'AIPRFOEX_PROVIDER_VERSION', '1.0.0' );
define( 'AIPRFOEX_PROVIDER_FILE', __FILE__ );
define( 'AIPRFOEX_AI_PLUGIN_SENTINEL_ID', 'aiprfoex_status' );
define( 'AIPRFOEX_AI_PLUGIN_SENTINEL_OPTION', 'connectors_ai_aiprfoex_status_api_key' );

require_once __DIR__ . '/src/autoload.php';

/**
 * Register the provider with the AI Client registry early.
 */
function register_provider(): void {
	if ( ! class_exists( AiClient::class) ) {
		return;
	}

	$registry = AiClient::defaultRegistry();

	if ( ! $registry->hasProvider( ExoProvider::class) ) {
		$registry->registerProvider( ExoProvider::class);
	}
}
add_action( 'init', __NAMESPACE__ . '\\register_provider', 5 );

/**
 * Configure authentication after WP loads credentials.
 *
 * Runs at priority 30, after core connector key binding (priority 20).
 * exo uses standard Bearer token authentication (optional).
 */
function setup_authentication(): void {
	if ( ! class_exists( AiClient::class) ) {
		return;
	}

	$api_key = ConnectorSettings::get_real_api_key();

	if ( empty( $api_key ) ) {
		$env_key = SettingsManager::instance()->resolve_env( 'AIPRFOEX_API_KEY' );
		if ( '' !== $env_key ) {
			$api_key = $env_key;
		}
	}

	// Always register authentication — the SDK requires an instance even
	// when the provider does not need a key (exo API key is optional).
	AiClient::defaultRegistry()->setProviderRequestAuthentication(
		'aiprfoex',
		new \WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication( $api_key ?: '' )
	);
}
add_action( 'init', __NAMESPACE__ . '\\setup_authentication', 30 );

/**
 * Increase the AI Client request timeout for local inference.
 *
 * The default 30 s is often insufficient for reasoning models running
 * on a local exo cluster. Bump to 300 s (5 min) so text generation
 * with multiple candidates has enough time to complete.
 *
 * @param int $timeout The default timeout in seconds.
 * @return int
 */
function increase_request_timeout( int $timeout ): int {
	return max( $timeout, 300 );
}
add_filter( 'wp_ai_client_default_request_timeout', __NAMESPACE__ . '\\increase_request_timeout' );

/**
 * Allow the exo endpoint host through wp_safe_remote_request.
 *
 * The AI Client SDK uses wp_safe_remote_request which blocks requests to
 * private/loopback IPs (localhost, 127.0.0.1). Since exo typically runs
 * locally, we whitelist the configured endpoint host.
 *
 * @param bool   $is_external Whether the host is external.
 * @param string $host        The request host.
 * @return bool
 */
function allow_exo_host( bool $is_external, string $host ): bool {
	if ( $is_external ) {
		return $is_external;
	}

	$endpoint = SettingsManager::instance()->get_endpoint();
	$exo_host = wp_parse_url( $endpoint, PHP_URL_HOST );

	if ( $exo_host && strtolower( $host ) === strtolower( $exo_host ) ) {
		return true;
	}

	return $is_external;
}
add_filter( 'http_request_host_is_external', __NAMESPACE__ . '\\allow_exo_host', 10, 2 );

/**
 * Allow the exo endpoint port through wp_safe_remote_request.
 *
 * By default only ports 80, 443, and 8080 are allowed. exo's default
 * port 52415 must be explicitly whitelisted.
 *
 * @param int[]  $ports Allowed ports.
 * @param string $host  The request host.
 * @return int[]
 */
function allow_exo_port( array $ports, string $host ): array {
	$endpoint = SettingsManager::instance()->get_endpoint();
	$exo_host = wp_parse_url( $endpoint, PHP_URL_HOST );
	$exo_port = wp_parse_url( $endpoint, PHP_URL_PORT );

	if ( $exo_host && $exo_port && strtolower( $host ) === strtolower( $exo_host ) ) {
		$ports[] = (int) $exo_port;
	}

	return $ports;
}
add_filter( 'http_allowed_safe_ports', __NAMESPACE__ . '\\allow_exo_port', 10, 2 );

/**
 * Prepend exo models to the AI plugin's preferred text-generation list.
 *
 * Without this, the PromptBuilder falls back to the first "configured"
 * provider it encounters (alphabetically), which may be a different
 * provider with broken/missing auth. Adding exo models here ensures they
 * are selected when the cluster is reachable.
 *
 * @param array<int, array{string, string}> $preferred The current preferred models.
 * @return array<int, array{string, string}>
 */
function prepend_exo_preferred_models( array $preferred ): array {
	try {
		$dir    = ExoProvider::modelMetadataDirectory();
		$models = $dir->listModelMetadata();
	} catch ( \Exception $e ) {
		return $preferred;
	}

	$exo = [];
	foreach ( $models as $meta ) {
		$exo[] = [ 'aiprfoex', $meta->getId() ];
	}

	return array_merge( $exo, $preferred );
}
add_filter( 'wpai_preferred_text_models', __NAMESPACE__ . '\\prepend_exo_preferred_models' );

/**
 * Register connector settings.
 */
add_action( 'init', [ ConnectorSettings::class, 'register' ] );

/**
 * Register the detect REST route.
 */
add_action( 'rest_api_init', [ DetectModelsController::class, 'register' ] );

/**
 * Register the connector JS module.
 *
 * Only @wordpress/connectors is a script module dependency.
 * Classic-script packages are accessed via window.wp.* globals.
 */
function register_connector_module(): void {
	wp_register_script_module(
		'aiprfoex/connectors',
		plugins_url( 'build/connectors.js', AIPRFOEX_PROVIDER_FILE ),
		[
			[
				'id'     => '@wordpress/connectors',
				'import' => 'dynamic',
			],
		],
		AIPRFOEX_PROVIDER_VERSION
	);

	wp_set_script_translations(
		'aiprfoex/connectors',
		'ai-provider-for-exo',
		plugin_dir_path( AIPRFOEX_PROVIDER_FILE ) . 'languages'
	);
}
add_action( 'init', __NAMESPACE__ . '\\register_connector_module' );

/**
 * Enqueue on the Connectors page only (hook both page variants).
 */
function enqueue_connector_module(): void {
	wp_enqueue_script_module( 'aiprfoex/connectors' );
}
add_action( 'options-connectors-wp-admin_init', __NAMESPACE__ . '\\enqueue_connector_module' );
add_action( 'connectors-wp-admin_init', __NAMESPACE__ . '\\enqueue_connector_module' );

/**
 * Filter the sentinel connector out of the Connectors page UI.
 *
 * @param array $data Script module data.
 * @return array
 */
function filter_connector_script_data( array $data ): array {
	if ( isset( $data['connectors'][ AIPRFOEX_AI_PLUGIN_SENTINEL_ID ] ) ) {
		unset( $data['connectors'][ AIPRFOEX_AI_PLUGIN_SENTINEL_ID ] );
	}

	return $data;
}
add_filter( 'script_module_data_options-connectors-wp-admin', __NAMESPACE__ . '\\filter_connector_script_data' );
add_filter( 'script_module_data_connectors-wp-admin', __NAMESPACE__ . '\\filter_connector_script_data' );

/**
 * Unregister from the connector registry so core does not manage our API key.
 *
 * This prevents double-masking, failed key validation, and duplicate
 * setting registration.
 *
 * After unregistering, a hidden sentinel connector is registered so the
 * AI plugin can detect this provider.
 *
 * @param \WP_Connector_Registry $registry Connector registry instance.
 */
function unregister_from_connector_registry( \WP_Connector_Registry $registry ): void {
	if ( $registry->is_registered( 'aiprfoex' ) ) {
		$registry->unregister( 'aiprfoex' );
	}

	if ( ! $registry->is_registered( AIPRFOEX_AI_PLUGIN_SENTINEL_ID ) ) {
		$registry->register(
			AIPRFOEX_AI_PLUGIN_SENTINEL_ID,
			[
				'name'           => __( 'exo Status', 'ai-provider-for-exo' ),
				'description'    => __( 'Internal compatibility connector for AI plugin detection.', 'ai-provider-for-exo' ),
				'type'           => 'ai_provider',
				'authentication' => [
					'method' => 'api_key',
				],
			]
		);
	}
}
add_action( 'wp_connectors_init', __NAMESPACE__ . '\\unregister_from_connector_registry' );

/**
 * Sync an internal sentinel option so the AI plugin sees a configured connector.
 *
 * The AI plugin checks wp_get_connectors() for ai_provider entries with a
 * non-empty API-key option. Because this provider unregisters its visible
 * connector to keep a custom UI, we expose a hidden compatibility connector
 * instead and toggle its generated option based on real configuration.
 *
 * For exo the API key is optional, so we trigger on endpoint alone.
 */
function sync_ai_plugin_credential_sentinel(): void {
	$endpoint = get_option( ConnectorSettings::OPTION_ENDPOINT );
	if ( ! $endpoint ) {
		$endpoint = SettingsManager::instance()->resolve_env( 'AIPRFOEX_ENDPOINT' );
	}
	$current = get_option( AIPRFOEX_AI_PLUGIN_SENTINEL_OPTION, '' );

	if ( $endpoint ) {
		if ( '1' !== $current ) {
			update_option( AIPRFOEX_AI_PLUGIN_SENTINEL_OPTION, '1' );
		}
		return;
	}

	if ( '' !== $current ) {
		delete_option( AIPRFOEX_AI_PLUGIN_SENTINEL_OPTION );
	}
}
add_action( 'init', __NAMESPACE__ . '\\sync_ai_plugin_credential_sentinel', 35 );
