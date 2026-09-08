<?php

use PHPUnit\Framework\TestCase;
use BeaverDivi5Converter\Parsers\BeaverDocumentParser;

final class BeaverDocumentParserTest extends TestCase {

    private function sampleObjects(): array {
        $row = new stdClass();
        $row->node     = 'row1';
        $row->type     = 'row';
        $row->parent   = null;
        $row->position = 0;
        $row->settings = (object) [ 'width' => 'full', 'bg_color' => '64A6BD' ];

        $group = new stdClass();
        $group->node     = 'grp1';
        $group->type     = 'column-group';
        $group->parent   = 'row1';
        $group->position = 0;
        $group->settings = ''; // Beaver Builder stores an empty string here.

        return [ 'row1' => $row, 'grp1' => $group ];
    }

    public function test_it_normalises_stdclass_objects_into_arrays(): void {
        $nodes = ( new BeaverDocumentParser() )->parseValue( $this->sampleObjects() );

        $this->assertSame( [ 'row1', 'grp1' ], array_keys( $nodes ) );
        $this->assertSame( 'row', $nodes['row1']['type'] );
        $this->assertNull( $nodes['row1']['parent'] );
        $this->assertSame( [ 'width' => 'full', 'bg_color' => '64A6BD' ], $nodes['row1']['settings'] );
        $this->assertSame( 'row1', $nodes['grp1']['parent'] );
        $this->assertSame( [], $nodes['grp1']['settings'], 'an empty-string settings value becomes an empty array' );
    }

    public function test_it_reads_a_serialized_string(): void {
        $nodes = ( new BeaverDocumentParser() )->parseValue( serialize( $this->sampleObjects() ) );

        $this->assertCount( 2, $nodes );
        $this->assertSame( 'column-group', $nodes['grp1']['type'] );
    }

    public function test_it_reads_json(): void {
        $json  = json_encode( [ 'nodes' => [ 'm1' => [ 'node' => 'm1', 'type' => 'module', 'parent' => 'c1', 'position' => 2, 'settings' => [ 'type' => 'heading', 'heading' => 'Hi' ] ] ] ] );
        $nodes = ( new BeaverDocumentParser() )->parseValue( $json );

        $this->assertSame( 'Hi', $nodes['m1']['settings']['heading'] );
        $this->assertSame( 2, $nodes['m1']['position'] );
    }

    public function test_it_refuses_serialized_payloads_carrying_other_classes(): void {
        $payload = 'a:1:{s:4:"row1";O:9:"Evil_Load":1:{s:4:"node";s:4:"row1";}}';

        $this->assertSame( [], ( new BeaverDocumentParser() )->parseValue( $payload ) );
    }

    public function test_nodes_without_a_type_are_dropped(): void {
        $nodes = ( new BeaverDocumentParser() )->parseValue( [ 'a' => [ 'node' => 'a' ], 'b' => 'junk', 'c' => [ 'node' => 'c', 'type' => 'row' ] ] );

        $this->assertSame( [ 'c' ], array_keys( $nodes ) );
    }

    public function test_parse_reads_the_meta_keys_in_get_post_meta_shape(): void {
        $meta = [
            '_fl_builder_data'          => [ serialize( $this->sampleObjects() ) ],
            '_fl_builder_data_settings' => [ serialize( (object) [ 'css' => '.x{}', 'js' => '' ] ) ],
            '_fl_builder_enabled'       => [ '1' ],
        ];

        $doc = ( new BeaverDocumentParser() )->parse( $meta );

        $this->assertCount( 2, $doc['nodes'] );
        $this->assertSame( '.x{}', $doc['settings']['css'] );
    }

    public function test_parse_accepts_the_value_as_wordpress_returns_it_unserialized(): void {
        $doc = ( new BeaverDocumentParser() )->parse( [ '_fl_builder_data' => [ $this->sampleObjects() ] ] );

        $this->assertCount( 2, $doc['nodes'] );
        $this->assertSame( [], $doc['settings'] );
    }

    public function test_empty_meta_yields_no_nodes(): void {
        $this->assertSame( [ 'nodes' => [], 'settings' => [] ], ( new BeaverDocumentParser() )->parse( [] ) );
        $this->assertSame( [], ( new BeaverDocumentParser() )->parseValue( '' ) );
        $this->assertSame( [], ( new BeaverDocumentParser() )->parseValue( null ) );
    }

    public function test_bundled_template_fixtures_parse(): void {
        $file  = __DIR__ . '/../fixtures/beaver-templates/contact.json';
        $nodes = ( new BeaverDocumentParser() )->parseValue( (string) file_get_contents( $file ) );

        $this->assertCount( 22, $nodes );
        $this->assertSame( 'row', $nodes['5e3b3c684929a']['type'] );
    }
}
