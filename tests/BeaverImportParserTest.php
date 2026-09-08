<?php

use PHPUnit\Framework\TestCase;
use BeaverDivi5Converter\Admin\BatchImporter;
use BeaverDivi5Converter\Parsers\BeaverImportParser;
use BeaverDivi5Converter\Parsers\WxrReader;

final class BeaverImportParserTest extends TestCase {

    private const DIR = __DIR__ . '/../fixtures/beaver-import/';

    protected function setUp(): void {
        bdc_test_reset_hooks();
        $GLOBALS['__test_posts']    = [];
        $GLOBALS['__test_postmeta'] = [];
    }

    public function test_wxr_reader_returns_posts_with_their_meta(): void {
        $items = ( new WxrReader() )->read( (string) file_get_contents( self::DIR . 'export.xml' ) );

        $this->assertCount( 2, $items );
        $this->assertSame( 'About Us', $items[0]['title'] );
        $this->assertSame( 'page', $items[0]['post_type'] );
        $this->assertSame( 'about-us', $items[0]['post_name'] );
        $this->assertSame( '1', $items[0]['meta']['_fl_builder_enabled'] );
        $this->assertStringStartsWith( 'a:', $items[0]['meta']['_fl_builder_data'] );
        $this->assertSame( [], $items[1]['meta'] );
    }

    public function test_wxr_reader_refuses_doctype_and_entities(): void {
        $this->expectException( \RuntimeException::class );
        $this->expectExceptionMessage( 'DOCTYPE' );
        ( new WxrReader() )->read( (string) file_get_contents( self::DIR . 'entity.xml' ) );
    }

    public function test_wxr_reader_refuses_malformed_xml(): void {
        $this->expectException( \RuntimeException::class );
        ( new WxrReader() )->read( '<?xml version="1.0"?><rss><channel><item>' );
    }

    public function test_wordpress_export_yields_only_beaver_builder_posts(): void {
        $items = ( new BeaverImportParser() )->parse( self::DIR . 'export.xml', 'export.xml' );

        $this->assertCount( 1, $items, 'the plain post has no layout and is skipped' );
        $this->assertSame( 'About Us', $items[0]['title'] );
        $this->assertSame( 'about-us', $items[0]['post_name'] );
        $this->assertCount( 6, $items[0]['nodes'] );
        $this->assertSame( 'row', $items[0]['nodes']['row1']['type'] );
        $this->assertSame( '.fl-row { color: red; }', $items[0]['settings']['css'] );
        $this->assertSame( [ 'kind' => 'upload', 'post_id' => null, 'file' => 'export.xml' ], $items[0]['source_ref'] );
    }

    public function test_themer_layouts_and_posts_keep_their_type(): void {
        $items = ( new BeaverImportParser() )->parse( self::DIR . 'two-pages.xml', 'two-pages.xml' );

        $this->assertCount( 3, $items );
        $this->assertSame( 'header', $items[1]['template_type'] );
        $this->assertSame( 'page', $items[1]['post_type'] );
        $this->assertSame( 'post', $items[2]['post_type'] );
    }

    public function test_beaver_builder_template_packs_are_read(): void {
        $items = ( new BeaverImportParser() )->parse( self::DIR . 'contact-template.dat', 'contact-template.dat' );

        $this->assertCount( 1, $items );
        $this->assertSame( 'Contact', $items[0]['title'] );
        $this->assertCount( 22, $items[0]['nodes'] );
    }

    public function test_json_documents_are_read(): void {
        $items = ( new BeaverImportParser() )->parse( self::DIR . 'tree.json', 'tree.json' );

        $this->assertSame( 'From JSON', $items[0]['title'] );
        $this->assertCount( 6, $items[0]['nodes'] );
    }

    public function test_unrecognised_and_empty_files_throw(): void {
        $tmp = tempnam( sys_get_temp_dir(), 'bdc' );
        file_put_contents( $tmp, 'hello' );
        try {
            ( new BeaverImportParser() )->parse( $tmp, 'notes.txt' );
            $this->fail( 'expected an exception' );
        } catch ( \RuntimeException $e ) {
            $this->assertStringContainsString( 'Unrecognised file type', $e->getMessage() );
        }
        file_put_contents( $tmp, '<?xml version="1.0"?><rss><channel><item><title>x</title></item></channel></rss>' );
        try {
            ( new BeaverImportParser() )->parse( $tmp, 'x.xml' );
            $this->fail( 'expected an exception' );
        } catch ( \RuntimeException $e ) {
            $this->assertStringContainsString( 'No Beaver Builder layouts found', $e->getMessage() );
        }
        unlink( $tmp );
    }

    public function test_serialized_payloads_with_foreign_classes_are_refused(): void {
        $tmp = tempnam( sys_get_temp_dir(), 'bdc' );
        file_put_contents( $tmp, 'a:1:{s:6:"layout";a:1:{i:0;O:4:"Evil":0:{}}}' );
        $this->expectException( \RuntimeException::class );
        try {
            ( new BeaverImportParser() )->parse( $tmp, 'evil.dat' );
        } finally {
            unlink( $tmp );
        }
    }

    public function test_batch_importer_converts_the_first_page_and_explains_the_rest(): void {
        $items   = ( new BeaverImportParser() )->parse( self::DIR . 'two-pages.xml', 'two-pages.xml' );
        $results = ( new BatchImporter() )->import( $items, [ 'post_status' => 'draft', 'post_type' => 'page' ] );

        $this->assertCount( 2, $results );
        $this->assertTrue( $results[0]['success'] );
        $this->assertSame( 'file_upload', get_post_meta( $results[0]['post_id'], '_bdc_import_source', true ) );
        $this->assertTrue( $results[1]['skipped'] );
        $this->assertStringContainsString( '2 more pages', $results[1]['title'] );
    }

    public function test_batch_importer_converts_everything_when_the_limit_is_raised(): void {
        add_filter( 'bdc_direct_conversion_limit', fn() => PHP_INT_MAX );
        $items   = ( new BeaverImportParser() )->parse( self::DIR . 'two-pages.xml', 'two-pages.xml' );
        $results = ( new BatchImporter() )->import( $items );

        $this->assertCount( 3, $results );
        $this->assertSame( 'post', get_post( $results[2]['post_id'] )->post_type, 'without a post_type option the item keeps its own type' );
        $this->assertStringContainsString( 'requires the Pro add-on', end( $results[1]['report']['warnings'] ) );
    }
}
