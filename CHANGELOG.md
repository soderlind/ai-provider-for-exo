# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [0.1.0] - 2026-04-09

### Added

- Register exo as a WordPress AI provider via the AI Client SDK.
- OpenAI-compatible text generation through exo's chat completions API.
- Auto-detect active models from the exo cluster (REST endpoint: `POST /exo/v1/detect`).
- Connector settings UI with "Connect & Detect" / "Save & Re-detect" flow.
- Detected active models displayed in a read-only panel with Refresh support.
- Optional API key authentication with secure storage and masking.
- Configurable endpoint URL (default: `http://localhost:52415`).
- Environment variable and `wp-config.php` constant support (`EXO_ENDPOINT`, `EXO_API_KEY`, `EXO_MODEL`).
- Full i18n support with Norwegian Bokmål (nb_NO) translation.

[0.1.0]: https://github.com/soderlind/ai-provider-for-exo/releases/tag/0.1.0
