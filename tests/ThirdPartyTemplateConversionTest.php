<?php

use PHPUnit\Framework\TestCase;
use BeaverDivi5Converter\Converter\ConverterEngine;
use BeaverDivi5Converter\Exporters\DiviBlockSerializer;
use BeaverDivi5Converter\Parsers\BeaverImportParser;
use Divi5Validator\Validator;

/**
 * Real Beaver Builder exports (WXR files in `beaver templates/`) from a site
 * running Ultimate Addons, PowerPack and Beaver Builder Pro. Every one must
 * convert to a validator-clean Divi 5 document with no placeholder modules and
 * no setting the converter could not account for.
 */
final class ThirdPartyTemplateConversionTest extends TestCase {

    protected function setUp(): void {
        bdc_test_reset_hooks();
    }

    public static function templates(): array {
        $cases = [];
        foreach ( glob( __DIR__ . '/../beaver templates/*.xml' ) ?: [] as $file ) {
            $cases[ basename( $file ) ] = [ $file ];
        }
        return $cases;
    }

    #[\PHPUnit\Framework\Attributes\DataProvider( 'templates' )]
    public function test_template_converts_clean( string $file ): void {
        $items = ( new BeaverImportParser() )->parse( $file, basename( $file ) );
        $this->assertCount( 1, $items, 'each export holds one layout' );
        $item = $items[0];
        $this->assertSame( '', (string) ( $item['error'] ?? '' ) );
        $this->assertNotEmpty( $item['nodes'] );

        $result = ( new ConverterEngine() )->convert( [ 'nodes' => $item['nodes'], 'settings' => $item['settings'] ?? [] ] );
        $report = $result['report'];

        $this->assertSame( [], $result['unsupported'], 'no module may fall back to a placeholder' );
        $placeholders = array_filter( $report['warnings'], static fn( string $w ) => str_contains( $w, 'no Divi 5 equivalent' ) );
        $this->assertSame( [], array_values( $placeholders ) );
        $this->assertSame( [], $report['skipped_settings'], 'every setting is mapped, consumed, or classified as an inert add-on default' );
        $this->assertNotEmpty( $report['addon_settings_ignored'], 'these exports carry Ultimate Addons / PowerPack defaults on every row' );

        $content    = ( new DiviBlockSerializer() )->serialize( $result );
        $violations = array_filter(
            ( new Validator() )->validateContent( $content )->violations(),
            static fn( $v ) => $v->code() !== Validator::E_MULTIPLE_H1
        );
        $this->assertSame( [], array_map( static fn( $v ) => $v->code() . ': ' . $v->message(), $violations ), 'Divi 5 validator violations' );
    }
}
