<?php
/**
 * Model metadata directory for exo.
 *
 * Discovers available models from the exo /v1/models endpoint,
 * falls back to user-configured model name or a generic entry.
 */

namespace Aiprfoex\Metadata;

use WordPress\AiClient\Providers\Contracts\ModelMetadataDirectoryInterface;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WordPress\AiClient\Providers\Models\DTO\SupportedOption;
use WordPress\AiClient\Providers\Models\Enums\CapabilityEnum;
use WordPress\AiClient\Providers\Models\Enums\OptionEnum;
use WordPress\AiClient\Messages\Enums\ModalityEnum;
use WordPress\AiClient\Common\Exception\InvalidArgumentException;
use Aiprfoex\Settings\SettingsManager;

class ExoModelMetadataDirectory implements ModelMetadataDirectoryInterface {

	private ?array $cached = null;

	public function listModelMetadata(): array {
		if ( null !== $this->cached ) {
			return $this->cached;
		}

		$settings   = SettingsManager::instance();
		$model_name = $settings->get_model_name();

		// 1. Use configured model name.
		if ( ! empty( $model_name ) ) {
			$this->cached = [ $this->buildModel( $model_name, $model_name ) ];
			return $this->cached;
		}

		// 2. Try to discover models from exo's /v1/models endpoint.
		$discovered = $this->discoverModels();
		if ( ! empty( $discovered ) ) {
			$this->cached = $discovered;
			return $this->cached;
		}

		// 3. Fall back to a generic entry.
		$this->cached = [
			$this->buildModel(
				'exo-model',
				__( 'exo Model', 'ai-provider-for-exo' )
			),
		];

		return $this->cached;
	}

	public function hasModelMetadata( string $modelId ): bool {
		foreach ( $this->listModelMetadata() as $meta ) {
			if ( $meta->getId() === $modelId ) {
				return true;
			}
		}
		return false;
	}

	public function getModelMetadata( string $modelId ): ModelMetadata {
		foreach ( $this->listModelMetadata() as $meta ) {
			if ( $meta->getId() === $modelId ) {
				return $meta;
			}
		}
		throw new InvalidArgumentException( 'Unknown model: ' . esc_html( $modelId ) );
	}

	/**
	 * Discover models from exo's OpenAI-compatible /v1/models endpoint.
	 *
	 * @return ModelMetadata[]|null
	 */
	private function discoverModels(): ?array {
		$settings = SettingsManager::instance();
		$endpoint = $settings->get_endpoint();

		if ( empty( $endpoint ) ) {
			return null;
		}

		$url     = rtrim( $endpoint, '/' ) . '/v1/models';
		$headers = [ 'Content-Type' => 'application/json' ];

		$api_key = $settings->get_real_api_key();
		if ( ! empty( $api_key ) ) {
			$headers['Authorization'] = 'Bearer ' . $api_key;
		}

		$response = wp_remote_get( $url, [
			'headers' => $headers,
			'timeout' => 10,
		] );

		if ( is_wp_error( $response ) ) {
			return null;
		}

		if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) || empty( $body['data'] ) ) {
			return null;
		}

		$models = [];
		foreach ( $body['data'] as $model_data ) {
			$id = sanitize_text_field( $model_data['id'] ?? '' );
			if ( empty( $id ) ) {
				continue;
			}
			$models[] = $this->buildModel( $id, $id );
		}

		return ! empty( $models ) ? $models : null;
	}

	private function buildModel( string $id, string $name ): ModelMetadata {
		$capabilities = [
			CapabilityEnum::textGeneration(),
			CapabilityEnum::chatHistory(),
		];

		return new ModelMetadata(
			$id,
			$name,
			$capabilities,
			$this->buildSupportedOptions()
		);
	}

	/**
	 * @return SupportedOption[]
	 */
	private function buildSupportedOptions(): array {
		return [
			new SupportedOption(
				OptionEnum::inputModalities(),
				[
					[ ModalityEnum::text() ],
					[ ModalityEnum::text(), ModalityEnum::image() ],
				]
			),
			new SupportedOption(
				OptionEnum::outputModalities(),
				[ [ ModalityEnum::text() ] ]
			),
			new SupportedOption( OptionEnum::systemInstruction() ),
			new SupportedOption( OptionEnum::temperature() ),
			new SupportedOption( OptionEnum::maxTokens() ),
			new SupportedOption( OptionEnum::topP() ),
			new SupportedOption( OptionEnum::topK() ),
			new SupportedOption( OptionEnum::candidateCount() ),
			new SupportedOption( OptionEnum::stopSequences() ),
			new SupportedOption( OptionEnum::presencePenalty() ),
			new SupportedOption( OptionEnum::frequencyPenalty() ),
			new SupportedOption( OptionEnum::logprobs() ),
			new SupportedOption( OptionEnum::topLogprobs() ),
			new SupportedOption( OptionEnum::outputMimeType(), [ 'text/plain', 'application/json' ] ),
			new SupportedOption( OptionEnum::outputSchema() ),
			new SupportedOption( OptionEnum::functionDeclarations() ),
			new SupportedOption( OptionEnum::customOptions() ),
		];
	}
}
