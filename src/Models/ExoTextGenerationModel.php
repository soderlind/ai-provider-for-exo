<?php
/**
 * OpenAI-compatible text generation model for exo.
 *
 * exo serves the standard OpenAI chat completions format at:
 *   {endpoint}/v1/chat/completions
 *
 * The base class handles parameter building, message formatting,
 * response parsing, and streaming.
 *
 * exo does not support the `n` parameter (multiple candidates per request).
 * This class emulates it by issuing N sequential requests and merging the
 * results so the SDK's `toTexts()` returns one string per candidate.
 */

namespace Exo\Models;

use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\DTO\Response;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\OpenAiCompatibleImplementation\AbstractOpenAiCompatibleTextGenerationModel;
use WordPress\AiClient\Results\DTO\GenerativeAiResult;
use WordPress\AiClient\Messages\DTO\Message;
use Exo\Provider\ExoProvider;

class ExoTextGenerationModel extends AbstractOpenAiCompatibleTextGenerationModel {

	/**
	 * Default max_tokens for local inference.
	 *
	 * Reasoning models (Qwen3.5, etc.) silently consume tokens on internal
	 * chain-of-thought.  Without a cap the request can run for minutes. A
	 * sensible default keeps responses snappy while still allowing longer
	 * answers when explicitly configured.
	 */
	private const DEFAULT_MAX_TOKENS = 1024;

	/**
	 * Requested candidate count, captured before forcing n=1.
	 *
	 * @var int
	 */
	private int $requested_candidates = 1;

	/**
	 * Last prepared params, stored so additional requests can be replayed.
	 *
	 * @var array<string, mixed>|null
	 */
	private ?array $last_params = null;

	/**
	 * Ensure max_tokens is always present and emulate n > 1.
	 *
	 * exo ignores the `n` parameter and always returns a single choice.
	 * We capture the requested count, force n=1, and replay the request
	 * in parseResponseToGenerativeAiResult().
	 *
	 * @param list<Message> $prompt The messages for the completion.
	 * @return array<string, mixed>
	 */
	protected function prepareGenerateTextParams( array $prompt ): array {
		$params = parent::prepareGenerateTextParams( $prompt );

		if ( ! isset( $params['max_tokens'] ) ) {
			$params['max_tokens'] = self::DEFAULT_MAX_TOKENS;
		}

		// Capture requested n, then force n=1.
		$this->requested_candidates = isset( $params['n'] ) ? max( 1, (int) $params['n'] ) : 1;
		$params['n']                = 1;
		$this->last_params          = $params;

		return $params;
	}

	/**
	 * Parse the first response, then replay for additional candidates.
	 *
	 * @param Response $response The response from the first API call.
	 * @return GenerativeAiResult Merged result with N candidates.
	 */
	protected function parseResponseToGenerativeAiResult( Response $response ): GenerativeAiResult {
		$first_result = parent::parseResponseToGenerativeAiResult( $response );

		if ( $this->requested_candidates <= 1 || null === $this->last_params ) {
			return $first_result;
		}

		$all_candidates = $first_result->getCandidates();
		$remaining      = $this->requested_candidates - 1;

		for ( $i = 0; $i < $remaining; $i++ ) {
			try {
				$request  = $this->createRequest(
					HttpMethodEnum::POST(),
					'chat/completions',
					[ 'Content-Type' => 'application/json' ],
					$this->last_params
				);
				$request  = $this->getRequestAuthentication()->authenticateRequest( $request );
				$extra    = $this->getHttpTransporter()->send( $request );
				$this->throwIfNotSuccessful( $extra );
				$parsed   = parent::parseResponseToGenerativeAiResult( $extra );

				foreach ( $parsed->getCandidates() as $candidate ) {
					$all_candidates[] = $candidate;
				}
			} catch ( \Exception $e ) {
				// Stop issuing requests on first failure.
				break;
			}
		}

		return new GenerativeAiResult(
			$first_result->getId(),
			$all_candidates,
			$first_result->getTokenUsage(),
			$first_result->getProviderMetadata(),
			$first_result->getModelMetadata(),
			$first_result->getAdditionalData()
		);
	}

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
