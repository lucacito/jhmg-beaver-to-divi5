<?php

use PHPUnit\Framework\TestCase;
use BeaverDivi5Converter\StyleMapper\StyleMapper;

final class StyleMapperTest extends TestCase {

    protected function setUp(): void {
        bbdc_test_reset_hooks();
    }

    private function map( string $kind, array $settings ): array {
        return ( new StyleMapper() )->map( $kind, $settings );
    }

    // --- spacing -------------------------------------------------------------

    public function test_row_padding_with_units_and_breakpoints(): void {
        $r = $this->map( 'row', [
            'padding_top' => '6', 'padding_bottom' => '6', 'padding_unit' => '%',
            'padding_top_medium' => '30', 'padding_medium_unit' => 'px',
            'padding_top_responsive' => '20', 'padding_responsive_unit' => 'px',
            'padding_top_large' => '50', 'padding_large_unit' => 'px',
            'margin_left' => '10', 'margin_unit' => 'px',
        ] );

        $spacing = $r['divi_attrs']['module']['decoration']['spacing'];
        $this->assertSame( [ 'top' => '6%', 'right' => '', 'bottom' => '6%', 'left' => '', 'syncVertical' => 'off', 'syncHorizontal' => 'off' ], $spacing['desktop']['value']['padding'] );
        $this->assertSame( '30px', $spacing['tablet']['value']['padding']['top'] );
        $this->assertSame( '20px', $spacing['phone']['value']['padding']['top'] );
        $this->assertSame( '10px', $spacing['desktop']['value']['margin']['left'] );
        $this->assertArrayNotHasKey( 'large', $spacing, '_large has no Divi breakpoint' );
        $this->assertContains( 'padding_top_large', $r['handled_keys'] );
        $this->assertContains( 'padding_large_unit', $r['handled_keys'] );
    }

    public function test_columns_keep_padding_but_not_margin(): void {
        $r = $this->map( 'column', [ 'padding_left' => '12', 'padding_unit' => 'px', 'margin_left' => '5', 'margin_unit' => 'px' ] );

        $this->assertSame( '12px', $r['divi_attrs']['module']['decoration']['spacing']['desktop']['value']['padding']['left'] );
        $this->assertArrayNotHasKey( 'margin', $r['divi_attrs']['module']['decoration']['spacing']['desktop']['value'] );
        $this->assertContains( 'margin_left', $r['handled_keys'] );
    }

    public function test_button_padding_targets_the_button_face(): void {
        $r = $this->map( 'button', [ 'padding_top' => '15', 'padding_left' => '30', 'margin_bottom' => '4' ] );

        $this->assertSame( '15px', $r['divi_attrs']['button']['decoration']['spacing']['desktop']['value']['padding']['top'] );
        $this->assertSame( '4px', $r['divi_attrs']['module']['decoration']['spacing']['desktop']['value']['margin']['bottom'] );
        $this->assertArrayNotHasKey( 'padding', $r['divi_attrs']['module']['decoration']['spacing']['desktop']['value'] );
    }

    // --- background ----------------------------------------------------------

    public function test_background_colour_is_normalised(): void {
        $r = $this->map( 'row', [ 'bg_type' => 'color', 'bg_color' => '102a43' ] );
        $this->assertSame( '#102a43', $r['divi_attrs']['module']['decoration']['background']['desktop']['value']['color'] );
    }

    public function test_bare_bg_color_without_a_type_still_maps(): void {
        $r = $this->map( 'blurb', [ 'bg_color' => 'rgba(0,0,0,0.1)' ] );
        $this->assertSame( 'rgba(0,0,0,0.1)', $r['divi_attrs']['module']['decoration']['background']['desktop']['value']['color'] );
    }

    public function test_background_photo_with_gradient_overlay(): void {
        $r = $this->map( 'row', [
            'bg_type'             => 'photo',
            'bg_image'            => 822,
            'bg_image_src'        => 'https://example.test/hero.jpg',
            'bg_repeat'           => 'no-repeat',
            'bg_position'         => 'center center',
            'bg_size'             => 'cover',
            'bg_overlay_type'     => 'gradient',
            'bg_overlay_gradient' => [ 'type' => 'linear', 'angle' => '135', 'position' => 'center center', 'colors' => [ 'rgba(16,42,67,0.95)', 'rgba(56,190,201,0.75)' ], 'stops' => [ '23', '94' ] ],
        ] );

        $bg = $r['divi_attrs']['module']['decoration']['background']['desktop']['value'];
        $this->assertSame( [ 'url' => 'https://example.test/hero.jpg', 'position' => 'center center', 'size' => 'cover', 'repeat' => 'no-repeat' ], $bg['image'] );
        $this->assertSame( 'on', $bg['gradient']['enabled'] );
        $this->assertSame( 'on', $bg['gradient']['overlaysImage'] );
        $this->assertSame( '135deg', $bg['gradient']['direction'] );
        // Divi appends the % itself; a position carrying a unit is rejected and the gradient dropped.
        $this->assertSame( [ [ 'color' => 'rgba(16,42,67,0.95)', 'position' => '23' ], [ 'color' => 'rgba(56,190,201,0.75)', 'position' => '94' ] ], $bg['gradient']['stops'] );
        $this->assertContains( 'bg_image_src', $r['handled_keys'] );
        $this->assertSame( [], $r['notes'] );
    }

    public function test_colour_overlay_becomes_a_flat_gradient(): void {
        $r  = $this->map( 'row', [ 'bg_type' => 'photo', 'bg_image_src' => 'https://x/y.jpg', 'bg_overlay_type' => 'color', 'bg_overlay_color' => 'rgba(0,0,0,0.5)' ] );
        $g  = $r['divi_attrs']['module']['decoration']['background']['desktop']['value']['gradient'];
        $this->assertSame( 'on', $g['overlaysImage'] );
        $this->assertSame( 'rgba(0,0,0,0.5)', $g['stops'][0]['color'] );
        $this->assertSame( 'rgba(0,0,0,0.5)', $g['stops'][1]['color'] );
    }

    public function test_url_sourced_background_photo(): void {
        $r = $this->map( 'row', [ 'bg_type' => 'photo', 'bg_image_source' => 'url', 'bg_image_url' => 'https://cdn/x.png', 'bg_image_src' => '' ] );
        $this->assertSame( 'https://cdn/x.png', $r['divi_attrs']['module']['decoration']['background']['desktop']['value']['image']['url'] );
    }

    public function test_gradient_background(): void {
        $r = $this->map( 'column', [ 'bg_type' => 'gradient', 'bg_gradient' => [ 'type' => 'radial', 'angle' => '90', 'position' => 'top left', 'colors' => [ 'ffffff', '000000' ], 'stops' => [ '0', '100' ] ] ] );
        $g = $r['divi_attrs']['module']['decoration']['background']['desktop']['value']['gradient'];
        $this->assertSame( 'radial', $g['type'] );
        $this->assertSame( 'top left', $g['directionRadial'], 'Beaver Builder "left top" is Divi "top left"' );
        $this->assertSame( '#ffffff', $g['stops'][0]['color'] );
        $this->assertArrayNotHasKey( 'overlaysImage', $g );
    }

    public function test_video_background_falls_back_to_its_poster_and_is_noted(): void {
        $r = $this->map( 'row', [ 'bg_type' => 'video', 'bg_video_fallback_src' => 'https://x/poster.jpg', 'bg_color' => '000' ] );
        $this->assertSame( 'https://x/poster.jpg', $r['divi_attrs']['module']['decoration']['background']['desktop']['value']['image']['url'] );
        $this->assertSame( '#000', $r['divi_attrs']['module']['decoration']['background']['desktop']['value']['color'] );
        $this->assertSame( 'background', $r['notes'][0]['kind'] );
    }

    public function test_slideshow_background_is_noted_and_overlay_kept_as_background(): void {
        $r = $this->map( 'row', [ 'bg_type' => 'slideshow', 'bg_overlay_type' => 'color', 'bg_overlay_color' => '333' ] );
        $this->assertSame( '#333', $r['divi_attrs']['module']['decoration']['background']['desktop']['value']['color'] );
        $this->assertSame( 'slideshow background dropped', $r['notes'][0]['detail'] );
    }

    public function test_unresolved_global_colour_is_noted_not_painted(): void {
        $r = $this->map( 'row', [ 'bg_type' => 'color', 'bg_color' => 'var(--fl-global-brand)' ] );
        $this->assertArrayNotHasKey( 'module', $r['divi_attrs'] );
        $this->assertSame( 'unresolved_global', $r['notes'][0]['kind'] );
    }

    // --- border / shadow -----------------------------------------------------------

    public function test_border_compound_field(): void {
        $r = $this->map( 'column', [ 'border' => [
            'style'  => 'solid',
            'color'  => 'e2e8f0',
            'width'  => [ 'top' => '1', 'right' => '1', 'bottom' => '1', 'left' => '1' ],
            'radius' => [ 'top_left' => '3', 'top_right' => '3', 'bottom_left' => '', 'bottom_right' => '3' ],
            'shadow' => [ 'color' => 'rgba(0,0,0,0.2)', 'horizontal' => '5', 'vertical' => '5', 'blur' => '20', 'spread' => '0' ],
        ] ] );

        $border = $r['divi_attrs']['module']['decoration']['border']['desktop']['value'];
        $this->assertSame( [ 'style' => 'solid', 'color' => '#e2e8f0', 'width' => '1px' ], $border['styles']['all'] );
        $this->assertSame( [ 'topLeft' => '3px', 'topRight' => '3px', 'bottomRight' => '3px', 'bottomLeft' => '0px' ], $border['radius'] );
        $this->assertSame( [ 'style' => 'preset1', 'position' => 'outer', 'color' => 'rgba(0,0,0,0.2)', 'horizontal' => '5px', 'vertical' => '5px', 'blur' => '20px', 'spread' => '0px' ], $r['divi_attrs']['module']['decoration']['boxShadow']['desktop']['value'] );
    }

    public function test_uneven_border_widths_are_written_per_side(): void {
        $r = $this->map( 'column', [ 'border' => [ 'style' => 'solid', 'width' => [ 'top' => '', 'right' => '', 'bottom' => '2', 'left' => '' ] ] ] );
        $styles = $r['divi_attrs']['module']['decoration']['border']['desktop']['value']['styles'];
        $this->assertSame( '2px', $styles['bottom']['width'] );
        $this->assertArrayNotHasKey( 'width', $styles['all'] );
    }

    public function test_button_and_image_borders_target_their_own_element(): void {
        $border = [ 'radius' => [ 'top_left' => '4', 'top_right' => '4', 'bottom_left' => '4', 'bottom_right' => '4' ] ];
        $this->assertArrayHasKey( 'radius', $this->map( 'button', [ 'border' => $border ] )['divi_attrs']['button']['decoration']['border']['desktop']['value'] );
        $this->assertArrayHasKey( 'radius', $this->map( 'image', [ 'border' => $border ] )['divi_attrs']['image']['decoration']['border']['desktop']['value'] );
    }

    // --- typography ------------------------------------------------------------------

    public function test_heading_typography(): void {
        $r = $this->map( 'heading', [
            'color'      => '1e293b',
            'typography' => [
                'font_family' => 'Poppins', 'font_weight' => '700', 'font_size' => [ 'length' => '48', 'unit' => 'px' ],
                'line_height' => [ 'length' => '1.2', 'unit' => '' ], 'letter_spacing' => [ 'length' => '-0.5', 'unit' => 'px' ],
                'text_align' => 'center', 'text_transform' => 'uppercase', 'text_decoration' => 'underline', 'font_style' => 'italic',
                'text_shadow' => [ 'color' => 'rgba(0,0,0,0.3)', 'horizontal' => '1', 'vertical' => '2', 'blur' => '3' ],
            ],
            'typography_responsive' => [ 'font_size' => [ 'length' => '32', 'unit' => 'px' ], 'text_align' => 'left' ],
        ] );

        $font = $r['divi_attrs']['title']['decoration']['font']['font'];
        $this->assertEquals( [
            'color' => '#1e293b',
            'family' => 'Poppins', 'weight' => '700', 'size' => '48px', 'lineHeight' => '1.2', 'letterSpacing' => '-0.5px',
            'textAlign' => 'center', 'style' => [ 'italic', 'uppercase', 'underline' ],
            'textShadow' => [ 'style' => 'preset1', 'color' => 'rgba(0,0,0,0.3)', 'horizontal' => '1px', 'vertical' => '2px', 'blur' => '3px' ],
        ], $font['desktop']['value'] );
        $this->assertSame( [ 'size' => '32px', 'textAlign' => 'left' ], $font['phone']['value'] );
        $this->assertContains( 'typography_medium', $r['handled_keys'] );
        $this->assertContains( 'typography_large', $r['handled_keys'] );
    }

    public function test_default_family_and_weight_are_ignored_and_italic_weights_split(): void {
        $r = $this->map( 'text', [ 'typography' => [ 'font_family' => 'Default', 'font_weight' => '600i', 'font_size' => [ 'length' => '', 'unit' => 'px' ], 'text_align' => 'justify' ] ] );
        $font = $r['divi_attrs']['content']['decoration']['bodyFont']['body']['font']['desktop']['value'];
        $this->assertSame( [ 'weight' => '600', 'style' => [ 'italic' ] ], $font );
        $this->assertSame( 'left', $r['divi_attrs']['module']['advanced']['text']['text']['desktop']['value']['orientation'], 'text alignment on a text module is the orientation; justify has no Divi value' );
    }

    public function test_button_text_colour_and_alignment(): void {
        $r = $this->map( 'button', [ 'text_color' => 'ffffff', 'align' => 'center', 'align_responsive' => 'left' ] );
        $this->assertSame( '#ffffff', $r['divi_attrs']['button']['decoration']['font']['font']['desktop']['value']['color'] );
        $this->assertSame( 'center', $r['divi_attrs']['module']['advanced']['alignment']['desktop']['value'] );
        $this->assertSame( 'left', $r['divi_attrs']['module']['advanced']['alignment']['phone']['value'] );
    }

    public function test_image_and_icon_alignment_paths(): void {
        $this->assertSame( 'right', $this->map( 'image', [ 'align' => 'right' ] )['divi_attrs']['module']['advanced']['align']['desktop']['value'] );
        $this->assertSame( 'center', $this->map( 'icon', [ 'align' => 'center' ] )['divi_attrs']['icon']['advanced']['align']['desktop']['value'] );
    }

    public function test_row_text_and_heading_colours_become_custom_css(): void {
        $r = $this->map( 'row', [ 'text_color' => 'ffffff', 'heading_color' => 'fff', 'link_color' => 'aaa', 'hover_color' => 'bbb' ] );
        $this->assertSame( 'color: #ffffff;', $r['divi_attrs']['css']['desktop']['value']['main'] );
        $this->assertStringContainsString( 'selector h1, selector h2', $r['divi_attrs']['css']['desktop']['value']['freeForm'] );
        $this->assertStringContainsString( 'selector a:hover { color: #bbb; }', $r['divi_attrs']['css']['desktop']['value']['freeForm'] );
    }

    // --- sizing / attributes / visibility -----------------------------------------------

    public function test_min_height_with_units_and_full_height(): void {
        $r = $this->map( 'row', [ 'full_height' => 'custom', 'min_height' => '70', 'min_height_unit' => 'vh', 'min_height_medium' => '50', 'min_height_medium_unit' => 'vh' ] );
        $sizing = $r['divi_attrs']['module']['decoration']['sizing'];
        $this->assertSame( '70vh', $sizing['desktop']['value']['minHeight'] );
        $this->assertSame( '50vh', $sizing['tablet']['value']['minHeight'] );

        $full = $this->map( 'row', [ 'full_height' => 'full' ] );
        $this->assertSame( '100vh', $full['divi_attrs']['module']['decoration']['sizing']['desktop']['value']['minHeight'] );

        $default = $this->map( 'row', [ 'full_height' => 'default', 'min_height' => '300', 'min_height_unit' => 'px' ] );
        $this->assertArrayNotHasKey( 'sizing', $default['divi_attrs']['module']['decoration'] ?? [], 'a stale min_height is ignored when the row is not set to use it' );
    }

    public function test_photo_width(): void {
        $r = $this->map( 'image', [ 'width' => '300', 'width_unit' => 'px', 'width_responsive' => '100', 'width_responsive_unit' => '%' ] );
        $this->assertSame( '300px', $r['divi_attrs']['module']['decoration']['sizing']['desktop']['value']['width'] );
        $this->assertSame( '100%', $r['divi_attrs']['module']['decoration']['sizing']['phone']['value']['width'] );
    }

    public function test_id_and_class(): void {
        $r = $this->map( 'row', [ 'id' => 'hero', 'class' => 'dark wide' ] );
        $this->assertSame( [ 'id' => 'hero', 'class' => 'dark wide' ], $r['divi_attrs']['module']['advanced']['htmlAttributes']['desktop']['value'] );
    }

    public function test_responsive_display_maps_to_disabled_on(): void {
        $r = $this->map( 'text', [ 'responsive_display' => 'desktop' ] );
        $this->assertSame( [ 'desktop' => [ 'value' => 'off' ], 'tablet' => [ 'value' => 'on' ], 'phone' => [ 'value' => 'on' ] ], $r['divi_attrs']['module']['decoration']['disabledOn'] );

        $range = $this->map( 'text', [ 'responsive_display' => 'medium-mobile' ] );
        $this->assertSame( 'on', $range['divi_attrs']['module']['decoration']['disabledOn']['desktop']['value'] );
        $this->assertSame( 'off', $range['divi_attrs']['module']['decoration']['disabledOn']['phone']['value'] );

        $list = $this->map( 'text', [ 'responsive_display' => 'desktop,large,medium' ] );
        $this->assertSame( 'on', $list['divi_attrs']['module']['decoration']['disabledOn']['phone']['value'] );

        $this->assertArrayNotHasKey( 'module', $this->map( 'text', [ 'responsive_display' => '' ] )['divi_attrs'] );
    }

    public function test_logged_in_visibility_animation_and_shapes_are_noted(): void {
        $r = $this->map( 'row', [ 'visibility_display' => 'logged_in', 'animation' => [ 'style' => 'fade-in' ], 'top_shape' => 'wave' ] );
        $kinds = array_column( $r['notes'], 'kind' );
        $this->assertSame( [ 'visibility', 'animation', 'shapes' ], $kinds );
    }

    public function test_column_size_to_fraction(): void {
        $this->assertSame( '4_4', StyleMapper::columnSizeToFraction( 100 ) );
        $this->assertSame( '1_3', StyleMapper::columnSizeToFraction( 33.33 ) );
        $this->assertSame( '2_3', StyleMapper::columnSizeToFraction( 66.67 ) );
        $this->assertSame( '1_2', StyleMapper::columnSizeToFraction( 50 ) );
        $this->assertSame( '1_4', StyleMapper::columnSizeToFraction( 25.5 ) );
        $this->assertNull( StyleMapper::columnSizeToFraction( 37 ) );
    }
}
