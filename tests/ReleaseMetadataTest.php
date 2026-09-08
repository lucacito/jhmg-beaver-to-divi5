<?php

use PHPUnit\Framework\TestCase;
use BeaverDivi5Converter\Telemetry\CoverageTelemetry;

final class ReleaseMetadataTest extends TestCase {
    private const FREE = __DIR__ . '/../plugin/jhmg-converter-for-beaver-builder-to-divi';

    public function test_version_is_consistent_across_header_constant_and_readme(): void {
        $main   = (string) file_get_contents( self::FREE . '/jhmg-converter-for-beaver-builder-to-divi.php' );
        $readme = (string) file_get_contents( self::FREE . '/readme.txt' );

        $this->assertMatchesRegularExpression( '/^\s*\*\s*Version:\s*' . preg_quote( BBDC_PLUGIN_VERSION, '/' ) . '\s*$/m', $main );
        $this->assertStringContainsString( "BBDC_PLUGIN_VERSION', '" . BBDC_PLUGIN_VERSION . "'", $main );
        $this->assertMatchesRegularExpression( '/^Stable tag:\s*' . preg_quote( BBDC_PLUGIN_VERSION, '/' ) . '\s*$/m', $readme );
        $this->assertStringContainsString( '= ' . BBDC_PLUGIN_VERSION . ' =', $readme );
    }

    public function test_readme_discloses_the_external_service_and_every_payload_key(): void {
        $readme = (string) file_get_contents( self::FREE . '/readme.txt' );

        $this->assertStringContainsString( 'External services', $readme );
        $this->assertStringContainsString( CoverageTelemetry::ENDPOINT, $readme );
        $this->assertStringContainsString( 'opt-in', $readme );
        $this->assertStringContainsString( 'Nothing else', $readme );
        foreach ( array_keys( ( new CoverageTelemetry() )->payload() ) as $key ) {
            $this->assertStringContainsString( $key, $readme, "payload key '$key' must be disclosed" );
        }
    }

    public function test_readme_lists_every_lite_module_the_registry_handles(): void {
        $readme = strtolower( (string) file_get_contents( self::FREE . '/readme.txt' ) );
        foreach ( [ 'heading', 'text editor', 'photo', 'button group', 'html', 'video', 'audio', 'sidebar', 'icon', 'callout', 'call to action', 'number counter', 'star rating', 'menu', 'box' ] as $module ) {
            $this->assertStringContainsString( $module, $readme, $module );
        }
    }

    public function test_readme_does_not_promise_a_visual_preview(): void {
        $readme = (string) file_get_contents( self::FREE . '/readme.txt' );
        $this->assertStringNotContainsString( 'visual preview', $readme );
        $this->assertStringNotContainsString( 'pixel', $readme );
    }
}
