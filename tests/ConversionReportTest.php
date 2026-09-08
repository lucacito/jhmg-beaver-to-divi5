<?php

use PHPUnit\Framework\TestCase;
use BeaverDivi5Converter\Converter\ConverterEngine;

final class ConversionReportTest extends TestCase {

    protected function setUp(): void {
        bbdc_test_reset_hooks();
    }

    private function convert( array $row_settings, array $module_settings ): array {
        return ( new ConverterEngine() )->convert( [ 'nodes' => [
            'row1' => [ 'node' => 'row1', 'type' => 'row', 'parent' => null, 'position' => 0, 'settings' => $row_settings ],
            'grp1' => [ 'node' => 'grp1', 'type' => 'column-group', 'parent' => 'row1', 'position' => 0, 'settings' => '' ],
            'col1' => [ 'node' => 'col1', 'type' => 'column', 'parent' => 'grp1', 'position' => 0, 'settings' => [ 'size' => 100 ] ],
            'mod1' => [ 'node' => 'mod1', 'type' => 'module', 'parent' => 'col1', 'position' => 0, 'settings' => $module_settings ],
        ] ] )['report'];
    }

    public function test_animation_visibility_shapes_and_backgrounds_are_listed_with_their_node(): void {
        $report = $this->convert(
            [ 'bg_type' => 'video', 'animation' => [ 'style' => 'fade-in' ], 'top_shape' => 'wave', 'visibility_display' => 'logged_in' ],
            [ 'type' => 'heading', 'heading' => 'x', 'animation' => 'slide-up' ]
        );

        $this->assertEqualsCanonicalizing( [
            [ 'kind' => 'background', 'node_id' => 'row1', 'detail' => 'video background dropped (no fallback image)' ],
            [ 'kind' => 'visibility', 'node_id' => 'row1', 'detail' => 'logged-in users only' ],
            [ 'kind' => 'animation', 'node_id' => 'row1', 'detail' => 'fade-in' ],
            [ 'kind' => 'shapes', 'node_id' => 'row1', 'detail' => 'top_shape=wave' ],
            [ 'kind' => 'animation', 'node_id' => 'mod1', 'detail' => 'slide-up' ],
        ], $report['not_carried_over'] );
    }

    public function test_unresolved_global_colours_are_listed_once_per_setting(): void {
        $report = $this->convert( [ 'bg_type' => 'color', 'bg_color' => 'var(--fl-global-brand)' ], [ 'type' => 'heading', 'heading' => 'x', 'color' => 'fl-global-brand' ] );

        $this->assertSame( [
            [ 'node_id' => 'row1', 'setting_key' => 'bg_color', 'ref' => 'var(--fl-global-brand)' ],
            [ 'node_id' => 'mod1', 'setting_key' => 'color', 'ref' => 'fl-global-brand' ],
        ], $report['unresolved_globals'] );
    }

    public function test_resolved_global_colours_are_not_reported(): void {
        add_filter( 'bbdc_global_colors', fn() => [ 'brand' => '2b6cb0' ] );
        $report = $this->convert( [ 'bg_type' => 'color', 'bg_color' => 'var(--fl-global-brand)' ], [ 'type' => 'heading', 'heading' => 'x' ] );

        $this->assertSame( [], $report['unresolved_globals'] );
    }

    public function test_quality_summary(): void {
        $report = $this->convert( [], [ 'type' => 'heading', 'heading' => 'x', 'odd_key' => 1 ] );

        $this->assertSame( 100, $report['quality']['module_coverage'] );
        $this->assertSame( 1, $report['quality']['settings_issues'] );
        $this->assertSame( [ 'mod1: odd_key' ], $report['skipped_settings'] );
    }
}
