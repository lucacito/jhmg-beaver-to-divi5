<?php

use PHPUnit\Framework\TestCase;
use BeaverDivi5Converter\Parsers\BeaverDocumentParser;
use BeaverDivi5Converter\Parsers\NodeTree;

final class NodeTreeTest extends TestCase {

    private function node( string $id, string $type, ?string $parent, int $position, array $settings = [] ): array {
        return [ 'node' => $id, 'type' => $type, 'parent' => $parent, 'position' => $position, 'settings' => $settings ];
    }

    public function test_it_nests_rows_groups_columns_and_modules_in_position_order(): void {
        $nodes = [
            'm2'  => $this->node( 'm2', 'module', 'c1', 1, [ 'type' => 'rich-text' ] ),
            'r2'  => $this->node( 'r2', 'row', null, 1 ),
            'r1'  => $this->node( 'r1', 'row', null, 0 ),
            'g1'  => $this->node( 'g1', 'column-group', 'r1', 0 ),
            'c1'  => $this->node( 'c1', 'column', 'g1', 0, [ 'size' => 100 ] ),
            'm1'  => $this->node( 'm1', 'module', 'c1', 0, [ 'type' => 'heading' ] ),
        ];

        $tree = NodeTree::build( $nodes );

        $this->assertSame( [], $tree['orphans'] );
        $this->assertSame( [ 'r1', 'r2' ], array_column( $tree['roots'], 'id' ) );
        $group = $tree['roots'][0]['children'][0];
        $this->assertSame( 'column-group', $group['type'] );
        $column = $group['children'][0];
        $this->assertSame( 'column', $column['type'] );
        $this->assertSame( [ 'm1', 'm2' ], array_column( $column['children'], 'id' ) );
        $this->assertSame( 'heading', $column['children'][0]['settings']['type'] );
    }

    public function test_box_children_nest_under_the_box_module(): void {
        $nodes = [
            'r1' => $this->node( 'r1', 'row', null, 0 ),
            'g1' => $this->node( 'g1', 'column-group', 'r1', 0 ),
            'c1' => $this->node( 'c1', 'column', 'g1', 0 ),
            'b1' => $this->node( 'b1', 'module', 'c1', 0, [ 'type' => 'box' ] ),
            'm1' => $this->node( 'm1', 'module', 'b1', 0, [ 'type' => 'heading' ] ),
        ];

        $tree = NodeTree::build( $nodes );
        $box  = $tree['roots'][0]['children'][0]['children'][0]['children'][0];

        $this->assertSame( 'box', $box['settings']['type'] );
        $this->assertSame( 'm1', $box['children'][0]['id'] );
    }

    public function test_orphans_are_kept_at_the_root_and_reported(): void {
        $nodes = [
            'r1' => $this->node( 'r1', 'row', null, 0 ),
            'm9' => $this->node( 'm9', 'module', 'missing', 0, [ 'type' => 'heading' ] ),
        ];

        $tree = NodeTree::build( $nodes );

        $this->assertSame( [ 'm9' ], $tree['orphans'] );
        $this->assertSame( [ 'r1', 'm9' ], array_column( $tree['roots'], 'id' ) );
    }

    public function test_a_parent_cycle_does_not_recurse_forever(): void {
        $nodes = [
            'a' => $this->node( 'a', 'row', 'b', 0 ),
            'b' => $this->node( 'b', 'column-group', 'a', 0 ),
        ];

        $tree = NodeTree::build( $nodes );

        $this->assertSame( [], $tree['roots'], 'nodes that only point at each other never reach a root' );
    }

    public function test_module_census_counts_slugs(): void {
        $file  = __DIR__ . '/../fixtures/beaver-templates/contact.json';
        $nodes = ( new BeaverDocumentParser() )->parseValue( (string) file_get_contents( $file ) );
        $tree  = NodeTree::build( $nodes );

        $this->assertSame( 3, count( $tree['roots'] ) );
        $this->assertEquals( [ 'heading' => 3, 'rich-text' => 3, 'button' => 3, 'html' => 1 ], NodeTree::moduleCensus( $tree['roots'] ) );
    }
}
