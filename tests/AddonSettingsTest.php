<?php

use PHPUnit\Framework\TestCase;
use BeaverDivi5Converter\Converter\ConverterEngine;
use BeaverDivi5Converter\Helpers\AddonSettings;

/**
 * Sites running Ultimate Addons or PowerPack save every add-on setting on every
 * row and column. At their defaults those keys are inert and must not be
 * reported one by one; when a feature is switched on it is one "not carried
 * over" entry naming the add-on and the feature.
 */
final class AddonSettingsTest extends TestCase {

    protected function setUp(): void {
        bbdc_test_reset_hooks();
    }

    /** A row as Ultimate Addons + PowerPack leave it when none of their features are used. */
    private function inertRowSettings(): array {
        return [
            'bg_type' => 'none', 'bg_color' => '',
            'uabb_row_gradient_type' => 'linear', 'uabb_row_radial_direction' => 'center_center', 'uabb_row_uabb_direction' => 'bottom',
            'uabb_row_linear_direction' => '90', 'uabb_row_linear_gradient_secondary_loc' => '100',
            'animation_type' => 'birds', 'bird_bg_color' => '07192f', 'bird_color_1' => 'ff0001', 'fog_speed' => '1', 'waves_color' => '005588', 'net_points' => '10',
            'enable_particles' => 'no', 'part_bg_color' => '07192f', 'part_rand_opacity' => 'true',
            'pp_bg_overlay_type' => 'full_width', 'scrolling_direction' => 'horizontal', 'scrolling_speed' => '50',
            'separator_shape' => 'none', 'separator_shape_width' => '100', 'bot_separator_shape' => 'none', 'bot_separator_color_opc' => '100',
            'enable_separator' => 'no', 'separator_type' => 'none', 'separator_color' => 'ffffff', 'separator_height' => '100', 'separator_position' => 'top',
            'enable_expandable' => 'no', 'er_title' => 'Click here to expand this row', 'er_transition_speed' => '500',
            'enable_down_arrow' => 'no', 'da_icon_style' => 'style-1', 'da_arrow_color' => [ 'primary' => '000000', 'secondary' => '000000' ],
            'top_edge_shape' => '', 'top_edge_align' => 'top center', 'top_edge_fill_color' => 'aaa',
            'top_edge_fill_gradient' => [ 'type' => 'linear', 'angle' => '90', 'position' => 'center center', 'colors' => [ '', '' ], 'stops' => [ '0', '100' ] ],
            'responsive_display_filtered' => true, 'visibility_logic' => '[]', 'flrich1676816719746_part_custom_code' => '', 'undefined' => '',
            'bg_hide_tablet' => 'no',
        ];
    }

    private function convertRow( array $row_settings, array $column_settings = [ 'size' => 100 ] ): array {
        return ( new ConverterEngine() )->convert( [ 'nodes' => [
            'row1' => [ 'node' => 'row1', 'type' => 'row', 'parent' => null, 'position' => 0, 'settings' => $row_settings ],
            'grp1' => [ 'node' => 'grp1', 'type' => 'column-group', 'parent' => 'row1', 'position' => 0, 'settings' => '' ],
            'col1' => [ 'node' => 'col1', 'type' => 'column', 'parent' => 'grp1', 'position' => 0, 'settings' => $column_settings ],
            'mod1' => [ 'node' => 'mod1', 'type' => 'module', 'parent' => 'col1', 'position' => 0, 'settings' => [ 'type' => 'heading', 'heading' => 'Hi', 'tag' => 'h2' ] ],
        ] ] );
    }

    public function test_inert_addon_defaults_are_counted_not_listed(): void {
        $report = $this->convertRow( $this->inertRowSettings() )['report'];

        $this->assertSame( [], $report['skipped_settings'], 'inert add-on keys must not be listed one by one' );
        $this->assertSame( [], $report['not_carried_over'] );
        $this->assertSame( 0, $report['quality']['settings_issues'] );

        $ignored = $report['addon_settings_ignored'];
        foreach ( [ 'Ultimate Addons row gradient background', 'Ultimate Addons animated background', 'Ultimate Addons expandable row', 'Ultimate Addons row down arrow', 'PowerPack scrolling image background', 'Beaver Builder row edge shape' ] as $label ) {
            $this->assertSame( 1, $ignored[ $label ] ?? null, "{$label} should be counted once for the row" );
        }
    }

    public function test_an_active_addon_feature_is_reported_once_per_node(): void {
        $settings = $this->inertRowSettings();
        $settings['enable_expandable'] = 'yes';
        $settings['er_title']          = 'Show more';

        $report = $this->convertRow( $settings )['report'];

        $this->assertContains(
            [ 'kind' => 'addon', 'node_id' => 'row1', 'detail' => 'expandable row (Ultimate Addons)' ],
            $report['not_carried_over']
        );
        $this->assertSame( [], array_filter( $report['skipped_settings'], static fn( string $s ) => str_contains( $s, 'er_' ) ), 'an active family is reported as one feature, not key by key' );
        $this->assertArrayNotHasKey( 'Ultimate Addons expandable row', $report['addon_settings_ignored'] );
    }

    public function test_an_addon_background_type_is_reported_as_a_dropped_background(): void {
        $settings = $this->inertRowSettings();
        $settings['bg_type']                          = 'uabb_gradient';
        $settings['uabb_row_gradient_primary_color']  = 'ff0000';
        $settings['uabb_row_gradient_secondary_color'] = '0000ff';

        $report = $this->convertRow( $settings )['report'];

        $this->assertContains( [ 'kind' => 'background', 'node_id' => 'row1', 'detail' => "add-on background type 'uabb_gradient' dropped" ], $report['not_carried_over'] );
        $this->assertContains( [ 'kind' => 'addon', 'node_id' => 'row1', 'detail' => 'row gradient background (Ultimate Addons)' ], $report['not_carried_over'] );
        $this->assertSame( [], $report['skipped_settings'] );
    }

    public function test_column_shadow_family_is_gated_by_its_toggle(): void {
        $column = [
            'size' => 100, 'col_drop_shadow' => 'no', 'col_shadow_color' => 'rgba(168,168,168,0.5)', 'col_shadow_color_blur' => '7',
            'col_hover_shadow' => 'no', 'col_shadow_hover_transition' => 200, 'uabb_col_gradient_type' => 'linear', 'uabb_col_linear_direction' => '24',
        ];
        $report = $this->convertRow( [], $column )['report'];
        $this->assertSame( [], $report['skipped_settings'] );
        $this->assertSame( 1, $report['addon_settings_ignored']['Ultimate Addons column shadow'] );

        $column['col_drop_shadow'] = 'yes';
        $report                    = $this->convertRow( [], $column )['report'];
        $this->assertContains( [ 'kind' => 'addon', 'node_id' => 'col1', 'detail' => 'column shadow (Ultimate Addons)' ], $report['not_carried_over'] );
    }

    public function test_row_only_families_never_swallow_module_keys(): void {
        $classified = AddonSettings::classify( [ 'type' => 'heading', 'da_custom' => 'x', 'er_title' => 'y', 'separator_type' => 'solid' ], true );
        $this->assertSame( [], $classified['ignored'] );
        $this->assertSame( [], $classified['active'] );
    }

    public function test_unit_only_typography_and_off_toggles_are_not_skipped_settings(): void {
        $result = ( new ConverterEngine() )->convert( [ 'nodes' => [
            'row1' => [ 'node' => 'row1', 'type' => 'row', 'parent' => null, 'position' => 0, 'settings' => [] ],
            'grp1' => [ 'node' => 'grp1', 'type' => 'column-group', 'parent' => 'row1', 'position' => 0, 'settings' => '' ],
            'col1' => [ 'node' => 'col1', 'type' => 'column', 'parent' => 'grp1', 'position' => 0, 'settings' => [ 'size' => 100 ] ],
            'mod1' => [ 'node' => 'mod1', 'type' => 'module', 'parent' => 'col1', 'position' => 0, 'settings' => [
                'type' => 'photo', 'photo_src' => 'https://example.test/a.jpg', 'alt' => 'a',
                'caption_typography_medium' => [ 'font_family' => 'Default', 'font_weight' => 'default', 'font_size' => [ 'unit' => 'px' ] ],
                'some_addon_toggle' => 'no',
                'some_addon_choice' => 'fancy',
            ] ],
        ] ] );
        $this->assertSame( [ 'mod1: some_addon_choice' ], $result['report']['skipped_settings'] );
    }
}
