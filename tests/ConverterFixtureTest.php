<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use BeaverDivi5Converter\Converter\ConverterEngine;

/**
 * Every fixtures/beaver/<name>.json must convert to exactly
 * fixtures/divi/<name>.json. The expected files are reviewed by hand and
 * regenerated with scripts/update-expected.php only when a change is intended.
 */
final class ConverterFixtureTest extends TestCase {

    public static function fixtureProvider(): array {
        $cases = [];
        foreach ( glob( __DIR__ . '/../fixtures/beaver/*.json' ) ?: [] as $file ) {
            $name           = basename( $file, '.json' );
            $cases[ $name ] = [ $name ];
        }
        return $cases;
    }

    #[DataProvider( 'fixtureProvider' )]
    public function test_converter_matches_expected_fixture( string $name ): void {
        bbdc_test_reset_hooks();

        $expected_file = __DIR__ . "/../fixtures/divi/{$name}.json";
        $this->assertFileExists( $expected_file, "No expected output for fixture '{$name}'. Review scripts/render-fixture.php output, then run scripts/update-expected.php {$name}." );

        $payload  = json_decode( (string) file_get_contents( __DIR__ . "/../fixtures/beaver/{$name}.json" ), true );
        $expected = json_decode( (string) file_get_contents( $expected_file ), true );

        $result = ( new ConverterEngine() )->convert( $payload );

        $this->assertEquals( $expected, [ 'divi' => $result['divi'], 'unsupported' => $result['unsupported'] ], "Converter output did not match fixtures/divi/{$name}.json" );
    }
}
