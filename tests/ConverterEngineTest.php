<?php

use PHPUnit\Framework\TestCase;
use BeaverDivi5Converter\Converter\ConverterEngine;

final class ConverterEngineTest extends TestCase {

    protected function setUp(): void {
        bbdc_test_reset_hooks();
    }

    private function fixture( string $name ): array {
        return json_decode( (string) file_get_contents( __DIR__ . "/../fixtures/beaver/{$name}.json" ), true );
    }

    public function test_a_simple_row_becomes_section_row_column_heading(): void {
        $result = ( new ConverterEngine() )->convert( $this->fixture( 'simple-row' ) );

        $section = $result['divi']['elements'][0];
        $this->assertSame( 'divi/section', $section['name'] );
        $this->assertSame( 'row1', $section['id'] );
        $row = $section['elements'][0];
        $this->assertSame( 'divi/row', $row['name'] );
        $this->assertSame( '4_4', $row['settings']['module']['advanced']['columnStructure']['desktop']['value'] );
        $this->assertSame( '1100px', $row['settings']['module']['decoration']['sizing']['desktop']['value']['maxWidth'], 'fixed rows carry the global row width' );
        $column = $row['elements'][0];
        $this->assertSame( 'divi/column', $column['name'] );
        $this->assertSame( '4_4', $column['settings']['module']['advanced']['type']['desktop']['value'] );
        $heading = $column['elements'][0];
        $this->assertSame( 'divi/heading', $heading['name'] );
        $this->assertSame( 'Hello World', $heading['settings']['title']['innerContent']['desktop']['value'] );
        $this->assertSame( 'h2', $heading['settings']['title']['decoration']['font']['font']['desktop']['value']['headingLevel'] );

        $this->assertEquals( [ 'section' => 1, 'row' => 1, 'column' => 1, 'heading' => 1 ], $result['report']['converted'] );
        $this->assertSame( [], $result['unsupported'] );
        $this->assertSame( [], $result['report']['warnings'] );
        $this->assertSame( [], $result['report']['skipped_settings'] );
    }

    public function test_full_width_rows_and_column_fractions(): void {
        $result  = ( new ConverterEngine() )->convert( $this->fixture( 'two-columns' ) );
        $section = $result['divi']['elements'][0];

        $this->assertSame( '#102a43', $section['settings']['module']['decoration']['background']['desktop']['value']['color'] );
        $this->assertSame( '60px', $section['settings']['module']['decoration']['spacing']['desktop']['value']['padding']['top'] );

        $row = $section['elements'][0];
        $this->assertSame( '2_3,1_3', $row['settings']['module']['advanced']['columnStructure']['desktop']['value'] );
        $this->assertSame( '1100px', $row['settings']['module']['decoration']['sizing']['desktop']['value']['maxWidth'], 'full row with fixed content keeps the content width' );
        $this->assertSame( '#ffffff', $row['elements'][1]['settings']['module']['decoration']['background']['desktop']['value']['color'] );
    }

    public function test_stacked_groups_become_several_rows_and_min_height_goes_on_the_first(): void {
        $result  = ( new ConverterEngine() )->convert( $this->fixture( 'stacked-groups' ) );
        $section = $result['divi']['elements'][0];

        $this->assertCount( 2, $section['elements'] );
        $this->assertArrayNotHasKey( 'sizing', $section['settings']['module']['decoration'] ?? [] );
        $first = $section['elements'][0]['settings']['module']['decoration'];
        $this->assertSame( '50vh', $first['sizing']['desktop']['value']['minHeight'] );
        $this->assertSame( 'center', $first['layout']['desktop']['value']['alignItems'] );
        $this->assertArrayNotHasKey( 'minHeight', $section['elements'][1]['settings']['module']['decoration']['sizing']['desktop']['value'] );
        $this->assertSame( '1_2,1_2', $section['elements'][1]['settings']['module']['advanced']['columnStructure']['desktop']['value'] );
    }

    public function test_a_nested_column_group_becomes_a_row_inside_the_column(): void {
        $result = ( new ConverterEngine() )->convert( $this->fixture( 'nested-columns' ) );
        $column = $result['divi']['elements'][0]['elements'][0]['elements'][0];

        $this->assertSame( 'divi/heading', $column['elements'][0]['name'] );
        $inner = $column['elements'][1];
        $this->assertSame( 'divi/row', $inner['name'] );
        $this->assertSame( '1_2,1_2', $inner['settings']['module']['advanced']['columnStructure']['desktop']['value'] );
        $this->assertSame( 'Inner right', $inner['elements'][1]['elements'][0]['settings']['title']['innerContent']['desktop']['value'] );
        $this->assertEquals( [ 'section' => 1, 'row' => 2, 'column' => 3, 'heading' => 3 ], $result['report']['converted'] );
    }

    public function test_columns_without_a_divi_fraction_become_flex_groups(): void {
        $result = ( new ConverterEngine() )->convert( $this->fixture( 'uneven-columns' ) );
        $row    = $result['divi']['elements'][0]['elements'][0];

        $this->assertSame( '4_4', $row['settings']['module']['advanced']['columnStructure']['desktop']['value'] );
        $column = $row['elements'][0];
        $this->assertSame( 'flex', $column['settings']['module']['decoration']['layout']['desktop']['value']['display'] );
        $this->assertSame( 'divi/group', $column['elements'][0]['name'] );
        $this->assertSame( 'selector { flex: 0 0 37%; max-width: 37%; min-width: 0; box-sizing: border-box; margin-left: 0; margin-right: 0; }', $column['elements'][0]['settings']['css']['desktop']['value']['freeForm'] );
        $this->assertSame( '#eeeeee', $column['elements'][0]['settings']['module']['decoration']['background']['desktop']['value']['color'] );
        $this->assertSame( 'Wide', $column['elements'][1]['elements'][0]['settings']['title']['innerContent']['desktop']['value'] );
        $this->assertStringContainsString( 'no Divi column fraction', $result['report']['warnings'][0] );
    }

    public function test_an_unknown_module_leaves_a_labelled_placeholder_and_is_reported(): void {
        $result = ( new ConverterEngine() )->convert( $this->fixture( 'unsupported-module' ) );
        $block  = $result['divi']['elements'][0]['elements'][0]['elements'][0]['elements'][0];

        $this->assertSame( 'divi/code', $block['name'] );
        $this->assertStringContainsString( 'beaver builder module: wpforms-mystery', $block['settings']['content']['innerContent']['desktop']['value'] );
        $this->assertStringContainsString( 'Contact form', $block['settings']['content']['innerContent']['desktop']['value'] );
        $this->assertSame( [ [ 'id' => 'mod1', 'type' => 'module', 'module' => 'wpforms-mystery' ] ], $result['unsupported'] );
        $this->assertArrayNotHasKey( 'code', $result['report']['converted'], 'a rescue placeholder is not a conversion' );
        $this->assertSame( 75, $result['report']['quality']['module_coverage'] );
    }

    public function test_an_empty_row_still_produces_a_section_and_a_warning(): void {
        $result  = ( new ConverterEngine() )->convert( $this->fixture( 'empty-row' ) );
        $section = $result['divi']['elements'][0];

        $this->assertSame( '#ff0000', $section['settings']['module']['decoration']['background']['desktop']['value']['color'] );
        $this->assertSame( 'divi/column', $section['elements'][0]['elements'][0]['name'] );
        $this->assertSame( [ 'Empty row after conversion: row1' ], $result['report']['warnings'] );
    }

    public function test_orphans_and_root_modules_are_wrapped_in_a_section(): void {
        $result = ( new ConverterEngine() )->convert( [ 'nodes' => [
            'm1' => [ 'node' => 'm1', 'type' => 'module', 'parent' => 'gone', 'position' => 0, 'settings' => [ 'type' => 'heading', 'heading' => 'Lost' ] ],
        ] ] );

        $section = $result['divi']['elements'][0];
        $this->assertSame( 'divi/section', $section['name'] );
        $this->assertSame( 'Lost', $section['elements'][0]['elements'][0]['elements'][0]['settings']['title']['innerContent']['desktop']['value'] );
        $this->assertStringContainsString( 'm1 points at a parent that does not exist', $result['report']['warnings'][0] );
    }

    public function test_layout_custom_css_and_js_are_reported_as_not_carried_over(): void {
        $doc = $this->fixture( 'simple-row' );
        $doc['settings'] = [ 'css' => '.fl-row { color: red; }', 'js' => '' ];

        $result = ( new ConverterEngine() )->convert( $doc );

        $this->assertSame( [ [ 'kind' => 'custom_code', 'node_id' => 'layout', 'detail' => 'custom CSS (23 characters)' ] ], $result['report']['not_carried_over'] );
    }

    public function test_unconsumed_settings_are_reported_as_skipped(): void {
        $doc = $this->fixture( 'simple-row' );
        $doc['nodes']['mod1']['settings']['mystery_setting'] = 'x';

        $result = ( new ConverterEngine() )->convert( $doc );

        $this->assertSame( [ 'mod1: mystery_setting' ], $result['report']['skipped_settings'] );
    }
}
