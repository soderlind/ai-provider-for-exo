<?php
/**
 * Plugin Name: AI Provider for exo
 * Plugin URI:  https://github.com/soderlind/ai-provider-for-exo
 * Description: Connect WordPress to exo — run frontier AI models locally on your device cluster.
 * Requires at least: 7.0
 * Requires PHP: 8.3
 * Version: 0.1.0
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

define( 'EXO_PROVIDER_VERSION', '0.1.0' );
define( 'EXO_PROVIDER_FILE', __FILE__ );

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
	if ( ! class_exists( AiClient::class ) ) {
		return;
	}

	$registry = AiClient::defaultRegistry();

	if ( ! $registry->hasProvider( ExoProvider::class ) ) {
		$registry->registerProvider( ExoProvider::class );
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
	if ( ! class_exists( AiClient::class ) ) {
		return;
	}

	$api_key = ConnectorSettings::get_real_api_key();

	if ( empty( $api_key ) ) {
		$env_key = SettingsManager::instance()->resolve_env( 'EXO_API_KEY' );
		if ( '' !== $env_key ) {
			$api_key = $env_key;
		}
	}

	if ( ! empty( $api_key ) ) {
		AiClient::defaultRegistry()->setProviderRequestAuthentication(
			'exo',
			new \WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication( $api_key )
		);
	}
}
add_action( 'init', __NAMESPACE__ . '\\setup_authentication', 30 );

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
 * Unregister from the connector registry so core does not manage our API key.
 *
 * This prevents double-masking, failed key validation, and duplicate
 * setting registration.
 *
 * @param \WP_Connector_Registry $registry Connector registry instance.
 */
function unregister_from_connector_registry( \WP_Connector_Registry $registry ): void {
	if ( $registry->is_registered( 'exo' ) ) {
		$registry->unregister( 'exo' );
	}
}
add_action( 'wp_connectors_init', __NAMESPACE__ . '\\unregister_from_connector_registry' );
