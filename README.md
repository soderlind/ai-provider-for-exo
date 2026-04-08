# AI Provider for exo

Connect WordPress to [exo](https://github.com/exo-explore/exo) — run frontier AI models locally on your device cluster.

![WordPress 7.0+](https://img.shields.io/badge/WordPress-7.0%2B-blue)
![PHP 8.3+](https://img.shields.io/badge/PHP-8.3%2B-purple)
![License: GPL-2.0-or-later](https://img.shields.io/badge/License-GPL--2.0--or--later-green)

## Description

This plugin registers **exo** as an AI provider in WordPress 7's AI Client SDK and Connectors page.

exo connects all your devices into an AI cluster, enabling you to run frontier models locally. It exposes an OpenAI-compatible API that this plugin connects to.

<img width="100%"  alt="Screenshot 2026-04-09 at 00 56 51" src="https://github.com/user-attachments/assets/cd369677-14ec-420c-ad42-6938c00d55a9" />


### Features

- Registers exo as a WordPress AI provider
- OpenAI-compatible text generation via exo's chat completions API
- **Auto-detect active models** from your running exo cluster
- **Capability detection** — displays model capabilities (Text, Code, Thinking, Vision) as badges
- "Connect & Detect" / "Save & Re-detect" connector flow
- Optional API key authentication with secure storage
- Configurable endpoint URL (default: `http://localhost:52415`)
- Settings integrated into WordPress 7's Connectors page
- Full i18n support with Norwegian Bokmål (nb_NO) translation

## Requirements

- WordPress 7.0 or later
- PHP 8.3 or later
- A running [exo](https://github.com/exo-explore/exo) cluster

## Installation

1. Upload the `ai-provider-for-exo` folder to `/wp-content/plugins/`.
2. Activate the plugin through the Plugins menu in WordPress.
3. Go to **Settings → Connectors** and configure your exo endpoint.
4. Click **Connect & Detect** to auto-discover active models and capabilities.

## Configuration

The plugin can be configured via the Connectors page or environment variables:

| Setting | Environment Variable | Default |
|---------|---------------------|---------|
| Endpoint URL | `EXO_ENDPOINT` | `http://localhost:52415` |
| API Key | `EXO_API_KEY` | _(none)_ |
| Model | `EXO_MODEL` | _(auto-detected)_ |

You can also define these as constants in `wp-config.php`:

```php
define( 'EXO_ENDPOINT', 'http://localhost:52415' );
define( 'EXO_API_KEY', 'your-key-here' );
define( 'EXO_MODEL', 'model-name' );
```

## Development

```bash
# Install dependencies
composer install
npm install

# Build
npm run build

# Watch
npm start

# Run tests
composer test    # PHPUnit
npm test         # Vitest

# i18n
npm run i18n
```

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for a detailed list of changes.

## License

GPL-2.0-or-later. See [LICENSE](https://www.gnu.org/licenses/gpl-2.0.html).
