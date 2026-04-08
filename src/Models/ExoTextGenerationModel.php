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

use WordPress\AiClient\Providers\OpenAiCompatibleImplementation\AbstractOpenAiCompatibleTextGenerationModel;

class ExoTextGenerationModel extends AbstractOpenAiCompatibleTextGenerationModel {
	// The base class handles everything for standard OpenAI-compatible APIs.
}
