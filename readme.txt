=== AI Provider for exo ===
Contributors: PerS
Tags: ai, exo, local-ai, llm, connectors
Requires at least: 7.0
Tested up to: 7.0
Requires PHP: 8.3
Stable tag: 0.1.0
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
