<?php
/**
 * OpenAI-compatible text generation model for exo.
 *
 * exo serves the standard OpenAI chat completions format at:
 *   {endpoint}/v1/chat/completions
 *
 * The base class handles parameter building, message formatting,
 * response parsing, and streaming.
 */

namespace Exo\Models;

use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\OpenAiCompatibleImplementation\AbstractOpenAiCompatibleTextGenerationModel;
use Exo\Provider\ExoProvider;

class ExoTextGenerationModel extends AbstractOpenAiCompatibleTextGenerationModel {

	/**
	 * Create a request for the exo OpenAI-compatible API.
	 *
	 * @param HttpMethodEnum              $method  HTTP method.
	 * @param string                      $path    API path (e.g. 'chat/completions').
	 * @param array<string, string|list<string>> $headers Optional headers.
	 * @param string|array<string, mixed>|null    $data    Optional request data.
	 * @return Request
	 */
	protected function createRequest( HttpMethodEnum $method, string $path, array $headers = [], $data = null ): Request {
		$base_url = ExoProvider::baseUrl();
		$url      = rtrim( $base_url, '/' ) . '/v1/' . ltrim( $path, '/' );

		return new Request(
			$method,
			$url,
			$headers,
			$data,
			$this->getRequestOptions()
		);
	}
}
