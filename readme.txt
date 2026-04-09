=== AI Provider for exo ===
Contributors: PerS
Tags: ai, exo, local-ai, llm, connector
Requires at least: 7.0
Tested up to: 7.0
Requires PHP: 8.3
Stable tag: 0.2.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Connect WordPress to exo — run frontier AI models locally on your device cluster.

== Description ==

This plugin registers **exo** as an AI provider in WordPress 7's AI Client SDK and Connectors page.

[exo](https://github.com/exo-explore/exo) connects all your devices into an AI cluster, enabling you to run frontier models locally. It exposes an OpenAI-compatible API that this plugin connects to.

**Features:**

* Registers exo as a WordPress AI provider
* OpenAI-compatible text generation via exo's chat completions API
* Auto-detect active models from your running exo cluster
* Capability detection — displays model capabilities (Text, Code, Thinking, Vision) as badges
* "Connect & Detect" / "Save & Re-detect" connector flow
* Optional API key authentication with secure storage
* Configurable endpoint URL (default: `http://localhost:52415`)
* Settings integrated into WordPress 7's Connectors page

**Choosing a Model:**

exo exposes every model in its catalog, but only models actively loaded on your cluster will respond. Use "Connect & Detect" on the Connectors page to discover which models are running.

Recommended — **Instruct models** produce clean, usable output for WordPress AI features (title generation, content suggestions, etc.):

* `Llama-3.2-3B-Instruct-8bit` — ~3 GB, fast, great for short tasks
* `Meta-Llama-3.1-8B-Instruct-4bit` — ~4 GB, good balance of speed and quality
* `Llama-3.3-70B-Instruct-4bit` — ~35 GB, best quality, needs a larger cluster

Avoid — **Reasoning/thinking models** (Qwen3.5, DeepSeek, GLM, Nemotron-Nano) spend most tokens on internal chain-of-thought, producing slow responses with minimal visible output.

To load a model: `exo run mlx-community/Llama-3.2-3B-Instruct-8bit`

**Requirements:**

* WordPress 7.0 or later
* PHP 8.3 or later
* A running exo cluster (see [exo documentation](https://github.com/exo-explore/exo))

== Installation ==

1. Upload the `ai-provider-for-exo` folder to `/wp-content/plugins/`.
2. Activate the plugin through the Plugins menu in WordPress.
3. Go to **Settings → Connectors** and configure your exo endpoint.
4. Start using AI features powered by your local exo cluster.

== Configuration ==

The plugin can be configured via the Connectors page or environment variables:

* `EXO_ENDPOINT` — exo API endpoint (default: `http://localhost:52415`)
* `EXO_API_KEY` — Optional API key for authentication
* `EXO_MODEL` — Model name to use (auto-detected if empty)

You can also define these as constants in `wp-config.php`.

== Changelog ==

= 0.2.0 =
* Add sentinel connector for WordPress AI plugin compatibility.
* AI plugin now recognizes exo as a valid, configured provider.
* Filter to hide internal sentinel connector from Connectors settings page.
* Custom exo wordmark logo (40×40 square) for the Connectors page.
* Fix: AI plugin showed "requires a valid AI Connector" despite exo being configured.

= 0.1.0 =
* Initial release.
* Register exo as a WordPress AI provider via the AI Client SDK.
* OpenAI-compatible text generation through exo's chat completions API.
* Auto-detect active models from the exo cluster (REST endpoint: POST /exo/v1/detect).
* Capability detection — captures and displays model capabilities (Text, Code, Thinking, Vision) as badges.
* Connector settings UI with "Connect & Detect" / "Save & Re-detect" flow.
* Detected active models displayed in a read-only panel with Refresh support.
* Custom exo logo icon for the Connectors page.
* Optional API key authentication with secure storage and masking.
* Configurable endpoint URL (default: http://localhost:52415).
* Environment variable and wp-config.php constant support (EXO_ENDPOINT, EXO_API_KEY, EXO_MODEL).
* Full i18n support with Norwegian Bokmål (nb_NO) translation.
