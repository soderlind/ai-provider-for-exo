<?php
/**
 * Plugin Name: AI Provider for exo
 * Plugin URI:  https://github.com/soderlind/ai-provider-for-exo
 * Description: Connect WordPress to exo — run frontier AI models locally on your device cluster.
 * Requires at least: 7.0
 * Requires PHP: 8.3
 * Version: 0.2.0
 * Author: Per Søderlind
 * Author URI: https://soderlind.no/
 * License: GPL-2.0-or-later
 * Text Domain: ai-provider-for-exo
 * Domain Path: /languages
 */

namespace Exo;

use WordPress\AiClient\AiClient;
use Exo\Provider\ExoProvider;
use Exo\Rest\DetectModelsController;
use Exo\Settings\ConnectorSettings;
use Exo\Settings\SettingsManager;

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

define( 'EXO_PROVIDER_VERSION', '0.2.0' );
define( 'EXO_PROVIDER_FILE', __FILE__ );
define( 'EXO_AI_PLUGIN_SENTINEL_ID', 'exo_status' );
define( 'EXO_AI_PLUGIN_SENTINEL_OPTION', 'connectors_ai_exo_status_api_key' );

require_once __DIR__ . '/src/autoload.php';

/**
 * Load plugin text domain for translations.
 */
function load_textdomain(): void {
	load_plugin_textdomain(
		'ai-provider-for-exo',
		false,
		dirname( plugin_basename( EXO_PROVIDER_FILE ) ) . '/languages'
	);
}
add_action( 'init', __NAMESPACE__ . '\\load_textdomain' );

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
		$env_key = SettingsManager::instance()->resolve_env( 'EXO_API_KEY' );
		if ( '' !== $env_key ) {
			$api_key = $env_key;
		}
	}

	// Always register authentication — the SDK requires an instance even
	// when the provider does not need a key (exo API key is optional).
	AiClient::defaultRegistry()->setProviderRequestAuthentication(
		'exo',
		new \WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication( $api_key ?: '' )
	);
}
add_action( 'init', __NAMESPACE__ . '\\setup_authentication', 30 );

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
		$exo[] = [ 'exo', $meta->getId() ];
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
		'exo/connectors',
		plugins_url( 'build/connectors.js', EXO_PROVIDER_FILE ),
		[
			[
				'id'     => '@wordpress/connectors',
				'import' => 'dynamic',
			],
		],
		EXO_PROVIDER_VERSION
	);

	wp_set_script_translations(
		'exo/connectors',
		'ai-provider-for-exo',
		plugin_dir_path( EXO_PROVIDER_FILE ) . 'languages'
	);
}
add_action( 'init', __NAMESPACE__ . '\\register_connector_module' );

/**
 * Enqueue on the Connectors page only (hook both page variants).
 */
function enqueue_connector_module(): void {
	wp_enqueue_script_module( 'exo/connectors' );
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
	if ( isset( $data['connectors'][ EXO_AI_PLUGIN_SENTINEL_ID ] ) ) {
		unset( $data['connectors'][ EXO_AI_PLUGIN_SENTINEL_ID ] );
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
	if ( $registry->is_registered( 'exo' ) ) {
		$registry->unregister( 'exo' );
	}

	if ( ! $registry->is_registered( EXO_AI_PLUGIN_SENTINEL_ID ) ) {
		$registry->register(
			EXO_AI_PLUGIN_SENTINEL_ID,
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
		$endpoint = SettingsManager::instance()->resolve_env( 'EXO_ENDPOINT' );
	}
	$current = get_option( EXO_AI_PLUGIN_SENTINEL_OPTION, '' );

	if ( $endpoint ) {
		if ( '1' !== $current ) {
			update_option( EXO_AI_PLUGIN_SENTINEL_OPTION, '1' );
		}
		return;
	}

	if ( '' !== $current ) {
		delete_option( EXO_AI_PLUGIN_SENTINEL_OPTION );
	}
}
add_action( 'init', __NAMESPACE__ . '\\sync_ai_plugin_credential_sentinel', 35 );
