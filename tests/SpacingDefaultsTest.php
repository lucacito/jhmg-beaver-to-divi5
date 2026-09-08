<?php

use PHPUnit\Framework\TestCase;
use BeaverDivi5Converter\Converter\ConverterEngine;
use BeaverDivi5Converter\StyleMapper\GlobalSettingsResolver;

/**
 * Beaver Builder's box model, reproduced rather than left to Divi's defaults:
 * rows pad 20px all round, column groups and columns add nothing, modules carry
 * 20px margins that never collapse (Beaver's clearfix), and flex-row columns get
 * the 24-grid `flexType` Divi 5 sizes them by.
 */
final class SpacingDefaultsTest extends TestCase {

    protected function setUp(): void {
        bbdc_test_reset_hooks();
        delete_option( GlobalSettingsResolver::OPTION );
    }

    private function convert( array $row, array $columns, array $modules ): array {
        $nodes = [
            'row1' => [ 'node' => 'row1', 'type' => 'row', 'parent' => null, 'position' => 0, 'settings' => $row ],
            'grp1' => [ 'node' => 'grp1', 'type' => 'column-group', 'parent' => 'row1', 'position' => 0, 'settings' => '' ],
        ];
        foreach ( $columns as $i => $column ) {
            $nodes[ 'col' . ( $i + 1 ) ] = [ 'node' => 'col' . ( $i + 1 ), 'type' => 'column', 'parent' => 'grp1', 'position' => $i, 'settings' => $column ];
        }
        foreach ( $modules as $i => $module ) {
            $nodes[ 'mod' . ( $i + 1 ) ] = [ 'node' => 'mod' . ( $i + 1 ), 'type' => 'module', 'parent' => 'col1', 'position' => $i, 'settings' => $module ];
        }
        return ( new ConverterEngine() )->convert( [ 'nodes' => $nodes ] );
    }

    private function section( array $result ): array {
        return $result['divi']['elements'][0];
    }

    public function test_section_takes_beaver_global_row_padding_for_blank_sides(): void {
        $section = $this->section( $this->convert( [ 'padding_top' => '60', 'padding_unit' => 'px' ], [ [ 'size' => 100 ] ], [ [ 'type' => 'heading', 'heading' => 'Hi' ] ] ) );
        $padding = $section['settings']['module']['decoration']['spacing']['desktop']['value']['padding'];

        $this->assertSame( '60px', $padding['top'], 'an explicit side wins' );
        $this->assertSame( '20px', $padding['bottom'], "Beaver Builder's shipped row padding" );
        $this->assertSame( '20px', $padding['left'] );
    }

    public function test_site_global_row_padding_and_module_margins_are_honoured(): void {
        update_option( GlobalSettingsResolver::OPTION, (object) [ 'row_padding' => '40', 'module_margins' => '0' ] );
        $section = $this->section( $this->convert( [], [ [ 'size' => 100 ] ], [ [ 'type' => 'heading', 'heading' => 'Hi' ] ] ) );

        $this->assertSame( '40px', $section['settings']['module']['decoration']['spacing']['desktop']['value']['padding']['top'] );
        $heading = $section['elements'][0]['elements'][0]['elements'][0];
        $this->assertSame( '0px', $heading['settings']['module']['decoration']['spacing']['desktop']['value']['margin']['left'] );
    }

    public function test_divi_row_and_column_add_no_spacing_of_their_own(): void {
        $row    = $this->section( $this->convert( [], [ [ 'size' => 100 ] ], [ [ 'type' => 'heading', 'heading' => 'Hi' ] ] ) )['elements'][0];
        $column = $row['elements'][0];

        $this->assertSame( 'divi/row', $row['name'] );
        $this->assertSame( '0px', $row['settings']['module']['decoration']['spacing']['desktop']['value']['padding']['top'], "a column group has no padding in Beaver Builder; Divi's row default would add 27px" );
        $this->assertSame( '0px', $column['settings']['module']['decoration']['layout']['desktop']['value']['rowGap'], "modules stack on their own margins; Divi's 30px gap would double the spacing" );
        $this->assertArrayNotHasKey( 'spacing', $column['settings']['module']['decoration'], 'no global column padding is shipped' );
    }

    public function test_modules_get_global_margins_on_blank_sides_only(): void {
        $section = $this->section( $this->convert( [], [ [ 'size' => 100 ] ], [ [ 'type' => 'heading', 'heading' => 'Hi', 'margin_top' => '0', 'margin_bottom' => '0', 'margin_unit' => 'px' ] ] ) );
        $margin  = $section['elements'][0]['elements'][0]['elements'][0]['settings']['module']['decoration']['spacing']['desktop']['value']['margin'];

        $this->assertSame( [ 'top' => '0px', 'right' => '20px', 'bottom' => '0px', 'left' => '20px' ], array_intersect_key( $margin, array_flip( [ 'top', 'right', 'bottom', 'left' ] ) ) );
    }

    public function test_blocks_a_composite_handler_delegates_carry_only_their_own_spacing(): void {
        $section = $this->section( $this->convert( [], [ [ 'size' => 100 ] ], [ [
            'type' => 'pp-heading', 'heading_title' => 'Title', 'heading_tag' => 'h2', 'prefix_text' => 'Kicker', 'heading_sub_title' => '<p>Sub</p>',
            'heading_top_margin' => '0', 'heading_bottom_margin' => '10',
        ] ] ) );
        $blocks = $section['elements'][0]['elements'][0]['elements'];
        $this->assertSame( [ 'divi/text', 'divi/heading', 'divi/text' ], array_column( $blocks, 'name' ) );

        $prefix  = $blocks[0]['settings']['module']['decoration']['spacing']['desktop']['value']['margin'];
        $heading = $blocks[1]['settings']['module']['decoration']['spacing']['desktop']['value']['margin'];
        $sub     = $blocks[2]['settings']['module']['decoration']['spacing']['desktop']['value']['margin'];

        $this->assertSame( '20px', $prefix['top'], "the module's top margin lands on the first block" );
        $this->assertSame( '20px', $sub['bottom'], "the module's bottom margin lands on the last block" );
        $this->assertSame( '20px', $heading['left'], 'side margins inset every block' );
        $this->assertSame( '0px', $heading['top'], "the heading's own explicit margin, not a filled default" );
        $this->assertSame( '10px', $heading['bottom'] );
        $this->assertSame( '', $prefix['bottom'] ?? '', 'no default margin between pieces of one module' );
    }

    public function test_columns_get_the_24_grid_flex_type_divi_5_sizes_them_by(): void {
        $row     = $this->section( $this->convert( [], [ [ 'size' => 33.33 ], [ 'size' => 16.66 ], [ 'size' => 50 ] ], [ [ 'type' => 'heading', 'heading' => 'Hi' ] ] ) )['elements'][0];
        $columns = $row['elements'];

        $this->assertSame( [ '1_3', '1_6', '1_2' ], array_map( static fn( array $c ) => $c['settings']['module']['advanced']['type']['desktop']['value'], $columns ) );
        $this->assertSame( [ '8_24', '4_24', '12_24' ], array_map( static fn( array $c ) => $c['settings']['module']['decoration']['sizing']['desktop']['value']['flexType'], $columns ) );
    }

    public function test_column_margins_become_padding_inside_the_column_slot(): void {
        $row    = $this->section( $this->convert( [], [ [ 'size' => 100, 'margin_left' => '60', 'margin_right' => '60', 'margin_unit' => 'px', 'padding_top' => '10', 'padding_unit' => 'px' ] ], [ [ 'type' => 'heading', 'heading' => 'Hi' ] ] ) )['elements'][0];
        $column = $row['elements'][0]['settings']['module']['decoration']['spacing']['desktop']['value'];

        $this->assertSame( '60px', $column['padding']['left'] );
        $this->assertSame( '10px', $column['padding']['top'], 'explicit padding is kept' );
        $this->assertArrayNotHasKey( 'margin', $column, 'a Divi column margin would change its share of the row' );
    }

    public function test_flex_groups_give_their_margins_back_to_the_basis(): void {
        $row    = $this->section( $this->convert( [], [ [ 'size' => 30 ], [ 'size' => 70, 'margin_left' => '60', 'margin_right' => '60', 'margin_unit' => 'px' ] ], [ [ 'type' => 'heading', 'heading' => 'Hi' ] ] ) )['elements'][0];
        $column = $row['elements'][0];
        $groups = $column['elements'];

        $this->assertSame( '0px', $column['settings']['module']['decoration']['layout']['desktop']['value']['columnGap'], "Divi's module gap would push 30% + 70% past the line" );
        $this->assertStringContainsString( 'flex: 0 0 30%;', $groups[0]['settings']['css']['desktop']['value']['freeForm'] );
        $this->assertStringContainsString( 'flex: 0 0 calc(70% - 60px - 60px);', $groups[1]['settings']['css']['desktop']['value']['freeForm'] );
        $this->assertSame( '60px', $groups[1]['settings']['module']['decoration']['spacing']['desktop']['value']['margin']['left'] );
    }

    public function test_text_colour_reaches_links_and_headings_like_beaver_builder(): void {
        $section = $this->section( $this->convert( [ 'text_color' => 'ffffff' ], [ [ 'size' => 100 ] ], [
            [ 'type' => 'rich-text', 'text' => '<h3>About</h3><p>Body <a href="#">link</a></p>', 'color' => '009960' ],
            [ 'type' => 'rich-text', 'text' => '<h3>Inherit</h3><p>Body <a href="#">link</a></p>' ],
        ] ) );
        [ $own, $inherited ] = $section['elements'][0]['elements'][0]['elements'];

        $this->assertSame( '#009960', $own['settings']['content']['decoration']['bodyFont']['link']['font']['desktop']['value']['color'], '.fl-rich-text * colours links' );
        $this->assertSame( '#009960', $own['settings']['content']['decoration']['headingFont']['h3']['font']['desktop']['value']['color'] );
        $this->assertSame( '#ffffff', $inherited['settings']['content']['decoration']['bodyFont']['body']['font']['desktop']['value']['color'] );
        $this->assertSame( '#ffffff', $inherited['settings']['content']['decoration']['bodyFont']['link']['font']['desktop']['value']['color'], 'row text_color colours links unless link_color is set' );
        $this->assertSame( '#ffffff', $inherited['settings']['content']['decoration']['headingFont']['h2']['font']['desktop']['value']['color'], 'row text_color colours headings unless heading_color is set' );
    }
}
