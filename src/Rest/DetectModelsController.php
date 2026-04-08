<?php
/**
 * REST endpoint to auto-detect models from an exo cluster.
 *
 * Probes the exo /v1/models endpoint and saves detected model names.
 */

namespace Exo\Rest;

use Exo\Settings\ConnectorSettings;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DetectModelsController {

	public const ROUTE_NAMESPACE = 'exo/v1';
	public const ROUTE           = '/detect';

	/**
	 * Register the route.
	 */
	public static function register(): void {
		register_rest_route(
			self::ROUTE_NAMESPACE,
			self::ROUTE,
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ __CLASS__, 'handle' ],
				'permission_callback' => [ __CLASS__, 'check_permission' ],
				'args'                => [
					'endpoint' => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'esc_url_raw',
					],
					'api_key'  => [
						'required'          => false,
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					],
				],
			]
		);
	}

	/**
	 * Only administrators can detect models.
	 */
	public static function check_permission(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Probe the exo endpoint and return active (loaded) models.
	 *
	 * First fetches the model catalog, then probes each model with a
	 * minimal chat completion request to find which ones are actually
	 * loaded. Only active models are saved.
	 */
	public static function handle( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$endpoint = rtrim( $request->get_param( 'endpoint' ), '/' );
		$api_key  = $request->get_param( 'api_key' );

		// If the stored key is masked, read the real one.
		if ( is_string( $api_key ) && str_starts_with( $api_key, "\u{2022}" ) ) {
			$api_key = ConnectorSettings::get_real_api_key();
		}

		if ( empty( $endpoint ) ) {
			return new WP_Error(
				'missing_endpoint',
				__( 'Endpoint URL is required.', 'ai-provider-for-exo' ),
				[ 'status' => 400 ]
			);
		}

		$headers = [ 'Content-Type' => 'application/json' ];

		if ( ! empty( $api_key ) ) {
			$headers[ 'Authorization' ] = 'Bearer ' . $api_key;
		}

		// 1. Fetch the model catalog.
		$catalog_url = $endpoint . '/v1/models';
		$response    = wp_remote_get( $catalog_url, [
			'headers' => $headers,
			'timeout' => 15,
		] );

		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'connection_failed',
				/* translators: %s: error message from wp_remote_get */
				sprintf( __( 'Could not connect to exo: %s', 'ai-provider-for-exo' ), $response->get_error_message() ),
				[ 'status' => 502 ]
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );

		if ( 200 !== $status_code ) {
			return new WP_Error(
				'detection_failed',
				/* translators: %d: HTTP status code */
				sprintf( __( 'exo returned HTTP %d. Verify the endpoint URL.', 'ai-provider-for-exo' ), $status_code ),
				[ 'status' => 502 ]
			);
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $body ) || empty( $body[ 'data' ] ) ) {
			return new WP_Error(
				'no_models',
				__( 'Connected to exo but no models were found.', 'ai-provider-for-exo' ),
				[ 'status' => 200 ]
			);
		}

		$catalog_ids  = [];
		$catalog_caps = [];
		foreach ( $body[ 'data' ] as $model_data ) {
			$id = sanitize_text_field( $model_data[ 'id' ] ?? '' );
			if ( ! empty( $id ) ) {
				$catalog_ids[] = $id;
				// Store capabilities from the catalog keyed by model id.
				$caps = [];
				if ( ! empty( $model_data[ 'capabilities' ] ) && is_array( $model_data[ 'capabilities' ] ) ) {
					$caps = array_map( 'sanitize_text_field', $model_data[ 'capabilities' ] );
				}
				$catalog_caps[ $id ] = $caps;
			}
		}

		if ( empty( $catalog_ids ) ) {
			return new WP_Error(
				'no_models',
				__( 'Connected to exo but no models were found.', 'ai-provider-for-exo' ),
				[ 'status' => 200 ]
			);
		}

		// 2. Probe each model to find active (loaded) ones.
		$completions_url = $endpoint . '/v1/chat/completions';
		$active_models   = [];

		foreach ( $catalog_ids as $model_id ) {
			$probe = wp_remote_post( $completions_url, [
				'headers' => $headers,
				'timeout' => 5,
				'body'    => wp_json_encode( [
					'model'      => $model_id,
					'messages'   => [ [ 'role' => 'user', 'content' => 'hi' ] ],
					'max_tokens' => 1,
				] ),
			] );

			if ( is_wp_error( $probe ) ) {
				continue;
			}

			$probe_code = wp_remote_retrieve_response_code( $probe );

			// 200 = model is active; 404 = not loaded.
			if ( 200 === $probe_code ) {
				$active_models[] = $model_id;
			}
		}

		if ( empty( $active_models ) ) {
			// Clear any previously saved model name and capabilities.
			update_option( ConnectorSettings::OPTION_MODEL_NAME, '' );
			update_option( ConnectorSettings::OPTION_CAPABILITIES, [] );

			return new WP_REST_Response(
				[
					'model_name'    => '',
					'models'        => [],
					'count'         => 0,
					'catalog_count' => count( $catalog_ids ),
					'capabilities'  => [],
				],
				200
			);
		}

		// Collect unique capabilities from active models.
		$all_caps = [];
		foreach ( $active_models as $model_id ) {
			foreach ( $catalog_caps[ $model_id ] ?? [] as $cap ) {
				$all_caps[ $cap ] = true;
			}
		}
		$capabilities = array_keys( $all_caps );

		// Save the active model names and capabilities to the database.
		$model_name = implode( ',', $active_models );
		update_option( ConnectorSettings::OPTION_MODEL_NAME, $model_name );
		update_option( ConnectorSettings::OPTION_CAPABILITIES, $capabilities );

		return new WP_REST_Response(
			[
				'model_name'    => $model_name,
				'models'        => $active_models,
				'count'         => count( $active_models ),
				'catalog_count' => count( $catalog_ids ),
				'capabilities'  => $capabilities,
			],
			200
		);
	}
}
