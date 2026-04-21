<?php
declare(strict_types=1);

namespace Aiprfoex\Tests\Unit\Settings;

use Aiprfoex\Settings\ConnectorSettings;
use Aiprfoex\Tests\TestCase;

class ConnectorSettingsTest extends TestCase {

	public function test_mask_api_key_returns_empty_for_empty_string(): void {
		$result = ConnectorSettings::mask_api_key( '' );
		$this->assertSame( '', $result );
	}

	public function test_mask_api_key_returns_short_keys_unchanged(): void {
		$this->assertSame( 'abc', ConnectorSettings::mask_api_key( 'abc' ) );
		$this->assertSame( 'abcd', ConnectorSettings::mask_api_key( 'abcd' ) );
	}

	public function test_mask_api_key_masks_longer_keys(): void {
		$key    = 'sk-1234567890abcdef';
		$result = ConnectorSettings::mask_api_key( $key );

		$this->assertStringEndsWith( 'cdef', $result );
		$this->assertStringContainsString( "\u{2022}", $result );

		$masked_length    = strlen( $key ) - 4;
		$expected_bullets = min( $masked_length, 16 );
		$this->assertSame( $expected_bullets + 4, mb_strlen( $result ) );
	}

	public function test_mask_api_key_handles_non_string_input(): void {
		$this->assertSame( '', ConnectorSettings::mask_api_key( null ) );
		$this->assertSame( '', ConnectorSettings::mask_api_key( 123 ) );
		$this->assertSame( '', ConnectorSettings::mask_api_key( [] ) );
	}
}
