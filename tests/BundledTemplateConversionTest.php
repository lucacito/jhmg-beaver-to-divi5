<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use BeaverDivi5Converter\Converter\ConverterEngine;
use BeaverDivi5Converter\Exporters\DiviBlockSerializer;
use BeaverDivi5Converter\Parsers\BeaverDocumentParser;
use BeaverDivi5Converter\Parsers\NodeTree;
use Divi5Validator\Validator;

/**
 * Every layout template Beaver Builder Lite ships with (data/layout-*.dat,
 * exported to fixtures/beaver-templates/) must convert into a Divi 5 document
 * the deterministic validator accepts, keep every visible string, and keep the
 * page's section count.
 */
final class BundledTemplateConversionTest extends TestCase {

    public static function templateProvider(): array {
        $cases = [];
        foreach ( glob( __DIR__ . '/../fixtures/beaver-templates/*.json' ) ?: [] as $file ) {
            $cases[ basename( $file, '.json' ) ] = [ $file ];
        }
        return $cases;
    }

    #[DataProvider( 'templateProvider' )]
    public function test_bundled_template_converts_to_valid_divi_5( string $file ): void {
        bdc_test_reset_hooks();

        $payload = json_decode( (string) file_get_contents( $file ), true );
        $engine  = new ConverterEngine();
        $result  = $engine->convert( $payload );
        $content = ( new DiviBlockSerializer() )->serialize( $result );

        // 1. The validator accepts it (a template may legitimately carry several h1s).
        $violations = array_values( array_filter(
            ( new Validator() )->validateContent( $content )->violations(),
            static fn( $v ) => $v->code() !== Validator::E_MULTIPLE_H1
        ) );
        $this->assertSame( [], array_map( static fn( $v ) => $v->code() . ' ' . $v->message() . ' @ ' . $v->path(), $violations ), basename( $file ) . ' produced an invalid Divi 5 document' );

        // 2. Nothing unsupported: every module in the Lite templates has a handler.
        $this->assertSame( [], $result['unsupported'], basename( $file ) . ' has unsupported modules' );

        // 3. One section per Beaver Builder row.
        $nodes = ( new BeaverDocumentParser() )->parseValue( $payload );
        $rows  = count( array_filter( $nodes, static fn( array $n ) => $n['type'] === 'row' ) );
        $this->assertCount( $rows, $result['divi']['elements'], basename( $file ) . ' section count' );

        // 4. Every heading, button label and text block survives.
        foreach ( self::visibleStrings( NodeTree::build( $nodes )['roots'] ) as $string ) {
            $needle = json_encode( $string, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG );
            $needle = trim( (string) $needle, '"' );
            $this->assertStringContainsString( $needle, $content, basename( $file ) . " lost the text: {$string}" );
        }

        // 5. Nothing silently skipped on the modules these templates use.
        $this->assertSame( [], $result['report']['skipped_settings'], basename( $file ) . ' skipped settings' );
    }

    /** @return string[] Heading text, button labels and plain-text fragments of rich text. */
    private static function visibleStrings( array $tree ): array {
        $strings = [];
        $walk    = static function ( array $nodes ) use ( &$walk, &$strings ): void {
            foreach ( $nodes as $node ) {
                $s = $node['settings'] ?? [];
                if ( ( $node['type'] ?? '' ) === 'module' ) {
                    foreach ( [ 'heading', 'text' ] as $key ) {
                        if ( ( $s['type'] ?? '' ) === 'button' && $key === 'heading' ) {
                            continue;
                        }
                        $value = $s[ $key ] ?? '';
                        if ( is_string( $value ) && trim( strip_tags( $value ) ) !== '' && ! str_contains( $value, '<' ) && ! str_contains( $value, '&' ) ) {
                            $strings[] = trim( $value );
                        }
                    }
                }
                $walk( $node['children'] ?? [] );
            }
        };
        $walk( $tree );
        return array_values( array_unique( $strings ) );
    }
}
