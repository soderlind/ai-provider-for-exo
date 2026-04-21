<?php
/**
 * exo Provider.
 *
 * Registers the provider with the WordPress AI Client SDK.
 *
 * exo exposes an OpenAI-compatible API at http://localhost:52415:
 *   Chat:   POST /v1/chat/completions
 *   Models: GET  /v1/models
 */

namespace Aiprfoex\Provider;

use WordPress\AiClient\Providers\ApiBasedImplementation\AbstractApiProvider;
use WordPress\AiClient\Providers\ApiBasedImplementation\ListModelsApiBasedProviderAvailability;
use WordPress\AiClient\Providers\Contracts\ModelMetadataDirectoryInterface;
use WordPress\AiClient\Providers\Contracts\ProviderAvailabilityInterface;
use WordPress\AiClient\Providers\DTO\ProviderMetadata;
use WordPress\AiClient\Providers\Enums\ProviderTypeEnum;
use WordPress\AiClient\Providers\Http\Enums\RequestAuthenticationMethod;
use WordPress\AiClient\Providers\Models\Contracts\ModelInterface;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use Aiprfoex\Metadata\ExoModelMetadataDirectory;
use Aiprfoex\Models\ExoTextGenerationModel;
use Aiprfoex\Settings\SettingsManager;

class ExoProvider extends AbstractApiProvider {

	public static function baseUrl(): string {
		$endpoint = SettingsManager::instance()->get_endpoint();
		return rtrim( $endpoint, '/' );
	}

	protected static function createModel(
		ModelMetadata $model_metadata,
		ProviderMetadata $provider_metadata
	): ModelInterface {
		return new ExoTextGenerationModel( $model_metadata, $provider_metadata );
	}

	protected static function createProviderMetadata(): ProviderMetadata {
		return new ProviderMetadata(
			'aiprfoex',
			__( 'exo', 'ai-provider-for-exo' ),
			ProviderTypeEnum::server(),
			'https://github.com/exo-explore/exo',
			RequestAuthenticationMethod::apiKey()
		);
	}

	protected static function createProviderAvailability(): ProviderAvailabilityInterface {
		return new ListModelsApiBasedProviderAvailability(
			static::modelMetadataDirectory()
		);
	}

	protected static function createModelMetadataDirectory(): ModelMetadataDirectoryInterface {
		return new ExoModelMetadataDirectory();
	}
}
